<?php

/*
| Payments (SPEC §11, milestone 15): one-off Stripe Checkout payments.
| Without STRIPE_SECRET the app works, but nothing can be bought.
|
| Prices include IVA (21%, Spain). Create a 21% inclusive tax rate in the
| Stripe dashboard and put its id in STRIPE_TAX_RATE_ID so receipts and
| invoices show the IVA; or turn on Stripe Tax instead.
|
| ⚠️ The prices are PLACEHOLDERS: the SPEC leaves them open (§10).
*/

return [
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tax_rate_id' => env('STRIPE_TAX_RATE_ID'),
    ],

    'currency' => 'eur',

    // Starting capital anyone can use without paying.
    'free_capital_cents' => 3_000_000,

    'products' => [
        'viability_report' => [
            'name' => 'Full viability report (5 years)',
            'price_cents' => 2_900,
        ],
        // More starting capital: the owner's savings (SPEC §11).
        'capital_60k' => [
            'name' => 'Savings: start with up to €60,000',
            'price_cents' => 499,
            'capital_cents' => 6_000_000,
        ],
        'capital_100k' => [
            'name' => 'Savings: start with up to €100,000',
            'price_cents' => 999,
            'capital_cents' => 10_000_000,
        ],
    ],
];
