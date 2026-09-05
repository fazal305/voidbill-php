<?php
/**
 * VOIDBILL — Phase 4: the calculation engine.
 *
 * Plain functions, not a class — each one takes the values it needs as
 * parameters and returns a result. Nothing here reads $_POST or touches
 * a global variable; every value a function needs comes in as an
 * argument, which is what makes these safe to test in isolation and
 * safe to reuse from anywhere (the form preview today, Phase 6's
 * itemized rendering later).
 *
 * Calculation flow:
 *   line totals -> subtotal -> discount -> taxable amount -> tax -> grand total
 */

declare(strict_types=1);

/**
 * One line item's total: quantity x unit price.
 */
function calculateLineTotal(float $quantity, float $unitPrice): float
{
    return $quantity * $unitPrice;
}

/**
 * Sums every item's line total into a subtotal.
 *
 * @param array<int, array{quantity: mixed, unitPrice: mixed}> $items
 */
function calculateSubtotal(array $items): float
{
    $subtotal = 0.0; // local to this function — nothing outside can see or change it directly

    foreach ($items as $item) {
        $quantity  = (float)($item['quantity'] ?? 0);
        $unitPrice = (float)($item['unitPrice'] ?? 0);
        $subtotal += calculateLineTotal($quantity, $unitPrice);
    }

    return $subtotal;
}

/**
 * Discount amount, either a percentage of the subtotal or a flat amount.
 * Never returns more than the subtotal itself — a bigger discount would
 * make the taxable amount negative, which doesn't make sense.
 */
function calculateDiscount(float $subtotal, string $discountType, float $discountValue): float
{
    switch ($discountType) {
        case 'percentage':
            $discount = $subtotal * ($discountValue / 100);
            break;
        case 'fixed':
            $discount = $discountValue;
            break;
        default:
            $discount = 0.0;
    }

    if ($discount < 0) {
        $discount = 0.0;
    } elseif ($discount > $subtotal) {
        $discount = $subtotal;
    }

    return $discount;
}

/**
 * Tax on the taxable amount (subtotal minus discount).
 */
function calculateTax(float $taxableAmount, float $taxPercent): float
{
    if ($taxPercent < 0) {
        $taxPercent = 0.0;
    }

    return $taxableAmount * ($taxPercent / 100);
}

/**
 * Taxable amount plus tax = what the customer actually owes.
 */
function calculateGrandTotal(float $taxableAmount, float $taxAmount): float
{
    return $taxableAmount + $taxAmount;
}

/**
 * Formats a number as money: rounded to 2 decimals, thousands separators,
 * a currency prefix. $currency has a default parameter so most call sites
 * don't need to repeat "Rs." every time.
 */
function formatCurrency(float $amount, string $currency = 'Rs.'): string
{
    $rounded = round($amount, 2);

    if ($rounded === -0.0) {
        $rounded = 0.0; // avoid printing "-0.00" for values that rounded to zero
    }

    return $currency . ' ' . number_format($rounded, 2);
}
