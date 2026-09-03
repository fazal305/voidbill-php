<?php
/**
 * VOIDBILL — server-side invoice math. This is the single source of truth
 * for every number that ends up on an invoice; the browser only ever shows
 * a preview of what this class will compute.
 */

declare(strict_types=1);

final class InvoiceCalculator
{
    /**
     * Calculate the line total for a single item, rounded to money precision.
     */
    public static function lineTotal(float $quantity, float $unitPrice): float
    {
        return round_money($quantity * $unitPrice);
    }

    /**
     * Sum the line totals of every item into a subtotal.
     *
     * @param array<int, array{quantity: float, unit_price: float}> $items
     */
    public static function calculateSubtotal(array $items): float
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += self::lineTotal((float)$item['quantity'], (float)$item['unit_price']);
        }
        return round_money($subtotal);
    }

    /**
     * Calculate the discount amount. Supports either a percentage of the
     * subtotal or a flat amount, but never lets the discount exceed the
     * subtotal (which would produce a negative taxable amount).
     */
    public static function calculateDiscount(float $subtotal, string $discountType, float $discountValue): float
    {
        if ($discountType === 'fixed') {
            $discount = $discountValue;
        } else {
            $discount = $subtotal * ($discountValue / 100);
        }

        $discount = max(0.0, $discount);
        $discount = min($discount, $subtotal);

        return round_money($discount);
    }

    public static function calculateAfterDiscount(float $subtotal, float $discountAmount): float
    {
        return round_money(max(0.0, $subtotal - $discountAmount));
    }

    public static function calculateTax(float $afterDiscount, float $taxPercent): float
    {
        return round_money($afterDiscount * (max(0.0, $taxPercent) / 100));
    }

    public static function calculateTotal(float $afterDiscount, float $taxAmount): float
    {
        return round_money($afterDiscount + $taxAmount);
    }

    /**
     * Run the full pipeline and return every intermediate figure needed to
     * render an invoice: subtotal, discount, taxable amount, tax, total.
     *
     * @param array<int, array{description: string, quantity: float, unit_price: float}> $items
     * @return array{subtotal: float, discount_amount: float, taxable_amount: float, tax_amount: float, total: float, items: array}
     */
    public static function calculate(array $items, string $discountType, float $discountValue, float $taxPercent): array
    {
        $itemsWithTotals = array_map(static function (array $item): array {
            $item['line_total'] = self::lineTotal((float)$item['quantity'], (float)$item['unit_price']);
            return $item;
        }, $items);

        $subtotal = self::calculateSubtotal($items);
        $discountAmount = self::calculateDiscount($subtotal, $discountType, $discountValue);
        $taxableAmount = self::calculateAfterDiscount($subtotal, $discountAmount);
        $taxAmount = self::calculateTax($taxableAmount, $taxPercent);
        $total = self::calculateTotal($taxableAmount, $taxAmount);

        return [
            'items' => $itemsWithTotals,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'taxable_amount' => $taxableAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }
}
