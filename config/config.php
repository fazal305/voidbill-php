<?php
/**
 * VOIDBILL configuration.
 * Central place for environment-level settings. No secrets belong here in
 * a real deployment — this is a small local app, so plain values are fine.
 */

declare(strict_types=1);

return [
    'app_name' => 'VOIDBILL',
    'app_tagline' => 'PHP INVOICE ENGINE',

    // 'production' hides raw PHP errors from users; 'development' shows them.
    'env' => 'production',

    'currency' => [
        'code' => 'PKR',
        'symbol' => 'Rs.',
    ],

    'invoice' => [
        'prefix' => 'INV',
        'max_items' => 50,
        'max_unit_price' => 999999999.99,
        'max_quantity' => 999999.999,
        'max_discount_percent' => 100,
        'max_tax_percent' => 100,
    ],

    'upload' => [
        'max_bytes' => 2 * 1024 * 1024, // 2MB
        'allowed_mime' => ['image/png', 'image/jpeg', 'image/webp'],
        'allowed_ext' => ['png', 'jpg', 'jpeg', 'webp'],
        'dir' => __DIR__ . '/../public/uploads',
    ],

    'storage' => [
        'dir' => __DIR__ . '/../storage',
        'invoices_file' => __DIR__ . '/../storage/invoices.json',
        'counter_file' => __DIR__ . '/../storage/counter.json',
        'business_file' => __DIR__ . '/../storage/business.json',
    ],

    'business_defaults' => [
        'name' => 'Fazal Abbas',
        'owner' => 'Fazal Abbas',
        'title' => 'Software Developer',
        'email' => '',
        'phone' => '',
        'address' => '',
        'website' => '',
        'logo' => '',
        'invoice_prefix' => 'INV',
        'currency_symbol' => 'Rs.',
        'payment_terms' => "50% upfront, remaining 50% upon project completion.",
        'terms_conditions' => "Quotation valid for 15 days from the invoice date.",
    ],
];
