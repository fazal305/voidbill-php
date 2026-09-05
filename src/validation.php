<?php
/**
 * VOIDBILL — Phase 5: server-side validation.
 *
 * JavaScript doesn't exist yet, so there is no client-side validation to
 * "back up" — everything checked here is the only line of defense. Every
 * rule returns a plain associative array of errors ($errors['field'] =>
 * message) instead of throwing or dying, so the caller can redisplay the
 * form with every value the user already typed still in place.
 */

declare(strict_types=1);

/**
 * A small local helper: is $value a real calendar date in Y-m-d format?
 * (Used instead of just checking strtotime() !== false, which happily
 * accepts garbage like "next Tuesday".)
 */
function isValidDate(string $value): bool
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Validates the whole invoice submission and returns an associative
 * array of field => error message. An empty array means everything
 * passed.
 *
 * @param array<string, string> $customer
 * @param array<string, string> $invoiceFields
 * @param array<string, string> $settings
 * @param array<int, array<string, mixed>> $items
 * @return array<string, string>
 */
function validateInvoiceData(array $customer, array $invoiceFields, array $settings, array $items): array
{
    $errors = [];

    $allowedStatuses      = ['DRAFT', 'GENERATED', 'SENT', 'PAID', 'OVERDUE'];
    $allowedDiscountTypes = ['percentage', 'fixed'];

    // --- Customer -----------------------------------------------------------
    if (trim($customer['name']) === '') {
        $errors['customer_name'] = 'Customer name is required.';
    }

    // Email is optional, but if something was typed it must look like an email.
    if ($customer['email'] !== '' && filter_var($customer['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors['customer_email'] = 'Enter a valid email address.';
    }

    // --- Invoice dates --------------------------------------------------------
    if (trim($invoiceFields['date']) === '') {
        $errors['invoice_date'] = 'Invoice date is required.';
    } elseif (!isValidDate($invoiceFields['date'])) {
        $errors['invoice_date'] = 'Invoice date is not a valid date.';
    }

    if ($invoiceFields['due_date'] !== '') {
        if (!isValidDate($invoiceFields['due_date'])) {
            $errors['invoice_due_date'] = 'Due date is not a valid date.';
        } elseif (!isset($errors['invoice_date']) && strtotime($invoiceFields['due_date']) < strtotime($invoiceFields['date'])) {
            // Only compare against the invoice date once we know it's valid —
            // a logical AND-style guard using a short-circuiting elseif chain.
            $errors['invoice_due_date'] = 'Due date cannot be before the invoice date.';
        }
    }

    // --- Status & discount type: in_array() against an allow-list ------------
    if (!in_array($invoiceFields['status'], $allowedStatuses, true)) {
        $errors['invoice_status'] = 'That is not a recognized invoice status.';
    }

    if (!in_array($settings['discount_type'], $allowedDiscountTypes, true)) {
        $errors['discount_type'] = 'That is not a recognized discount type.';
    }

    // --- Discount value & tax percent ------------------------------------------
    if (!is_numeric($settings['discount_value'])) {
        $errors['discount_value'] = 'Discount value must be a number.';
    } elseif ((float)$settings['discount_value'] < 0) {
        $errors['discount_value'] = 'Discount value cannot be negative.';
    } elseif ($settings['discount_type'] === 'percentage' && (float)$settings['discount_value'] > 100) {
        $errors['discount_value'] = 'A percentage discount cannot exceed 100%.';
    }

    if (!is_numeric($settings['tax_percent'])) {
        $errors['tax_percent'] = 'Tax percent must be a number.';
    } elseif ((float)$settings['tax_percent'] < 0 || (float)$settings['tax_percent'] > 100) {
        $errors['tax_percent'] = 'Tax percent must be between 0 and 100.';
    }

    // --- Items ------------------------------------------------------------
    if (count($items) === 0) {
        $errors['items'] = 'Add at least one invoice item.';
    } else {
        foreach ($items as $i => $item) {
            $description = trim((string)($item['description'] ?? ''));
            $quantity    = $item['quantity'] ?? '';
            $unitPrice   = $item['unitPrice'] ?? '';

            // A completely untouched blank row (e.g. one just added with
            // "+ Add Item") isn't an error yet — skip it and keep checking
            // the rest of the items.
            if ($description === '' && $quantity === '' && $unitPrice === '') {
                continue;
            }

            $rowNumber = $i + 1;

            if ($description === '') {
                $errors["item_{$i}_description"] = "Item {$rowNumber} needs a description.";
            }

            if ($quantity === '' || !is_numeric($quantity)) {
                $errors["item_{$i}_quantity"] = "Item {$rowNumber} quantity must be a number.";
            } elseif ((float)$quantity <= 0) {
                $errors["item_{$i}_quantity"] = "Item {$rowNumber} quantity must be greater than zero.";
            }

            if ($unitPrice === '' || !is_numeric($unitPrice)) {
                $errors["item_{$i}_unitPrice"] = "Item {$rowNumber} price must be a number.";
            } elseif ((float)$unitPrice < 0) {
                $errors["item_{$i}_unitPrice"] = "Item {$rowNumber} price cannot be negative.";
            }
        }
    }

    return $errors;
}
