<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Elavon\Converge2\Converge2;
use App\Models\Charge;
use App\Models\Enterprise;
use App\Models\ExternalSubscriptionCharge;
use App\Models\PaymentMethodAccess;
use App\Models\SubscriptionCharge;
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
        [$source, $id] = UnifiedChargeLedger::parseKey((string) ($record['id'] ?? ''));

        try {
            return match ($source) {
                UnifiedChargeLedger::SOURCE_SHOP => $this->forShopCharge($id),
                UnifiedChargeLedger::SOURCE_SUBSCRIPTION => $this->forSubscriptionCharge($id),
                UnifiedChargeLedger::SOURCE_EXTERNAL_SUBSCRIPTION => $this->forExternalSubscriptionCharge($id),
                default => null,
            };
        } catch (Throwable $exception) {
            Log::warning('Charge card lookup failed', ['record' => $record['id'] ?? null, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    protected function forShopCharge(int $id): ?string
    {
        $charge = Charge::query()->with('shop')->find($id);

        if ($charge === null) {
            return null;
        }

        $stored = $this->normalize(rescue(fn () => $charge->lastFour, null, false));
        if ($stored !== null) {
            return $stored;
        }

        $transactionId = $this->gateway->transactionId($charge);

        if ($transactionId === null) {
            return null;
        }

        return $this->fetchFromElavon(
            UnifiedChargeLedger::SOURCE_SHOP.'-'.$id,
            $transactionId,
            fn (): ?Converge2 => $this->gateway->client($charge),
        );
    }

    protected function forSubscriptionCharge(int $id): ?string
    {
        $charge = SubscriptionCharge::query()->with('subscription.subscribable')->find($id);

        if ($charge === null) {
            return null;
        }

        $stored = $this->normalize($charge->last4);
        if ($stored !== null) {
            return $stored;
        }

        $transactionId = $this->gateway->transactionId($charge);
        $subscribable = $charge->subscription?->subscribable;

        if ($transactionId === null || ! ($subscribable instanceof PaymentMethodAccess || $subscribable instanceof Enterprise)) {
            return null;
        }

        $lastFour = $this->fetchFromElavon(
            UnifiedChargeLedger::SOURCE_SUBSCRIPTION.'-'.$id,
            $transactionId,
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
}
