<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$activePage = 'invoice';
$pageTitle = 'New Invoice';
$errors = [];
$old = [];
$generatedInvoice = null;
$showEmptyFormItem = true;

$numberGenerator = new InvoiceNumberGenerator($config['storage']['counter_file'], $business['invoice_prefix'] ?? 'INV');

// ---- PRG: show a just-generated invoice ----
if (isset($_GET['generated'])) {
    $number = (string)$_GET['generated'];
    $generatedInvoice = $storage->findInvoice($number);
    if ($generatedInvoice === null) {
        flash_set('error', 'That invoice could not be found.');
    }
}

// ---- Handle submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $v = new Validator();

        $old = $_POST;

        $customerName = $v->requireText($_POST, 'customer_name', 'Customer name', 150);
        $customerCompany = $v->optionalText($_POST, 'customer_company', 'Company', 150);
        $customerEmail = $v->optionalEmail($_POST, 'customer_email', 'Customer email');
        $customerPhone = $v->optionalText($_POST, 'customer_phone', 'Phone', 40);
        $customerAddress = $v->optionalText($_POST, 'customer_address', 'Address', 300);

        $invoiceDate = $v->requireDate($_POST, 'invoice_date', 'Invoice date');

        $items = $v->requireItems(
            $_POST['items'] ?? [],
            (int)$config['invoice']['max_items'],
            (float)$config['invoice']['max_quantity'],
            (float)$config['invoice']['max_unit_price']
        );

        $discountType = ($_POST['discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $discountMax = $discountType === 'percent' ? (float)$config['invoice']['max_discount_percent'] : (float)$config['invoice']['max_unit_price'];
        $discountValue = $v->requireNumber($_POST, 'discount_value', 'Discount', 0, $discountMax);

        $taxPercent = $v->requireNumber($_POST, 'tax_percent', 'Tax', 0, (float)$config['invoice']['max_tax_percent']);

        $notes = $v->optionalText($_POST, 'notes', 'Notes', 1000);

        if (!$v->hasErrors()) {
            $result = InvoiceCalculator::calculate($items, $discountType, $discountValue, $taxPercent);
            $number = $numberGenerator->next();

            $invoice = [
                'number' => $number,
                'date' => $invoiceDate,
                'status' => 'GENERATED',
                'customer' => [
                    'name' => $customerName,
                    'company' => $customerCompany,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'address' => $customerAddress,
                ],
                'items' => $result['items'],
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'tax_percent' => $taxPercent,
                'subtotal' => $result['subtotal'],
                'discount_amount' => $result['discount_amount'],
                'taxable_amount' => $result['taxable_amount'],
                'tax_amount' => $result['tax_amount'],
                'total' => $result['total'],
                'notes' => $notes,
                'payment_terms' => $business['payment_terms'] ?? '',
                'terms_conditions' => $business['terms_conditions'] ?? '',
                'created_at' => time(),
            ];

            $storage->saveInvoice($invoice);
            flash_set('success', "Invoice $number generated successfully.");
            header('Location: index.php?generated=' . urlencode($number));
            exit;
        }

        $errors = $v->errors();
        $showEmptyFormItem = false;
    }
}

$flashSuccess = flash_get('success');
$flashError = flash_get('error');

require __DIR__ . '/partials/header.php';
?>

<?php if ($flashSuccess): ?>
    <div class="alert alert--success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert--error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>
<?php if (!empty($errors['form'])): ?>
    <div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="workspace">
    <section class="panel form-panel" aria-label="Invoice details">
        <div class="panel__header">
            <h2 class="panel__title">Invoice Details</h2>
            <button type="button" class="btn btn--ghost btn--sm" id="clear-form-btn">Clear Form</button>
        </div>
        <div class="panel__body">
            <form id="invoice-form" method="post" action="index.php"
                  data-currency-symbol="<?= e($business['currency_symbol'] ?? 'Rs.') ?>"
                  data-has-server-data="<?= !empty($old) ? '1' : '0' ?>" novalidate>
                <?= csrf_field() ?>

                <fieldset class="field-group">
                    <legend>Customer</legend>
                    <div class="field <?= isset($errors['customer_name']) ? 'has-error' : '' ?>">
                        <label for="customer_name">Customer / Client Name *</label>
                        <input type="text" id="customer_name" name="customer_name" value="<?= old($old, 'customer_name') ?>" required maxlength="150" aria-describedby="customer_name-err">
                        <?php if (isset($errors['customer_name'])): ?><p class="field-error" id="customer_name-err"><?= e($errors['customer_name']) ?></p><?php endif; ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="customer_company">Company</label>
                            <input type="text" id="customer_company" name="customer_company" value="<?= old($old, 'customer_company') ?>" maxlength="150">
                        </div>
                        <div class="field <?= isset($errors['customer_email']) ? 'has-error' : '' ?>">
                            <label for="customer_email">Email</label>
                            <input type="email" id="customer_email" name="customer_email" value="<?= old($old, 'customer_email') ?>" maxlength="150" aria-describedby="customer_email-err">
                            <?php if (isset($errors['customer_email'])): ?><p class="field-error" id="customer_email-err"><?= e($errors['customer_email']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="customer_phone">Phone</label>
                            <input type="text" id="customer_phone" name="customer_phone" value="<?= old($old, 'customer_phone') ?>" maxlength="40">
                        </div>
                        <div class="field <?= isset($errors['invoice_date']) ? 'has-error' : '' ?>">
                            <label for="invoice_date">Invoice Date *</label>
                            <input type="date" id="invoice_date" name="invoice_date" value="<?= old($old, 'invoice_date', date('Y-m-d')) ?>" required aria-describedby="invoice_date-err">
                            <?php if (isset($errors['invoice_date'])): ?><p class="field-error" id="invoice_date-err"><?= e($errors['invoice_date']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field">
                        <label for="customer_address">Address</label>
                        <textarea id="customer_address" name="customer_address" maxlength="300"><?= old($old, 'customer_address') ?></textarea>
                    </div>
                </fieldset>

                <fieldset class="field-group">
                    <legend>Items</legend>
                    <?php if (isset($errors['items'])): ?><p class="field-error"><?= e($errors['items']) ?></p><?php endif; ?>
                    <div class="items__head">
                        <span>Description</span><span>Qty</span><span>Unit Price</span><span>Total</span><span></span>
                    </div>
                    <div id="items-container">
                        <?php foreach ($old['items'] ?? [] as $i => $item):
                            $descErr = $errors["items." . ($i + 1) . ".description"] ?? null;
                            $qtyErr = $errors["items." . ($i + 1) . ".quantity"] ?? null;
                            $priceErr = $errors["items." . ($i + 1) . ".unit_price"] ?? null;
                        ?>
                            <div class="item-row">
                                <div class="field <?= $descErr ? 'has-error' : '' ?>">
                                    <input type="text" data-field="description" name="items[<?= (int)$i ?>][description]" placeholder="e.g. Website Development" value="<?= e($item['description'] ?? '') ?>" maxlength="200" aria-describedby="item-<?= (int)$i ?>-desc-err">
                                    <?php if ($descErr): ?><p class="field-error" id="item-<?= (int)$i ?>-desc-err"><?= e($descErr) ?></p><?php endif; ?>
                                </div>
                                <div class="field <?= $qtyErr ? 'has-error' : '' ?>">
                                    <input type="number" data-field="quantity" name="items[<?= (int)$i ?>][quantity]" placeholder="1" step="0.001" min="0" value="<?= e($item['quantity'] ?? '') ?>" aria-describedby="item-<?= (int)$i ?>-qty-err">
                                    <?php if ($qtyErr): ?><p class="field-error" id="item-<?= (int)$i ?>-qty-err"><?= e($qtyErr) ?></p><?php endif; ?>
                                </div>
                                <div class="field <?= $priceErr ? 'has-error' : '' ?>">
                                    <input type="number" data-field="unit_price" name="items[<?= (int)$i ?>][unit_price]" placeholder="0.00" step="0.01" min="0" value="<?= e($item['unit_price'] ?? '') ?>" aria-describedby="item-<?= (int)$i ?>-price-err">
                                    <?php if ($priceErr): ?><p class="field-error" id="item-<?= (int)$i ?>-price-err"><?= e($priceErr) ?></p><?php endif; ?>
                                </div>
                                <div class="item-row__total">—</div>
                                <button type="button" class="item-row__remove" aria-label="Remove item <?= (int)$i + 1 ?>">&times;</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="empty-items" id="items-empty-state" <?= !empty($old['items']) ? 'hidden' : '' ?>>No invoice items yet. Add your first product or service to begin.</p>
                    <button type="button" class="btn btn--sm" id="add-item-btn">+ Add Item</button>
                </fieldset>

                <fieldset class="field-group">
                    <legend>Discount &amp; Tax</legend>
                    <div class="field-row">
                        <div class="field">
                            <label>Discount Type</label>
                            <div class="chip-toggle" style="display:flex;gap:8px;">
                                <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;color:var(--color-text);">
                                    <input type="radio" name="discount_type" value="percent" <?= ($old['discount_type'] ?? 'percent') === 'percent' ? 'checked' : '' ?> style="width:auto;"> Percentage
                                </label>
                                <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;color:var(--color-text);">
                                    <input type="radio" name="discount_type" value="fixed" <?= ($old['discount_type'] ?? '') === 'fixed' ? 'checked' : '' ?> style="width:auto;"> Fixed Amount
                                </label>
                            </div>
                        </div>
                        <div class="field <?= isset($errors['discount_value']) ? 'has-error' : '' ?>">
                            <label for="discount_value">Discount Value</label>
                            <input type="number" id="discount_value" name="discount_value" step="0.01" min="0" value="<?= old($old, 'discount_value', '0') ?>" aria-describedby="discount_value-err">
                            <?php if (isset($errors['discount_value'])): ?><p class="field-error" id="discount_value-err"><?= e($errors['discount_value']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field <?= isset($errors['tax_percent']) ? 'has-error' : '' ?>">
                        <label for="tax_percent">Tax %</label>
                        <input type="number" id="tax_percent" name="tax_percent" step="0.01" min="0" max="100" value="<?= old($old, 'tax_percent', '0') ?>" aria-describedby="tax_percent-err">
                        <?php if (isset($errors['tax_percent'])): ?><p class="field-error" id="tax_percent-err"><?= e($errors['tax_percent']) ?></p><?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="field-group">
                    <legend>Notes</legend>
                    <div class="field">
                        <label for="notes">Notes (optional)</label>
                        <textarea id="notes" name="notes" maxlength="1000" placeholder="Anything the client should know..."><?= old($old, 'notes') ?></textarea>
                        <span class="hint">Payment terms and terms &amp; conditions come from your <a href="settings.php">business settings</a>.</span>
                    </div>
                </fieldset>

                <button type="submit" class="btn btn--primary btn--block" id="generate-btn">
                    <span class="spinner" aria-hidden="true"></span>
                    <span class="btn__label">Generate Invoice</span>
                </button>
            </form>
        </div>
    </section>

    <section class="panel panel--sticky" aria-label="Invoice preview">
        <div class="panel__header">
            <h2 class="panel__title"><?= $generatedInvoice ? 'Generated Invoice' : 'Live Preview' ?></h2>
            <?php if ($generatedInvoice): ?>
                <span class="chip"><span class="dot"></span> Saved</span>
            <?php endif; ?>
        </div>
        <div class="panel__body">
            <?php if ($generatedInvoice): ?>
                <?php $invoice = $generatedInvoice; require __DIR__ . '/partials/paper.php'; ?>
                <div class="preview-actions no-print">
                    <a href="invoice.php?number=<?= urlencode($generatedInvoice['number']) ?>" class="btn btn--sm">View Permalink</a>
                    <button type="button" class="btn btn--sm" onclick="window.print()">Print / Save PDF</button>
                    <a href="index.php" class="btn btn--primary btn--sm">New Invoice</a>
                </div>
            <?php else: ?>
                <div class="paper">
                    <div class="paper__header">
                        <div>
                            <div class="paper__brand">VOID<span>BILL</span></div>
                            <div class="paper__tagline"><?= e($business['title'] ?? 'PHP Invoice Engine') ?></div>
                        </div>
                        <div class="paper__meta">
                            <div class="paper__invoice-number">Draft</div>
                        </div>
                    </div>
                    <div class="paper__parties">
                        <div class="paper__party">
                            <div class="paper__label">From</div>
                            <strong><?= e($business['name'] ?? '') ?></strong>
                        </div>
                        <div class="paper__party">
                            <div class="paper__label">Bill To</div>
                            <strong id="preview-customer-name"><?= old($old, 'customer_name', 'Your customer') ?></strong>
                        </div>
                    </div>
                    <div class="totals">
                        <div class="totals__row"><span>Subtotal</span><span id="preview-subtotal">Rs. 0.00</span></div>
                        <div class="totals__row totals__row--negative"><span>Discount</span><span id="preview-discount">Rs. 0.00</span></div>
                        <div class="totals__row totals__row--positive"><span>Tax</span><span id="preview-tax">Rs. 0.00</span></div>
                        <div class="totals__row totals__row--grand"><span>Total</span><span class="totals__value" id="preview-total">Rs. 0.00</span></div>
                    </div>
                </div>
                <p class="helper-text" style="margin-top:12px;">This preview updates live as you type. PHP performs the authoritative calculation when you generate the invoice.</p>
            <?php endif; ?>
        </div>
    </section>
</div>

<template id="item-row-template">
    <div class="item-row">
        <div class="field">
            <input type="text" data-field="description" placeholder="e.g. Website Development" maxlength="200">
        </div>
        <div class="field">
            <input type="number" data-field="quantity" placeholder="1" step="0.001" min="0">
        </div>
        <div class="field">
            <input type="number" data-field="unit_price" placeholder="0.00" step="0.01" min="0">
        </div>
        <div class="item-row__total">—</div>
        <button type="button" class="item-row__remove" aria-label="Remove item">&times;</button>
    </div>
</template>

<?php require __DIR__ . '/partials/footer.php'; ?>
