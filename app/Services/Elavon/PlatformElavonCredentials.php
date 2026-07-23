<?php

namespace App\Services\Elavon;

use App\Elavon\Converge2\Client\ClientConfig;
use App\Elavon\Converge2\Converge2;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;

class PlatformElavonCredentials
{
    /**
     * Platform subscription credentials for a shop (demo → sandbox .env, live → production .env).
     *
     * @return array{mercahantAlias: string, publicKey: string, secretKey: string, sandbox: bool}
     */
    public static function forShop(Shop $shop): array
    {
        return self::forSandbox($shop->usesElavonSandbox());
    }

    /**
     * Platform subscription credentials for a plugin (demo → sandbox .env, live → production .env).
     *
     * @return array{mercahantAlias: string, publicKey: string, secretKey: string, sandbox: bool}
     */
    public static function forPaymentMethodAccess(PaymentMethodAccess $access): array
    {
        return self::forSandbox($access->usesElavonSandbox());
    }

    /**
     * @return array{mercahantAlias: string, publicKey: string, secretKey: string, sandbox: bool}
     */
    public static function forSandbox(bool $sandbox): array
    {
        $environment = $sandbox ? 'sandbox' : 'production';
        $credentials = config("services.enterprise_elavon.credentials.{$environment}", []);

        return [
            'mercahantAlias' => str_replace(' ', '', (string) ($credentials['merchant_alias'] ?? '')),
            'publicKey' => str_replace(' ', '', (string) ($credentials['public_key'] ?? '')),
            'secretKey' => str_replace(' ', '', (string) ($credentials['secret_key'] ?? '')),
            'sandbox' => $sandbox,
        ];
    }

    public static function clientConfig(bool $sandbox): ClientConfig
    {
        $keys = self::forSandbox($sandbox);

        $config = new ClientConfig;
        $config->setMerchantAlias($keys['mercahantAlias']);
        $config->setPublicKey($keys['publicKey']);
        $config->setSecretKey($keys['secretKey']);

        if ($sandbox) {
            $config->setSandboxMode();
        }

        return $config;
    }

    public static function client(bool $sandbox): Converge2
    {
        return new Converge2(self::clientConfig($sandbox));
    }

    public static function clientForShop(Shop $shop): Converge2
    {
        return self::client($shop->usesElavonSandbox());
    }

    /**
     * Resolve the Converge client when the environment is unknown (e.g. webhook callbacks).
     */
    public static function clientForNotification(string $notificationId): ?Converge2
    {
        foreach ([true, false] as $sandbox) {
            $client = self::client($sandbox);
            $notification = $client->getNotification($notificationId);

            if ($notification->isSuccess()) {
                return $client;
            }
        }

        return null;
    }
}
