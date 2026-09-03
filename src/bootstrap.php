<?php
/**
 * VOIDBILL — shared bootstrap included by every public entry point.
 * Loads config, wires up error handling, starts the session, and
 * exposes the small set of classes/helpers the app needs.
 */

declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';

$isDev = ($config['env'] ?? 'production') === 'development';
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting(E_ALL);

if (!$isDev) {
    set_exception_handler(static function (Throwable $e): void {
        http_response_code(500);
        error_log($e->getMessage());
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>VOIDBILL</title>'
            . '<style>body{background:#0d0f12;color:#e8eaed;font-family:system-ui,sans-serif;'
            . 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}</style></head>'
            . '<body><div><h1 style="color:#4ee1a0;">Something went wrong</h1>'
            . '<p>VOIDBILL ran into an unexpected error. Please try again.</p></div></body></html>';
    });
}

require __DIR__ . '/helpers.php';
require __DIR__ . '/InvoiceCalculator.php';
require __DIR__ . '/InvoiceNumberGenerator.php';
require __DIR__ . '/Validator.php';
require __DIR__ . '/Storage.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

$storage = new Storage(
    $config['storage']['invoices_file'],
    $config['storage']['business_file']
);

$business = $storage->getBusiness($config['business_defaults']);
