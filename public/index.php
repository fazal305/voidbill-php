<?php
/**
 * VOIDBILL — Phase 13: testing + a real security fix (CSRF protection).
 *
 * Auditing this form for state-changing requests (it can write a new
 * invoice number and record) turned up a genuine gap: there was no
 * CSRF protection anywhere. src/csrf.php now issues a session-bound
 * token, embedded as a hidden field and checked before any POST is
 * processed — a request missing or carrying the wrong token is
 * rejected before it ever reaches validation or the calculation
 * engine, exactly like a request with bad data would be.
 *
 * assets/js/app.js adds: draft autosave/recovery via localStorage
 * (a UX convenience only — the saved invoice data in storage/ remains
 * the only source of truth), toast confirmations for restoring/
 * discarding a draft, a Ctrl/Cmd+Enter shortcut for Generate Invoice,
 * and a loading state that disables the submit buttons the instant one
 * is clicked, so an impatient double-click can't fire two submissions.
 *
 * "Print Invoice" calls the browser's native window.print(). Its
 * assets/css/print.css (loaded only for the print media type) hides
 * everything except the invoice document itself, so what prints is a
 * clean A4 page, not a screenshot of the dark app shell.
 *
 * The paper preview shows everything a real invoice needs: full
 * business and customer contact details, invoice date and due date,
 * a color-coded status badge, payment terms, and terms & conditions —
 * not just a name and a total.
 *
 * "Update Preview" (validates + calculates, doesn't commit anything) and
 * "Generate Invoice" (validates + calculates + assigns a real sequential
 * number + saves the record to storage/invoices.json) are two distinct
 * actions. Only "Generate Invoice" touches the counter file, so simply
 * tweaking a field and previewing again never burns an invoice number.
 *
 * Every value submitted is checked by validateInvoiceData() in
 * src/validation.php before the totals are trusted, and before an
 * invoice is allowed to be generated at all.
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

// Secure session handling: no JavaScript access to the cookie, and not
// sent on cross-site navigations — both irrelevant to a plain page
// view, but exactly what the CSRF token below relies on being true.
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require __DIR__ . '/../src/calculations.php';
require __DIR__ . '/../src/validation.php';
require __DIR__ . '/../src/persistence.php';
require __DIR__ . '/../src/csrf.php';

$config = require __DIR__ . '/../config/config.php';

$appName    = $config['app_name'];
$tagline    = $config['app_tagline'];
$isDev      = $config['env'] === 'development';
$phpVersion = phpversion();

// $isDev previously only toggled the little debug badge — it never
// actually controlled whether PHP shows a raw error to the user. In
// production mode, a fatal error (an unwritable storage/ directory, a
// corrupt invoices.json, anything unexpected) should never dump a
// stack trace; it should log the real error and show a plain message.
ini_set('display_errors', $isDev ? '1' : '0');
error_reporting(E_ALL);

if (!$isDev) {
    set_exception_handler(function (Throwable $e): void {
        error_log($e->getMessage());
        http_response_code(500);
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>VOIDBILL</title></head>'
            . '<body style="background:#0d0f12;color:#e8eaed;font-family:system-ui,sans-serif;'
            . 'display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;">'
            . '<div><h1 style="color:#4ee1a0;">Something went wrong</h1>'
            . '<p>VOIDBILL ran into an unexpected error. Please try again.</p></div></body></html>';
    });
}

// Business identity stays hardcoded for now — there's no settings phase
// in this rebuild, so it isn't part of the form.
$business = [
    'name'    => 'Fazal Abbas',
    'owner'   => 'Fazal Abbas',
    'email'   => 'fazalabbas2002@gmail.com',
    'phone'   => '+92 300 1234567',
    'address' => 'Karachi, Pakistan',
    'website' => 'fazalabbas.dev',
];

$allowedStatuses = ['DRAFT', 'GENERATED', 'SENT', 'PAID', 'OVERDUE'];

// --- $_POST ------------------------------------------------------------
// The form's field names use bracket notation (customer[name],
// items[0][description], ...) so PHP parses $_POST into the same
// associative/multidimensional shapes Phase 2 hardcoded by hand.
$isPost    = $_SERVER['REQUEST_METHOD'] === 'POST';
$csrfError = false;

if ($isPost && !csrfVerify($_POST['csrf_token'] ?? null)) {
    // A missing or wrong token means this request didn't originate from
    // VOIDBILL's own form in this session — most likely an expired
    // session after a long idle period, possibly a forged request.
    // Either way, its data isn't trustworthy, so it's treated like a
    // fresh page load rather than acted on.
    $csrfError = true;
    $isPost = false;
}

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
        'date'             => date('Y-m-d'),
        'due_date'         => date('Y-m-d', strtotime('+14 days')),
        'status'           => 'DRAFT',
        'notes'            => '',
        'payment_terms'    => 'Payment due within 14 days of the invoice date.',
        'terms_conditions' => 'Late payments may be subject to a rescheduling fee.',
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
$invoiceFields += [
    'date' => '', 'due_date' => '', 'status' => 'DRAFT', 'notes' => '',
    'payment_terms' => '', 'terms_conditions' => '',
];
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
    'date'            => $invoiceFields['date'],
    'dueDate'         => $invoiceFields['due_date'],
    'status'          => $invoiceFields['status'],
    'notes'           => $invoiceFields['notes'],
    'paymentTerms'    => $invoiceFields['payment_terms'],
    'termsConditions' => $invoiceFields['terms_conditions'],
    'customer'        => $customer,
    'items'           => $items,
];

// --- Validation --------------------------------------------------------
// Only validate on a real "Update Preview" or "Generate Invoice"
// submission — not on the structural add_item/remove_item actions, and
// not on the first GET.
$shouldValidate = $isPost && in_array($action, ['update', 'generate'], true);
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

// --- Invoice numbering & persistence ---------------------------------------
// Only "Generate Invoice" reaches this — "Update Preview" recalculates
// and re-validates every time, but never burns a number or writes a
// record, so a user can safely tweak fields and preview repeatedly.
$generatedNumber = null;

if ($action === 'generate' && !$hasErrors) {
    $generatedNumber = generateInvoiceNumber($config['storage']['counter_file'], $config['invoice_prefix']);

    // Only save rows the user actually filled in — an untouched blank
    // "+ Add Item" row shouldn't end up in permanent storage.
    $itemsToSave = [];
    foreach ($items as $item) {
        $description = trim((string)($item['description'] ?? ''));
        $quantity    = $item['quantity'] ?? '';
        $unitPrice   = $item['unitPrice'] ?? '';
        if ($description === '' && $quantity === '' && $unitPrice === '') {
            continue;
        }
        $itemsToSave[] = $item;
    }

    saveInvoiceRecord($config['storage']['invoices_file'], [
        'number'          => $generatedNumber,
        'date'            => $invoice['date'],
        'dueDate'         => $invoice['dueDate'],
        'status'          => $invoice['status'],
        'customer'        => $invoice['customer'],
        'items'           => $itemsToSave,
        'notes'           => $invoice['notes'],
        'paymentTerms'    => $invoice['paymentTerms'],
        'termsConditions' => $invoice['termsConditions'],
        'discountType'   => $settings['discount_type'],
        'discountValue'  => $discountValue,
        'taxPercent'     => $taxPercent,
        'subtotal'       => $subtotal,
        'discountAmount' => $discountAmount,
        'taxableAmount'  => $taxableAmount,
        'taxAmount'      => $taxAmount,
        'grandTotal'     => $grandTotal,
        'generatedAt'    => date('Y-m-d H:i:s'),
    ]);
}

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
    return '<p class="field-error" id="' . e($key) . '-error">' . e($errors[$key]) . '</p>';
}

/**
 * Outputs an aria-describedby attribute pointing at fieldError()'s id,
 * but only when that field actually has an error — screen readers
 * shouldn't be told to look at a description that doesn't exist.
 */
function describedBy(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' aria-describedby="' . e($key) . '-error"' : '';
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Maps an invoice status to a CSS modifier class for its badge. A
 * second, distinct use of switch alongside calculateDiscount()'s —
 * that one branches on discount type, this one on invoice status,
 * exactly the two candidates the spec calls out for switch.
 */
function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'DRAFT':
            return 'badge--draft';
        case 'GENERATED':
            return 'badge--generated';
        case 'SENT':
            return 'badge--sent';
        case 'PAID':
            return 'badge--paid';
        case 'OVERDUE':
            return 'badge--overdue';
        default:
            return 'badge--draft';
    }
}

/**
 * Formats a Y-m-d date string for display (e.g. "05 September 2026"),
 * falling back to the raw value for anything that isn't a valid date —
 * which can happen on the very first "+ Add Item" click before the
 * date field has been touched, since that action skips validation.
 */
function formatDisplayDate(string $value): string
{
    if (!isValidDate($value)) {
        return $value !== '' ? $value : '—';
    }
    return date('d F Y', strtotime($value));
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
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="topbar no-print">
    <span class="topbar__mark">VOID<span>BILL</span></span>
    <span class="topbar__tagline"><?= e($tagline) ?></span>
    <nav class="topnav">
        <a href="index.php" aria-current="page">New Invoice</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
    <?php if ($isDev): ?>
        <span class="env-badge">PHP <?= e($phpVersion) ?> · dev</span>
    <?php endif; ?>
</header>

<main id="main" class="shell">
    <div class="page-header">
        <div>
            <h1>New Invoice</h1>
            <p>Build, validate, and generate a professional invoice.</p>
        </div>
    </div>

    <div class="workspace">
        <section class="panel" aria-label="Invoice builder">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Builder</h2>
            </div>
            <div class="panel__body">
                <?php if ($csrfError): ?>
                    <div class="alert alert--error" role="alert">
                        Your session expired or the request could not be verified. Your previous entries were not applied — please re-enter them and try again.
                    </div>
                <?php endif; ?>
                <?php if ($hasErrors): ?>
                    <div class="alert alert--error" role="alert">
                        Unable to update the invoice. Please check the highlighted fields and try again.
                    </div>
                <?php endif; ?>

                <form method="post" action="index.php" id="invoice-form" data-is-post="<?= $isPost ? '1' : '0' ?>" data-generated="<?= $generatedNumber !== null ? e($generatedNumber) : '' ?>">
                    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                    <fieldset class="field-group">
                        <legend>Customer</legend>
                        <div class="field <?= isset($errors['customer_name']) ? 'has-error' : '' ?>">
                            <label for="customer_name">Customer / Client Name</label>
                            <input type="text" id="customer_name" name="customer[name]" value="<?= e($customer['name']) ?>"<?= describedBy($errors, 'customer_name') ?>>
                            <?= fieldError($errors, 'customer_name') ?>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label for="customer_company">Company</label>
                                <input type="text" id="customer_company" name="customer[company]" value="<?= e($customer['company']) ?>">
                            </div>
                            <div class="field <?= isset($errors['customer_email']) ? 'has-error' : '' ?>">
                                <label for="customer_email">Email</label>
                                <input type="email" id="customer_email" name="customer[email]" value="<?= e($customer['email']) ?>"<?= describedBy($errors, 'customer_email') ?>>
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
                                <input type="date" id="invoice_date" name="invoice[date]" value="<?= e($invoice['date']) ?>"<?= describedBy($errors, 'invoice_date') ?>>
                                <?= fieldError($errors, 'invoice_date') ?>
                            </div>
                            <div class="field <?= isset($errors['invoice_due_date']) ? 'has-error' : '' ?>">
                                <label for="invoice_due_date">Due Date</label>
                                <input type="date" id="invoice_due_date" name="invoice[due_date]" value="<?= e($invoice['dueDate']) ?>"<?= describedBy($errors, 'invoice_due_date') ?>>
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
                                        <label class="sr-only" for="item_<?= (int)$i ?>_description">Item <?= (int)$i + 1 ?> description</label>
                                        <input type="text" id="item_<?= (int)$i ?>_description" name="items[<?= (int)$i ?>][description]" placeholder="e.g. Website Development" value="<?= e($item['description'] ?? '') ?>"<?= describedBy($errors, "item_{$i}_description") ?>>
                                        <?= fieldError($errors, "item_{$i}_description") ?>
                                    </div>
                                    <div class="field <?= isset($errors["item_{$i}_quantity"]) ? 'has-error' : '' ?>">
                                        <label class="sr-only" for="item_<?= (int)$i ?>_quantity">Item <?= (int)$i + 1 ?> quantity</label>
                                        <input type="number" step="0.001" id="item_<?= (int)$i ?>_quantity" name="items[<?= (int)$i ?>][quantity]" placeholder="1" value="<?= e((string)($item['quantity'] ?? '')) ?>"<?= describedBy($errors, "item_{$i}_quantity") ?>>
                                        <?= fieldError($errors, "item_{$i}_quantity") ?>
                                    </div>
                                    <div class="field <?= isset($errors["item_{$i}_unitPrice"]) ? 'has-error' : '' ?>">
                                        <label class="sr-only" for="item_<?= (int)$i ?>_unitPrice">Item <?= (int)$i + 1 ?> unit price</label>
                                        <input type="number" step="0.01" id="item_<?= (int)$i ?>_unitPrice" name="items[<?= (int)$i ?>][unitPrice]" placeholder="0.00" value="<?= e((string)($item['unitPrice'] ?? '')) ?>"<?= describedBy($errors, "item_{$i}_unitPrice") ?>>
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
                                <select id="discount_type" name="settings[discount_type]"<?= describedBy($errors, 'discount_type') ?>>
                                    <option value="percentage" <?= $settings['discount_type'] === 'percentage' ? 'selected' : '' ?>>Percentage</option>
                                    <option value="fixed" <?= $settings['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed Amount</option>
                                </select>
                                <?= fieldError($errors, 'discount_type') ?>
                            </div>
                            <div class="field <?= isset($errors['discount_value']) ? 'has-error' : '' ?>">
                                <label for="discount_value">Discount Value</label>
                                <input type="number" step="0.01" id="discount_value" name="settings[discount_value]" value="<?= e((string)$settings['discount_value']) ?>"<?= describedBy($errors, 'discount_value') ?>>
                                <?= fieldError($errors, 'discount_value') ?>
                            </div>
                            <div class="field <?= isset($errors['tax_percent']) ? 'has-error' : '' ?>">
                                <label for="tax_percent">Tax %</label>
                                <input type="number" step="0.01" id="tax_percent" name="settings[tax_percent]" value="<?= e((string)$settings['tax_percent']) ?>"<?= describedBy($errors, 'tax_percent') ?>>
                                <?= fieldError($errors, 'tax_percent') ?>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="field-group">
                        <legend>Notes &amp; Terms</legend>
                        <div class="field">
                            <label for="notes">Notes</label>
                            <textarea id="notes" name="invoice[notes]" rows="2"><?= e($invoice['notes']) ?></textarea>
                        </div>
                        <div class="field">
                            <label for="payment_terms">Payment Terms</label>
                            <textarea id="payment_terms" name="invoice[payment_terms]" rows="2"><?= e($invoice['paymentTerms']) ?></textarea>
                        </div>
                        <div class="field">
                            <label for="terms_conditions">Terms &amp; Conditions</label>
                            <textarea id="terms_conditions" name="invoice[terms_conditions]" rows="2"><?= e($invoice['termsConditions']) ?></textarea>
                        </div>
                    </fieldset>

                    <div class="form-actions">
                        <button type="submit" name="action" value="update" class="btn btn--block">Update Preview</button>
                        <button type="submit" name="action" value="generate" class="btn btn--primary btn--block">Generate Invoice</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel" aria-label="Invoice preview">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Preview</h2>
                <button type="button" class="btn btn--sm no-print" onclick="window.print()">Print Invoice</button>
            </div>
            <div class="panel__body">
                <?php if ($generatedNumber !== null): ?>
                    <div class="alert alert--success" role="status">
                        Invoice <strong><?= e($generatedNumber) ?></strong> generated and saved.
                    </div>
                <?php endif; ?>

                <div class="paper">
                    <div class="paper__header-row">
                        <div>
                            <div class="paper__brand">VOID<span>BILL</span></div>
                            <div class="paper__tagline"><?= e($tagline) ?></div>
                        </div>
                        <?php if ($generatedNumber !== null): ?>
                            <div class="paper__number"><?= e($generatedNumber) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="paper__dates">
                        <div>
                            <div class="paper__label">Invoice Date</div>
                            <?= e(formatDisplayDate($invoice['date'])) ?>
                        </div>
                        <div>
                            <div class="paper__label">Due Date</div>
                            <?= e(formatDisplayDate($invoice['dueDate'])) ?>
                        </div>
                        <div>
                            <div class="paper__label">Status</div>
                            <span class="badge <?= statusBadgeClass($invoice['status']) ?>"><?= e($invoice['status']) ?></span>
                        </div>
                    </div>

                    <div class="paper__parties">
                        <div>
                            <div class="paper__label">From</div>
                            <strong><?= e($business['name']) ?></strong>
                            <?php if ($business['owner'] !== $business['name']): ?>
                                <div><?= e($business['owner']) ?></div>
                            <?php endif; ?>
                            <?php if ($business['email'] !== ''): ?><div><?= e($business['email']) ?></div><?php endif; ?>
                            <?php if ($business['phone'] !== ''): ?><div><?= e($business['phone']) ?></div><?php endif; ?>
                            <?php if ($business['address'] !== ''): ?><div><?= e($business['address']) ?></div><?php endif; ?>
                            <?php if ($business['website'] !== ''): ?><div><?= e($business['website']) ?></div><?php endif; ?>
                        </div>
                        <div>
                            <div class="paper__label">Bill To</div>
                            <strong><?= $invoice['customer']['name'] !== '' ? e($invoice['customer']['name']) : 'Your customer' ?></strong>
                            <?php if ($invoice['customer']['company'] !== ''): ?><div><?= e($invoice['customer']['company']) ?></div><?php endif; ?>
                            <?php if ($invoice['customer']['email'] !== ''): ?><div><?= e($invoice['customer']['email']) ?></div><?php endif; ?>
                            <?php if ($invoice['customer']['phone'] !== ''): ?><div><?= e($invoice['customer']['phone']) ?></div><?php endif; ?>
                            <?php if ($invoice['customer']['address'] !== ''): ?><div><?= e($invoice['customer']['address']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <p class="paper__meta"><?= e($itemsMessage) ?></p>

                    <?php if ($hasErrors): ?>
                        <p class="paper__meta paper__meta--error">Fix the highlighted fields to see accurate totals.</p>
                    <?php else: ?>
                        <?php
                        // --- foreach: render one read-only row per item -----------------
                        // A different job from Phase 3's foreach (which rendered editable
                        // <input> fields in the builder). This one is read-only, skips
                        // any still-blank row with continue, and calls
                        // calculateLineTotal() again per row — the same function
                        // calculateSubtotal() already used internally, reused here to
                        // display each row's own total rather than just the sum.
                        $hasRenderableItems = false;
                        foreach ($items as $item) {
                            $description = trim((string)($item['description'] ?? ''));
                            $quantity    = $item['quantity'] ?? '';
                            $unitPrice   = $item['unitPrice'] ?? '';
                            if ($description === '' && $quantity === '' && $unitPrice === '') {
                                continue;
                            }
                            $hasRenderableItems = true;
                            break;
                        }
                        ?>

                        <?php if ($hasRenderableItems): ?>
                            <div class="table-scroll">
                                <table class="paper-items">
                                    <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th class="num">Qty</th>
                                        <th class="num">Unit Price</th>
                                        <th class="num">Total</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($items as $item):
                                        $description = trim((string)($item['description'] ?? ''));
                                        $quantity    = $item['quantity'] ?? '';
                                        $unitPrice   = $item['unitPrice'] ?? '';

                                        if ($description === '' && $quantity === '' && $unitPrice === '') {
                                            continue; // an unfilled "+ Add Item" row — nothing to render yet
                                        }

                                        $lineTotal = calculateLineTotal((float)$quantity, (float)$unitPrice);
                                        ?>
                                        <tr>
                                            <td><?= e($description) ?></td>
                                            <td class="num"><?= e((string)$quantity) ?></td>
                                            <td class="num"><?= e(formatCurrency((float)$unitPrice, $currencySymbol)) ?></td>
                                            <td class="num"><?= e(formatCurrency($lineTotal, $currencySymbol)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="paper__meta">No invoice items yet.</p>
                        <?php endif; ?>

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

                        <?php if ($invoice['notes'] !== '' || $invoice['paymentTerms'] !== '' || $invoice['termsConditions'] !== ''): ?>
                            <div class="paper__footer">
                                <?php if ($invoice['notes'] !== ''): ?>
                                    <div>
                                        <div class="paper__label">Notes</div>
                                        <div><?= nl2br(e($invoice['notes'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($invoice['paymentTerms'] !== ''): ?>
                                    <div>
                                        <div class="paper__label">Payment Terms</div>
                                        <div><?= nl2br(e($invoice['paymentTerms'])) ?></div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($invoice['termsConditions'] !== ''): ?>
                                    <div>
                                        <div class="paper__label">Terms &amp; Conditions</div>
                                        <div><?= nl2br(e($invoice['termsConditions'])) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <p class="footer-note no-print">Phase 13 of 15 — testing.</p>
</main>

<div class="toast-region no-print" id="toast-region" aria-live="polite"></div>

<script src="assets/js/app.js"></script>
</body>
</html>
