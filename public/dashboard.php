<?php
/**
 * VOIDBILL — Phase 10: dashboard / invoice history.
 *
 * Reads every invoice ever generated (loadInvoices(), from Phase 7's
 * persistence layer) and summarizes it: how many, how much, how much
 * is paid vs still outstanding, and the most recent ones. Nothing here
 * writes anything — this page is pure reporting.
 */

declare(strict_types=1);

require __DIR__ . '/../src/calculations.php';
require __DIR__ . '/../src/persistence.php';

$config = require __DIR__ . '/../config/config.php';

$appName        = $config['app_name'];
$tagline        = $config['app_tagline'];
$isDev          = $config['env'] === 'development';
$phpVersion     = phpversion();

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
$currencySymbol = $config['currency_symbol'];

$invoices = loadInvoices($config['storage']['invoices_file']);

// --- Totals, built with foreach + conditionals (array filtering "by hand") ---
// Rather than array_filter()+array_sum(), this stays consistent with how
// the rest of the app aggregates data — a plain accumulator loop that's
// easy to read as "for every invoice, add to a running total, and also
// add to the paid total if it's marked PAID".
$totalInvoices    = count($invoices);
$totalValue       = 0.0;
$paidValue        = 0.0;

foreach ($invoices as $invoice) {
    $grandTotal = (float)($invoice['grandTotal'] ?? 0);
    $totalValue += $grandTotal;

    if ($invoice['status'] === 'PAID') {
        $paidValue += $grandTotal;
    }
}

$outstandingValue = $totalValue - $paidValue;

// --- Sorting: usort(), not sort()/rsort() -----------------------------------
// sort() and rsort() compare whole array elements — fine for a flat list
// of numbers, but not for an array of invoice records where we need to
// sort BY one field (generatedAt) rather than by the records themselves.
// usort() with a comparison callback is the correct tool for that job.
usort($invoices, function (array $a, array $b): int {
    return strtotime($b['generatedAt'] ?? '') <=> strtotime($a['generatedAt'] ?? '');
});

// --- for: a numeric loop capped at a fixed count ----------------------------
// A genuine case for `for` rather than `foreach` — this isn't "do
// something with every invoice", it's "collect up to 5, by index",
// which a counted loop expresses more directly.
$recentInvoices = [];
for ($i = 0; $i < count($invoices) && $i < 5; $i++) {
    $recentInvoices[] = $invoices[$i];
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($appName) ?> — Dashboard</title>
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
    <nav class="topnav">
        <a href="index.php">New Invoice</a>
        <a href="dashboard.php" aria-current="page">Dashboard</a>
    </nav>
    <?php if ($isDev): ?>
        <span class="env-badge">PHP <?= e($phpVersion) ?> · dev</span>
    <?php endif; ?>
</header>

<main id="main" class="shell">
    <div class="page-header">
        <div>
            <h1>Dashboard</h1>
            <p>Every invoice VOIDBILL has generated on this machine.</p>
        </div>
        <a href="index.php" class="btn btn--primary btn--sm">+ New Invoice</a>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-card__label">Total Invoices</div>
            <div class="stat-card__value"><?= (int)$totalInvoices ?></div>
        </div>
        <div class="stat-card stat-card--accent">
            <div class="stat-card__label">Total Value</div>
            <div class="stat-card__value"><?= e(formatCurrency($totalValue, $currencySymbol)) ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card__label">Paid</div>
            <div class="stat-card__value"><?= e(formatCurrency($paidValue, $currencySymbol)) ?></div>
        </div>
        <div class="stat-card stat-card--danger">
            <div class="stat-card__label">Outstanding</div>
            <div class="stat-card__value"><?= e(formatCurrency($outstandingValue, $currencySymbol)) ?></div>
        </div>
    </div>

    <section class="panel" aria-label="Recent invoices">
        <div class="panel__header">
            <h2 class="panel__title">Recent Invoices</h2>
        </div>
        <div class="panel__body">
            <?php if ($totalInvoices === 0): ?>
                <p class="empty-state">No invoices yet. <a href="index.php">Generate your first invoice</a> to see it here.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table class="data">
                        <thead>
                        <tr>
                            <th>Number</th>
                            <th>Customer</th>
                            <th>Date</th>
                            <th class="num">Total</th>
                            <th>Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recentInvoices as $invoice): ?>
                            <tr>
                                <td><?= e($invoice['number'] ?? '') ?></td>
                                <td><?= e($invoice['customer']['name'] ?? '') ?></td>
                                <td><?= e(date('d M Y', strtotime($invoice['date'] ?? 'now'))) ?></td>
                                <td class="num"><?= e(formatCurrency((float)($invoice['grandTotal'] ?? 0), $currencySymbol)) ?></td>
                                <td><span class="badge <?= statusBadgeClass($invoice['status'] ?? 'DRAFT') ?>"><?= e($invoice['status'] ?? '') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalInvoices > 5): ?>
                    <p class="paper__meta">Showing the 5 most recent of <?= (int)$totalInvoices ?> invoices.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <p class="footer-note">VOIDBILL — all 15 phases complete.</p>
</main>
</body>
</html>
