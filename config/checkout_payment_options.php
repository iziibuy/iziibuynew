<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout payment options catalog (defaults)
    |--------------------------------------------------------------------------
    |
    | Rows in the checkout_payment_options table override these by key.
    | Customers pick an option at checkout; the shop maps each option to one
    | allowed acquirer (elavon, surfboard, quickpay, dintero, …).
    |
    */

    'acquirers' => [
        'elavon' => 'Elavon',
        'surfboard' => 'Surfboard',
        'quickpay' => 'QuickPay',
        'dintero' => 'Dintero',
        'two' => 'Two',
    ],

    'options' => [
        'visa' => [
            'label' => 'Visa',
            'icon' => 'visa',
            'acquirers' => ['elavon', 'surfboard', 'quickpay'],
            'sort' => 10,
            'active' => true,
        ],
        'mastercard' => [
            'label' => 'Mastercard',
            'icon' => 'mastercard',
            'acquirers' => ['elavon', 'surfboard', 'quickpay'],
            'sort' => 20,
            'active' => true,
        ],
        'amex' => [
            'label' => 'Amex',
            'icon' => 'amex',
            'acquirers' => ['elavon', 'surfboard', 'quickpay'],
            'sort' => 30,
            'active' => true,
        ],
        'vipps' => [
            'label' => 'Vipps',
            'icon' => 'vipps',
            'acquirers' => ['surfboard', 'dintero'],
            'sort' => 40,
            'active' => true,
        ],
        'swish' => [
            'label' => 'Swish',
            'icon' => 'swish',
            'acquirers' => ['surfboard'],
            'sort' => 50,
            'active' => true,
        ],
        'googlepay' => [
            'label' => 'Google Pay',
            'icon' => 'googlepay',
            'acquirers' => ['surfboard', 'dintero'],
            'sort' => 60,
            'active' => true,
        ],
        'applepay' => [
            'label' => 'Apple Pay',
            'icon' => 'applepay',
            'acquirers' => ['surfboard', 'dintero'],
            'sort' => 70,
            'active' => true,
        ],
        'klarna' => [
            'label' => 'Klarna',
            'icon' => 'klarna',
            'acquirers' => ['surfboard'],
            'sort' => 80,
            'active' => true,
        ],
    ],
];
