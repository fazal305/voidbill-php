<?php
/**
 * VOIDBILL — Phase 5: server-side validation.
 *
 * Every value submitted is now checked by validateInvoiceData() in
 * src/validation.php before the totals are trusted. Validation only
 * runs when the user actually tries to update the invoice (action ===
 * "update") — adding or removing a blank item row shouldn't suddenly
 * flag every other field as invalid.
 *
 * There is no JavaScript yet. "+ Add Item" and "- Remove Last Item"
 * are ordinary submit buttons — the whole form (including every value
 * already typed) is resubmitted, PHP grows or shrinks the $items array
 * with array_push()/array_pop(), and the page re-renders with the
 * updated row count and updated totals. Phase 11 layers instant
 * client-side add/remove on top of this; this server-only version
 * keeps working either way.
 */

declare(strict_types=1);

require __DIR__ . '/../src/calculations.php';
require __DIR__ . '/../src/validation.php';

$config = require __DIR__ . '/../config/config.php';

$appName    = $config['app_name'];
$tagline    = $config['app_tagline'];
$isDev      = $config['env'] === 'development';
$phpVersion = phpversion();

// Business identity stays hardcoded for now — there's no settings phase
// in this rebuild, so it isn't part of the form.
$business = [
    'name'    => 'Fazal Abbas',
    'owner'   => 'Fazal Abbas',
    'email'   => 'fazalabbas2002@gmail.com',
    'phone'   => '+92 300 1234567',
    'address' => 'Karachi, Pakistan',
];

$allowedStatuses = ['DRAFT', 'GENERATED', 'SENT', 'PAID', 'OVERDUE'];

// --- $_POST ------------------------------------------------------------
// The form's field names use bracket notation (customer[name],
// items[0][description], ...) so PHP parses $_POST into the same
// associative/multidimensional shapes Phase 2 hardcoded by hand.
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost) {
    $customer       = $_POST['customer'] ?? [];
    $invoiceFields  = $_POST['invoice'] ?? [];
    $settings       = $_POST['settings'] ?? [];
    $items          = $_POST['items'] ?? [];
    $action         = $_POST['action'] ?? '';
} else {
    // First visit: sensible defaults, zero items.
    $customer      = [];
    $invoiceFields = [
        'date'     => date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'status'   => 'DRAFT',
        'notes'    => '',
    ];
    $settings = [
        'discount_type'  => 'percentage',
        'discount_value' => '0',
        'tax_percent'    => '0',
    ];
    $items  = [];
    $action = '';
}

// Fill in any missing keys so every field below always has something to
// read — this is deliberately not validation (Phase 5 rejects bad
// values; this just prevents "undefined array key" notices).
$customer      += ['name' => '', 'company' => '', 'email' => '', 'phone' => '', 'address' => ''];
$invoiceFields += ['date' => '', 'due_date' => '', 'status' => 'DRAFT', 'notes' => ''];
$settings      += ['discount_type' => 'percentage', 'discount_value' => '0', 'tax_percent' => '0'];

// --- if / elseif: grow or shrink the items array ---------------------------
if ($action === 'add_item') {
    array_push($items, ['description' => '', 'quantity' => '', 'unitPrice' => '']);
} elseif ($action === 'remove_item') {
    if (count($items) > 0) {
        array_pop($items);
    }
}

$itemCount = count($items);

if ($itemCount === 0) {
    $itemsMessage = 'No invoice items yet. Add your first item to begin.';
} else {
    $itemsMessage = $itemCount . ' line item(s) in this invoice.';
}

$invoice = [
    'date'     => $invoiceFields['date'],
    'dueDate'  => $invoiceFields['due_date'],
    'status'   => $invoiceFields['status'],
    'notes'    => $invoiceFields['notes'],
    'customer' => $customer,
    'items'    => $items,
];

// --- Validation --------------------------------------------------------
// Only validate on a real "Update Preview" submission — not on the
// structural add_item/remove_item actions, and not on the first GET.
$shouldValidate = $isPost && !in_array($action, ['add_item', 'remove_item'], true);
$errors         = $shouldValidate ? validateInvoiceData($customer, $invoiceFields, $settings, $items) : [];
$hasErrors      = count($errors) > 0;

// --- The calculation engine ------------------------------------------------
// Every function here takes plain values in and returns a plain value out
// (function arguments, return values, local scope) — none of them know
// $items or $settings exist as variable names, only as parameters.
$discountValue = (float)$settings['discount_value'];
$taxPercent    = (float)$settings['tax_percent'];

$subtotal      = calculateSubtotal($items);
$discountAmount = calculateDiscount($subtotal, $settings['discount_type'], $discountValue);
$taxableAmount  = $subtotal - $discountAmount;
$taxAmount      = calculateTax($taxableAmount, $taxPercent);
$grandTotal     = calculateGrandTotal($taxableAmount, $taxAmount);

$currencySymbol = $config['currency_symbol'];

/**
 * Renders an error message under a field, if one exists for $key.
 * A tiny helper, not a whole templating layer — keeps the repeated
 * "if isset($errors[...])" pattern out of every field block below.
 */
function fieldError(array $errors, string $key): string
{
    if (!isset($errors[$key])) {
        return '';
    }
    return '<p class="field-error">' . e($errors[$key]) . '</p>';
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> — <?= e($tagline) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar">
    <span class="topbar__mark">VOID<span>BILL</span></span>
    <span class="topbar__tagline"><?= e($tagline) ?></span>
    <?php if ($isDev): ?>
        <span class="env-badge">PHP <?= e($phpVersion) ?> · dev</span>
    <?php endif; ?>
</header>

<main id="main" class="shell">
    <div class="workspace">
        <section class="panel" aria-label="Invoice builder">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Builder</h2>
            </div>
            <div class="panel__body">
                <?php if ($hasErrors): ?>
                    <div class="alert alert--error" role="alert">
                        Unable to update the invoice. Please check the highlighted fields and try again.
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php">
                    <fieldset class="field-group">
                        <legend>Customer</legend>
                        <div class="field <?= isset($errors['customer_name']) ? 'has-error' : '' ?>">
                            <label for="customer_name">Customer / Client Name</label>
                            <input type="text" id="customer_name" name="customer[name]" value="<?= e($customer['name']) ?>">
                            <?= fieldError($errors, 'customer_name') ?>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="customer_company">Company</label>
                                <input type="text" id="customer_company" name="customer[company]" value="<?= e($customer['company']) ?>">
                            </div>
                            <div class="field <?= isset($errors['customer_email']) ? 'has-error' : '' ?>">
                                <label for="customer_email">Email</label>
                                <input type="email" id="customer_email" name="customer[email]" value="<?= e($customer['email']) ?>">
                                <?= fieldError($errors, 'customer_email') ?>
                            </div>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="customer_phone">Phone</label>
                                <input type="text" id="customer_phone" name="customer[phone]" value="<?= e($customer['phone']) ?>">
                            </div>
                            <div class="field">
                                <label for="customer_address">Address</label>
                                <input type="text" id="customer_address" name="customer[address]" value="<?= e($customer['address']) ?>">
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="field-group">
                        <legend>Invoice</legend>
                        <div class="field-row">
                            <div class="field <?= isset($errors['invoice_date']) ? 'has-error' : '' ?>">
                                <label for="invoice_date">Invoice Date</label>
                                <input type="date" id="invoice_date" name="invoice[date]" value="<?= e($invoice['date']) ?>">
                                <?= fieldError($errors, 'invoice_date') ?>
                            </div>
                            <div class="field <?= isset($errors['invoice_due_date']) ? 'has-error' : '' ?>">
                                <label for="invoice_due_date">Due Date</label>
                                <input type="date" id="invoice_due_date" name="invoice[due_date]" value="<?= e($invoice['dueDate']) ?>">
                                <?= fieldError($errors, 'invoice_due_date') ?>
                            </div>
                        </div>
                        <div class="field">
                            <label for="invoice_status">Status</label>
                            <select id="invoice_status" name="invoice[status]">
                                <?php foreach ($allowedStatuses as $statusOption): ?>
                                    <option value="<?= e($statusOption) ?>" <?= $statusOption === $invoice['status'] ? 'selected' : '' ?>><?= e($statusOption) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </fieldset>

                    <fieldset class="field-group">
                        <legend>Items</legend>

                        <?php if ($itemCount === 0): ?>
                            <p class="empty-state <?= isset($errors['items']) ? 'has-error' : '' ?>"><?= e($errors['items'] ?? $itemsMessage) ?></p>
                        <?php else: ?>
                            <div class="items__head">
                                <span>Description</span><span>Qty</span><span>Unit Price</span>
                            </div>
                            <?php
                            // This foreach renders editable input rows — a different job
                            // from Phase 6's foreach, which will render calculated,
                            // read-only rows in the invoice preview.
                            foreach ($items as $i => $item): ?>
                                <div class="item-row">
                                    <div class="field <?= isset($errors["item_{$i}_description"]) ? 'has-error' : '' ?>">
                                        <input type="text" name="items[<?= (int)$i ?>][description]" placeholder="e.g. Website Development" value="<?= e($item['description'] ?? '') ?>">
                                        <?= fieldError($errors, "item_{$i}_description") ?>
                                    </div>
                                    <div class="field <?= isset($errors["item_{$i}_quantity"]) ? 'has-error' : '' ?>">
                                        <input type="number" step="0.001" name="items[<?= (int)$i ?>][quantity]" placeholder="1" value="<?= e((string)($item['quantity'] ?? '')) ?>">
                                        <?= fieldError($errors, "item_{$i}_quantity") ?>
                                    </div>
                                    <div class="field <?= isset($errors["item_{$i}_unitPrice"]) ? 'has-error' : '' ?>">
                                        <input type="number" step="0.01" name="items[<?= (int)$i ?>][unitPrice]" placeholder="0.00" value="<?= e((string)($item['unitPrice'] ?? '')) ?>">
                                        <?= fieldError($errors, "item_{$i}_unitPrice") ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <div class="item-actions">
                            <button type="submit" name="action" value="add_item" class="btn">+ Add Item</button>
                            <button type="submit" name="action" value="remove_item" class="btn btn--ghost" <?= $itemCount === 0 ? 'disabled' : '' ?>>− Remove Last Item</button>
                        </div>
                    </fieldset>

                    <fieldset class="field-group">
                        <legend>Discount &amp; Tax</legend>
                        <div class="field-row">
                            <div class="field <?= isset($errors['discount_type']) ? 'has-error' : '' ?>">
                                <label for="discount_type">Discount Type</label>
                                <select id="discount_type" name="settings[discount_type]">
                                    <option value="percentage" <?= $settings['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                                    <option value="fixed" <?= $settings['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                                </select>
                                <?= fieldError($errors, 'discount_type') ?>
                            </div>
                            <div class="field <?= isset($errors['discount_value']) ? 'has-error' : '' ?>">
                                <label for="discount_value">Discount Value</label>
                                <input type="number" step="0.01" id="discount_value" name="settings[discount_value]" value="<?= e((string)$settings['discount_value']) ?>">
                                <?= fieldError($errors, 'discount_value') ?>
                            </div>
                            <div class="field <?= isset($errors['tax_percent']) ? 'has-error' : '' ?>">
                                <label for="tax_percent">Tax %</label>
                                <input type="number" step="0.01" id="tax_percent" name="settings[tax_percent]" value="<?= e((string)$settings['tax_percent']) ?>">
                                <?= fieldError($errors, 'tax_percent') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="field-group">
                        <legend>Notes</legend>
                        <div class="field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="invoice[notes]" rows="3"><?= e($invoice['notes']) ?></textarea>
                        </div>
                    </fieldset>

                    <button type="submit" name="action" value="update" class="btn btn--primary btn--block">Update Preview</button>
                </form>
            </div>
        </section>

        <section class="panel" aria-label="Invoice preview">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Preview</h2>
            </div>
            <div class="panel__body">
                <div class="paper">
                    <div class="paper__brand">VOID<span>BILL</span></div>
                    <div class="paper__tagline"><?= e($tagline) ?></div>

                    <div class="paper__parties">
                        <div>
                            <div class="paper__label">From</div>
                            <strong><?= e($business['name']) ?></strong>
                        </div>
                        <div>
                            <div class="paper__label">Bill To</div>
                            <strong><?= $invoice['customer']['name'] !== '' ? e($invoice['customer']['name']) : 'Your customer' ?></strong>
                            <?php if ($invoice['customer']['company'] !== ''): ?>
                                <div><?= e($invoice['customer']['company']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="paper__meta">
                        Status: <strong><?= e($invoice['status']) ?></strong>
                        &middot; <?= e($itemsMessage) ?>
                    </p>
                    <?php if ($invoice['notes'] !== ''): ?>
                        <p class="paper__meta">Notes: <?= e($invoice['notes']) ?></p>
                    <?php endif; ?>

                    <?php if ($hasErrors): ?>
                        <p class="paper__meta paper__meta--error">Fix the highlighted fields to see accurate totals.</p>
                    <?php else: ?>
                        <p class="paper__meta">Itemized rows arrive in Phase 6 — these totals are already the real, server-calculated numbers.</p>

                        <div class="paper__totals">
                            <div class="paper__totals-row">
                                <span>Subtotal</span>
                                <span><?= e(formatCurrency($subtotal, $currencySymbol)) ?></span>
                            </div>
                            <div class="paper__totals-row">
                                <span>Discount</span>
                                <span><?= $discountAmount > 0 ? '− ' . e(formatCurrency($discountAmount, $currencySymbol)) : e(formatCurrency(0, $currencySymbol)) ?></span>
                            </div>
                            <div class="paper__totals-row">
                                <span>Taxable Amount</span>
                                <span><?= e(formatCurrency($taxableAmount, $currencySymbol)) ?></span>
                            </div>
                            <div class="paper__totals-row">
                                <span>Tax</span>
                                <span><?= $taxAmount > 0 ? '+ ' . e(formatCurrency($taxAmount, $currencySymbol)) : e(formatCurrency(0, $currencySymbol)) ?></span>
                            </div>
                            <div class="paper__totals-row paper__totals-row--grand">
                                <span>Grand Total</span>
                                <span><?= e(formatCurrency($grandTotal, $currencySymbol)) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <p class="footer-note">Phase 5 of 15 — server-side validation.</p>
</main>
</body>
</html>
