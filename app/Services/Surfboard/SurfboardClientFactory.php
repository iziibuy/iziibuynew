<?php

namespace App\Services\Surfboard;

use App\Models\ExternalBooking;
use App\Models\PaymentMethodAccess;
use App\Models\Shop;
use App\Payment\Surfboard\SurfboardPayment;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SurfboardClientFactory
{
    public function makeFromRequest(Request $request): SurfboardApiClient
    {
        $context = $request->query('context', 'platform');

        $surfboard = match ($context) {
            'platform' => $this->makePlatformClient($request),
            'shop' => $this->makeFromShop(
                Shop::query()->findOrFail($request->integer('shop_id')),
            ),
            'external' => $this->makeFromExternalBooking(
                ExternalBooking::query()->where('ulid', $request->query('booking'))->firstOrFail(),
            ),
            default => throw new InvalidArgumentException(
                'Invalid context. Use platform, shop, or external.',
            ),
        };

        return new SurfboardApiClient($surfboard);
    }

    public function makePlatformClient(Request $request): SurfboardPayment
    {
        $sandbox = $request->boolean('sandbox', app()->environment('local', 'testing'));

        return $this->makeClient(
            (string) config('services.surfboard.merchant_id', env('SURFBOARD_MERCHANT_ID')),
            (string) config('services.surfboard.store_id', env('SURFBOARD_STORE_ID')),
            $sandbox,
        );
    }

    public function makeFromShop(Shop $shop): SurfboardPayment
    {
        return $this->makeClient(
            (string) $shop->surfboard_merchantId,
            (string) $shop->surfboard_storeId,
            $shop->site_mode !== 'live',
        );
    }

    public function makeFromPaymentMethodAccess(PaymentMethodAccess $access): SurfboardPayment
    {
        return $this->makeClient(
            (string) $access->surfboard_merchantId,
            (string) $access->surfboard_storeId,
            $access->site_mode !== 'live',
        );
    }

    public function makeFromExternalBooking(ExternalBooking $booking): SurfboardPayment
    {
        $access = $booking->paymentMethodAccess;

        if (! $access) {
            throw new InvalidArgumentException('External booking has no payment method access configured.');
        }

        return $this->makeFromPaymentMethodAccess($access);
    }

    protected function makeClient(string $merchantId, string $storeId, bool $sandbox): SurfboardPayment
    {
        if ($merchantId === '') {
            throw new InvalidArgumentException('Surfboard merchant ID is required.');
        }

        return new SurfboardPayment(
            merchantId: $merchantId,
            storeId: $storeId,
            sandbox: $sandbox,
        );
    }
}
