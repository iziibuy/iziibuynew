<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Elavon\Converge2\Converge2;
use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\ExternalSubscriptionCharge;
use App\Models\PaymentMethodAccess;
use App\Models\SubscriptionCharge;
use App\Services\Elavon\ElavonRecurringTransaction;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves the last four card digits used for a unified charge ledger row.
 *
 * Uses digits stored on the charge first, then falls back to the Elavon transaction.
 */
class ChargeCardLookup
{
    protected const FAILED_LOOKUP_TTL_MINUTES = 10;

    protected ?int $remoteLookupsRemaining = null;

    public function __construct(protected ChargeElavonGateway $gateway) {}

    /**
     * Cap how many Elavon requests this instance may make, e.g. once per table render.
     */
    public function limitRemoteLookups(int $limit): static
    {
        $this->remoteLookupsRemaining = max($limit, 0);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $record
     */
    public function lastFour(array $record): ?string
    {
        return $this->resolveLastFour($record, forceRemote: false);
    }

    /**
     * Fetch last four digits from Elavon when missing, or again when $force is true.
     *
     * @param  array<string, mixed>  $record
     */
    public function syncFromElavon(array $record, bool $force = false): ?string
    {
        return $this->resolveLastFour($record, forceRemote: $force);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    protected function resolveLastFour(array $record, bool $forceRemote): ?string
    {
        [$source, $id] = UnifiedChargeLedger::parseKey((string) ($record['id'] ?? ''));

        try {
            return match ($source) {
                UnifiedChargeLedger::SOURCE_SHOP => $this->forShopCharge($id, $forceRemote),
                UnifiedChargeLedger::SOURCE_SUBSCRIPTION => $this->forSubscriptionCharge($id, $forceRemote),
                UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION => $this->forExternalSubscriptionCharge($id),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning('Charge card lookup failed', ['record' => $record['id'] ?? null, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    protected function forShopCharge(int $id, bool $forceRemote = false): ?string
    {
        $charge = Charge::query()->with('shop')->find($id);

        if ($charge === null) {
            return null;
        }

        if (! $forceRemote) {
            $stored = $this->lastFourFromShopChargeRecord($charge);
            if ($stored !== null) {
                return $stored;
            }
        }

        $recordKey = UnifiedChargeLedger::SOURCE_SHOP.'-'.$id;
        if ($forceRemote) {
            $this->forgetLookupCache($recordKey);
            $this->forgetLookupCache($this->storedCardCacheKey($recordKey));
        }

        $lastFour = $this->resolveLastFourFromElavon(
            $recordKey,
            $this->gateway->transactionId($charge),
            $this->gateway->storedCardId($charge),
            fn (): ?Converge2 => $this->gateway->client($charge),
        );

        if ($lastFour !== null) {
            $this->persistLastFourOnShopCharge($charge, $lastFour);
        }

        return $lastFour;
    }

    protected function lastFourFromShopChargeRecord(Charge $charge): ?string
    {
        $details = json_decode((string) ($charge->getAttributes()['details'] ?? ''), true);
        if (is_array($details)) {
            $fromDetails = $this->normalize(data_get($details, 'last4') ?? data_get($details, 'card.last4'));
            if ($fromDetails !== null) {
                return $fromDetails;
            }
        }

        $fromQuickPay = $this->normalize(rescue(fn () => $charge->lastFour, null, false));

        return $fromQuickPay;
    }

    protected function persistLastFourOnShopCharge(Charge $charge, string $lastFour): void
    {
        $details = json_decode((string) ($charge->getAttributes()['details'] ?? ''), true);
        $details = is_array($details) ? $details : [];
        $charge->forceFill([
            'details' => json_encode(array_merge($details, ['last4' => $lastFour])),
        ])->saveQuietly();
    }

    protected function forSubscriptionCharge(int $id, bool $forceRemote = false): ?string
    {
        $charge = SubscriptionCharge::query()->with('subscription.subscribable')->find($id);

        if ($charge === null) {
            return null;
        }

        if (! $forceRemote) {
            $stored = $this->normalize($charge->last4);
            if ($stored !== null) {
                return $stored;
            }
        }

        $subscribable = $charge->subscription?->subscribable;

        if (! ($subscribable instanceof PaymentMethodAccess || $subscribable instanceof Enterprise)) {
            return null;
        }

        $recordKey = UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$id;
        if ($forceRemote) {
            $this->forgetLookupCache($recordKey);
            $this->forgetLookupCache($this->storedCardCacheKey($recordKey));
        }

        $lastFour = $this->resolveLastFourFromElavon(
            $recordKey,
            $this->gateway->transactionId($charge),
            $this->gateway->storedCardId($charge),
            fn (): ?Converge2 => $this->gateway->client($charge),
        );

        if ($lastFour !== null) {
            $details = json_decode((string) ($charge->getAttributes()['charge_details'] ?? ''), true);
            $charge->forceFill([
                'charge_details' => json_encode(array_merge(is_array($details) ? $details : [], ['last4' => $lastFour])),
            ])->saveQuietly();
        }

        return $lastFour;
    }

    /**
     * @param  Closure(): ?Converge2  $client
     */
    protected function resolveLastFourFromElavon(
        string $recordKey,
        ?string $transactionId,
        ?string $storedCardId,
        Closure $client,
    ): ?string {
        if ($transactionId !== null) {
            $fromTransaction = $this->fetchFromElavon($recordKey, $transactionId, $client);
            if ($fromTransaction !== null) {
                return $fromTransaction;
            }
        }

        return $this->fetchLastFourFromStoredCard($recordKey, $storedCardId, $client);
    }

    /**
     * @param  Closure(): ?Converge2  $client
     */
    protected function fetchLastFourFromStoredCard(string $recordKey, ?string $storedCardId, Closure $client): ?string
    {
        if (blank($storedCardId)) {
            return null;
        }

        $cacheKey = $this->storedCardCacheKey($recordKey);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === '' ? null : (string) $cached;
        }

        if ($this->remoteLookupsRemaining !== null) {
            if ($this->remoteLookupsRemaining <= 0) {
                return null;
            }

            $this->remoteLookupsRemaining--;
        }

        $converge = $client();
        $lastFour = $converge !== null
            ? ElavonRecurringTransaction::cardLastFourFromStoredCardId($converge, $storedCardId)
            : null;

        if ($lastFour === null) {
            Cache::put($cacheKey, '', now()->addMinutes(self::FAILED_LOOKUP_TTL_MINUTES));

            return null;
        }

        Cache::forever($cacheKey, $lastFour);

        return $lastFour;
    }

    protected function storedCardCacheKey(string $recordKey): string
    {
        return 'charge-card-last4-stored:'.$recordKey;
    }

    protected function forExternalSubscriptionCharge(int $id): ?string
    {
        $charge = ExternalSubscriptionCharge::query()->find($id);
        $details = json_decode((string) ($charge?->getAttributes()['payment_details'] ?? ''), true);

        return $this->normalize(data_get($details, 'last4') ?? data_get($details, 'card.last4'));
    }

    /**
     * @param  Closure(): ?Converge2  $client
     */
    protected function fetchFromElavon(string $recordKey, string $transactionId, Closure $client): ?string
    {
        $cacheKey = 'charge-card-last4:'.$recordKey;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached === '' ? null : (string) $cached;
        }

        if ($this->remoteLookupsRemaining !== null) {
            if ($this->remoteLookupsRemaining <= 0) {
                return null;
            }

            $this->remoteLookupsRemaining--;
        }

        $lastFour = null;

        try {
            $transaction = $client()?->getTransaction($transactionId);

            if ($transaction?->isSuccess() && method_exists($transaction, 'castCard')) {
                $lastFour = $this->normalize($transaction->castCard()?->getLast4());
            }
        } catch (Throwable $exception) {
            Log::warning('Elavon card lookup failed', ['transaction_id' => $transactionId, 'error' => $exception->getMessage()]);
        }

        if ($lastFour === null) {
            Cache::put($cacheKey, '', now()->addMinutes(self::FAILED_LOOKUP_TTL_MINUTES));

            return null;
        }

        Cache::forever($cacheKey, $lastFour);

        return $lastFour;
    }

    protected function normalize(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return strlen((string) $digits) >= 4 ? substr((string) $digits, -4) : null;
    }

    protected function forgetLookupCache(string $recordKey): void
    {
        Cache::forget('charge-card-last4:'.$recordKey);
    }
}
