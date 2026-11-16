<?php

namespace App\Services;

use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Models\Traits\HasMeta;
use Illuminate\Database\Eloquent\Model;

class GatewayCredentialBackup
{
    public const META_KEY = 'original_gateway_credentials';

    /**
     * Merchant gateway meta keys that trial/demo credentials may replace.
     *
     * @var list<string>
     */
    public const CREDENTIAL_KEYS = [
        'elavon_merchant_alias',
        'elavon_public_key',
        'elavon_secret_key',
        'surfboard_terminalId',
        'surfboard_mit_terminalId',
        'surfboard_merchantId',
        'surfboard_storeId',
    ];

    /**
     * Snapshot existing Surfboard/Elavon credentials into a JSON meta before demo keys are applied.
     * Skips when a backup already exists so a later trial does not overwrite originals with demo values.
     *
     * @param  Shop|PaymentMethodAccess  $model
     */
    public function backupIfNeeded(Model $model): bool
    {
        $this->assertSupportsMetas($model);

        if ($this->hasBackup($model)) {
            return false;
        }

        $credentials = $this->currentCredentials($model);

        if ($credentials === []) {
            return false;
        }

        $elavon = array_filter([
            'elavon_merchant_alias' => $credentials['elavon_merchant_alias'] ?? null,
            'elavon_public_key' => $credentials['elavon_public_key'] ?? null,
            'elavon_secret_key' => $credentials['elavon_secret_key'] ?? null,
        ], fn ($value) => filled($value));

        $surfboard = array_filter([
            'surfboard_terminalId' => $credentials['surfboard_terminalId'] ?? null,
            'surfboard_mit_terminalId' => $credentials['surfboard_mit_terminalId'] ?? null,
            'surfboard_merchantId' => $credentials['surfboard_merchantId'] ?? null,
            'surfboard_storeId' => $credentials['surfboard_storeId'] ?? null,
        ], fn ($value) => filled($value));

        $payload = ['captured_at' => now()->toIso8601String()];

        if ($elavon !== []) {
            $payload['elavon'] = $elavon;
        }

        if ($surfboard !== []) {
            $payload['surfboard'] = $surfboard;
        }

        return $model->createMetas([self::META_KEY => $payload]);
    }

    /**
     * @param  Shop|PaymentMethodAccess  $model
     */
    public function hasBackup(Model $model): bool
    {
        $this->assertSupportsMetas($model);

        return $model->metas()->where('column_name', self::META_KEY)->exists();
    }

    /**
     * @param  Shop|PaymentMethodAccess  $model
     * @return array<string, string>
     */
    public function currentCredentials(Model $model): array
    {
        $this->assertSupportsMetas($model);

        // Read from the metas table directly so HasMeta's request cache cannot
        // return stale values from a previous model with the same id.
        return $model->metas()
            ->whereIn('column_name', self::CREDENTIAL_KEYS)
            ->whereNotNull('column_value')
            ->where('column_value', '!=', '')
            ->pluck('column_value', 'column_name')
            ->map(fn ($value) => (string) $value)
            ->all();
    }

    /**
     * @param  Shop|PaymentMethodAccess  $model
     * @return array{elavon?: array<string, string>, surfboard?: array<string, string>, captured_at?: string}|null
     */
    public function getBackup(Model $model): ?array
    {
        $this->assertSupportsMetas($model);

        $raw = $model->metas()->where('column_name', self::META_KEY)->value('column_value');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  Shop|PaymentMethodAccess  $model
     */
    protected function assertSupportsMetas(Model $model): void
    {
        if (! in_array(HasMeta::class, class_uses_recursive($model), true)) {
            throw new \InvalidArgumentException(sprintf(
                '%s must use the HasMeta trait to back up gateway credentials.',
                $model::class,
            ));
        }
    }
}
