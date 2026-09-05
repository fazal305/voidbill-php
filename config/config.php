<?php
/**
 * VOIDBILL configuration.
 *
 * A plain PHP associative array is enough configuration for an app this
 * size — no need for a config-loading library. Every other file that
 * needs a setting does `$config = require __DIR__ . '/../config/config.php';`
 * and reads it as a normal array.
 */

declare(strict_types=1);

return [
    'app_name'    => 'VOIDBILL',
    'app_tagline' => 'PHP INVOICE ENGINE',

    // 'development' is allowed to show raw PHP errors; 'production' is not.
    'env' => 'development',

    'currency_symbol' => 'Rs.',
    'invoice_prefix'  => 'INV',

    'storage' => [
        'counter_file'   => __DIR__ . '/../storage/counter.json',
        'invoices_file'  => __DIR__ . '/../storage/invoices.json',
    ],
];
