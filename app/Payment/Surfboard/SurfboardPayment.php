<?php

namespace App\Payment\Surfboard;

class SurfboardPayment
{
    public string $apiUrl;

    public string $apiKey;

    public string $apiSecret;

    public string $partnerId;

    public string $merchantId;

    public string $storeId;

    public function __construct($merchantId = null, $storeId = null, $sandbox = false)
    {

        // These properties are typed `string` (non-nullable), so env() returning
        // null must be resolved to a fallback BEFORE assignment — assigning null
        // directly throws a TypeError immediately, skipping any fallback checked
        // afterwards.
        $this->apiUrl = env('SURFBOARD_API_URL') ?: 'https://lithium.surfgw.com/api';

        if ($sandbox) {
            $this->apiKey = 'ex2vlacjexeawnywpojghc0l3_iugg.service.zpzq7@surfboard.service';
            $this->apiSecret = 'KUs2d3if7bVY0m4aBUBYa7kHqCvu7t';
        } else {
            $this->apiKey = (string) env('SURFBOARD_API_KEY');
            $this->apiSecret = (string) env('SURFBOARD_API_SECRET');
        }

        $this->partnerId = (string) env('SURFBOARD_PARTNER_ID');
        $this->merchantId = (string) ($merchantId ?? env('SURFBOARD_MERCHANT_ID'));
        $this->storeId = (string) ($storeId ?? env('SURFBOARD_STORE_ID'));
        // $this->apiUrl = 'https://lithium.surfgw.com/api';
        // $this->apiKey = 'ex2vlacjexeawnywpojghc0l3_iugg.service.zpzq7@surfboard.service';
        // $this->apiSecret = 'KUs2d3if7bVY0m4aBUBYa7kHqCvu7t';
        // $this->partnerId = '82ade0f0013ec80309';
        // $this->merchantId = $merchantId ?? '82ade587195008050e';
        // $this->storeId = $storeId ?? '82ade5873a5890070f';
    }
}
