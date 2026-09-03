<?php
/**
 * Small stateless helpers shared across the app. Anything that needs
 * config, storage, or business rules lives in its own class instead.
 */

declare(strict_types=1);

/**
 * Escape a string for safe HTML output. Every piece of user-controlled
 * data must pass through this before it touches a template.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format a number as money using the app's currency symbol, with
 * thousands separators and exactly two decimal places. Avoids the
 * classic "150000.0000001" float-formatting problem.
 */
function money(float $amount, string $symbol = 'Rs.'): string
{
    $rounded = round($amount + 0.0, 2);
    // Avoid printing "-0.00" for values that rounded to zero.
    if ($rounded === -0.0) {
        $rounded = 0.0;
    }
    return $symbol . ' ' . number_format($rounded, 2);
}

/**
 * Round a monetary value using standard half-up rounding to 2 decimals.
 */
function round_money(float $amount): float
{
    return round($amount, 2, PHP_ROUND_HALF_UP);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Read a value from a nested array by dot path, e.g. get($config, 'upload.max_bytes').
 */
function config_get(array $config, string $path, $default = null)
{
    $segments = explode('.', $path);
    $value = $config;
    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function old(array $old, string $key, string $default = ''): string
{
    return e((string)($old[$key] ?? $default));
}

function flash_set(string $key, $value): void
{
    $_SESSION['flash'][$key] = $value;
}

function flash_get(string $key, $default = null)
{
    $value = $_SESSION['flash'][$key] ?? $default;
    unset($_SESSION['flash'][$key]);
    return $value;
}
