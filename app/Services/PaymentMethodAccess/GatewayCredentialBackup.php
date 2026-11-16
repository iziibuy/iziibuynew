<?php

namespace App\Services\PaymentMethodAccess;

use App\Models\PaymentMethodAccess;

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
     */
    public function backupIfNeeded(PaymentMethodAccess $access): bool
    {
        if ($this->hasBackup($access)) {
            return false;
        }

        $credentials = $this->currentCredentials($access);

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

        return $access->createMetas([self::META_KEY => $payload]);
    }

    public function hasBackup(PaymentMethodAccess $access): bool
    {
        return $access->metas()->where('column_name', self::META_KEY)->exists();
    }

    /**
     * @return array<string, string>
     */
    public function currentCredentials(PaymentMethodAccess $access): array
    {
        $credentials = [];

        foreach (self::CREDENTIAL_KEYS as $key) {
            $value = $access->{$key};

            if (filled($value)) {
                $credentials[$key] = (string) $value;
            }
        }

        return $credentials;
    }

    /**
     * @return array{elavon?: array<string, string>, surfboard?: array<string, string>, captured_at?: string}|null
     */
    public function getBackup(PaymentMethodAccess $access): ?array
    {
        $raw = $access->metas()->where('column_name', self::META_KEY)->value('column_value');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
