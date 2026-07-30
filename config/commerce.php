<?php

use App\Services\Payments\Drivers\BankTransferGateway;
use App\Services\Payments\Drivers\PaystackGateway;
use App\Services\Payments\Drivers\SandboxGateway;

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The institution bills in one currency; multi-currency is deliberately out
    | of scope. `code` is what gateways are told, `symbol` is what users see.
    |
    */

    'currency' => env('COMMERCE_CURRENCY', 'NGN'),
    'symbol' => env('COMMERCE_CURRENCY_SYMBOL', '₦'),

    /*
    |--------------------------------------------------------------------------
    | Payment drivers
    |--------------------------------------------------------------------------
    |
    | Every gateway the app knows how to talk to. A driver being listed here only
    | means the code exists — whether it is OFFERED at checkout is decided by the
    | matching row in the payment_methods table, which an admin toggles from
    | Store → Payment methods. That is why credentials live in the database
    | (encrypted) rather than here: they are edited by staff, not deployed.
    |
    | `supports_subscriptions` is presentational for now (Section 12 sells one-off
    | course purchases only); it drives the capability chip on the admin card.
    |
    */

    'drivers' => [

        'sandbox' => [
            'class' => SandboxGateway::class,
            'label' => 'Sandbox (test only)',
            'supports_subscriptions' => false,
            // Instantly marks an order paid without leaving the app. Enabled by
            // default so a fresh install and the test suite can complete a
            // purchase end-to-end with no gateway account.
            'default_enabled' => true,
        ],

        'paystack' => [
            'class' => PaystackGateway::class,
            'label' => 'Paystack',
            'supports_subscriptions' => true,
            'default_enabled' => false,
            // Seeded into the encrypted config column so the admin form has the
            // right shape; blank values mean "not configured yet".
            'config' => [
                'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
                'secret_key' => env('PAYSTACK_SECRET_KEY', ''),
            ],
        ],

        'bank_transfer' => [
            'class' => BankTransferGateway::class,
            'label' => 'Bank transfer',
            'supports_subscriptions' => false,
            'default_enabled' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Paystack
    |--------------------------------------------------------------------------
    |
    | Base URL only. Keys are read from the payment_methods row, never from env
    | at request time, so an admin can rotate them without a deploy. The env
    | values above are seed defaults for a fresh install.
    |
    */

    'paystack' => [
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart
    |--------------------------------------------------------------------------
    |
    | How long a signed-out visitor's cart survives. Their cart is keyed on a
    | cookie token and merged into their account on login, so this only governs
    | abandoned guest carts.
    |
    */

    'cart' => [
        'guest_lifetime_days' => (int) env('COMMERCE_GUEST_CART_DAYS', 30),
        'cookie' => 'uprl_cart',
        'max_items' => 50,
    ],

];
