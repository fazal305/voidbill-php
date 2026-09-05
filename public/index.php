<?php
/**
 * VOIDBILL — Phase 2: the invoice data structure.
 *
 * Still no form (Phase 3) and no calculation engine (Phase 4) — this
 * page hardcodes one example invoice so the shape of the data can be
 * seen clearly before anything gets built on top of it. Phase 3 will
 * replace this hardcoded array with data read from $_POST; the shape
 * stays the same.
 */

declare(strict_types=1);

// --- Variables & data types ---------------------------------------------
$config = require __DIR__ . '/../config/config.php';

$appName    = $config['app_name'];
$tagline    = $config['app_tagline'];
$isDev      = $config['env'] === 'development';
$phpVersion = phpversion();

// --- Associative arrays ---------------------------------------------------
// Each of these groups related fields under descriptive string keys.
// "$business['name']" reads far better than a bag of loose variables
// like $businessName, $businessEmail, $businessPhone, etc.
$business = [
    'name'    => 'Fazal Abbas',
    'owner'   => 'Fazal Abbas',
    'email'   => 'fazalabbas2002@gmail.com',
    'phone'   => '+92 300 1234567',
    'address' => 'Karachi, Pakistan',
];

$customer = [
    'name'    => 'Ahmed Traders',
    'company' => 'Ahmed Traders Pvt Ltd',
    'email'   => 'ahmed@traders.pk',
    'phone'   => '',
    'address' => '',
];

// --- Multidimensional array ------------------------------------------------
// $items is an indexed array (0, 1, 2, ...) where every element is itself
// an associative array. This is the shape line items will keep for the
// rest of the project — Phase 4 reads 'quantity' and 'unitPrice' out of
// each row to calculate a line total.
$items = [
    [
        'description' => 'Website Development',
        'quantity'    => 1,
        'unitPrice'   => 150000,
    ],
    [
        'description' => 'Hosting',
        'quantity'    => 1,
        'unitPrice'   => 25000,
    ],
    [
        'description' => 'Maintenance',
        'quantity'    => 6,
        'unitPrice'   => 10000,
    ],
];

// Default invoice-level settings — discount type/value and tax percent
// live here rather than as loose variables, because Phase 4's
// calculation functions will take a $settings array as one argument
// instead of three or four separate ones.
$settings = [
    'currencySymbol' => $config['currency_symbol'],
    'discountType'   => 'percentage', // or 'fixed' — used by Phase 4's switch
    'discountValue'  => 10,
    'taxPercent'     => 5,
];

// The invoice itself nests $customer and $items inside one associative
// array — a multidimensional structure that mirrors how the real form
// data will be assembled in Phase 3.
$invoice = [
    'number'   => null, // assigned in Phase 7
    'date'     => date('Y-m-d'),
    'status'   => 'DRAFT',
    'customer' => $customer,
    'items'    => $items,
    'notes'    => '',
];

// --- count() ---------------------------------------------------------------
$itemCount = count($invoice['items']);

// --- Basic conditional ----------------------------------------------------
if ($itemCount === 0) {
    $itemsMessage = 'No invoice items yet. Add your first item to begin.';
} else {
    $itemsMessage = $itemCount . ' line item(s) in this invoice.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></title>
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
    <span class="topbar__tagline"><?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></span>
    <?php if ($isDev): ?>
        <span class="env-badge">PHP <?= htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') ?> · dev</span>
    <?php endif; ?>
</header>

<main id="main" class="shell">
    <div class="workspace">
        <section class="panel" aria-label="Invoice builder">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Builder</h2>
            </div>
            <div class="panel__body">
                <p class="empty-state">
                    No form yet — this is hardcoded example data (Phase 3 adds the real form).<br>
                    <?= htmlspecialchars($itemsMessage, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </div>
        </section>

        <section class="panel" aria-label="Invoice preview">
            <div class="panel__header">
                <h2 class="panel__title">Invoice Preview</h2>
            </div>
            <div class="panel__body">
                <div class="paper">
                    <div class="paper__brand">VOID<span>BILL</span></div>
                    <div class="paper__tagline"><?= htmlspecialchars($tagline, ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="paper__parties">
                        <div>
                            <div class="paper__label">From</div>
                            <strong><?= htmlspecialchars($business['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                        </div>
                        <div>
                            <div class="paper__label">Bill To</div>
                            <strong><?= htmlspecialchars($invoice['customer']['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <div><?= htmlspecialchars($invoice['customer']['company'], ENT_QUOTES, 'UTF-8') ?></div>
                        </div>
                    </div>

                    <p class="paper__meta">
                        Status: <strong><?= htmlspecialchars($invoice['status'], ENT_QUOTES, 'UTF-8') ?></strong>
                        &middot; <?= htmlspecialchars($itemsMessage, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <p class="paper__meta">Itemized rows and totals arrive in Phases 4 and 6.</p>
                </div>
            </div>
        </section>
    </div>

    <p class="footer-note">Phase 2 of 15 — invoice data structure. No form, no calculations yet.</p>
</main>
</body>
</html>
