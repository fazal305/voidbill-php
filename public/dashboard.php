<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$activePage = 'dashboard';
$pageTitle = 'Dashboard';
$symbol = $business['currency_symbol'] ?? 'Rs.';

$invoices = array_reverse($storage->allInvoices());

$totalInvoices = count($invoices);
$totalValue = 0.0;
$paidValue = 0.0;
$outstandingValue = 0.0;

foreach ($invoices as $inv) {
    $total = (float)($inv['total'] ?? 0);
    $totalValue += $total;
    if (($inv['status'] ?? '') === 'PAID') {
        $paidValue += $total;
    } else {
        $outstandingValue += $total;
    }
}

require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>An overview of every invoice VOIDBILL has generated on this machine.</p>
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
        <div class="stat-card__value"><?= e(money($totalValue, $symbol)) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-card__label">Paid</div>
        <div class="stat-card__value"><?= e(money($paidValue, $symbol)) ?></div>
    </div>
    <div class="stat-card stat-card--danger">
        <div class="stat-card__label">Outstanding</div>
        <div class="stat-card__value"><?= e(money($outstandingValue, $symbol)) ?></div>
    </div>
</div>

<div class="panel">
    <div class="panel__header">
        <h2 class="panel__title">Recent Invoices</h2>
    </div>
    <div class="panel__body">
        <?php if (empty($invoices)): ?>
            <p class="empty-items">No invoices yet. <a href="index.php">Generate your first invoice</a> to see it here.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data">
                    <thead>
                    <tr>
                        <th>Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><a href="invoice.php?number=<?= urlencode($inv['number']) ?>"><?= e($inv['number']) ?></a></td>
                            <td><?= e($inv['customer']['name'] ?? '') ?></td>
                            <td><?= e(date('d M Y', strtotime($inv['date']))) ?></td>
                            <td><?= e(money((float)$inv['total'], $symbol)) ?></td>
                            <td><span class="badge badge--<?= e(strtolower($inv['status'])) ?>"><?= e($inv['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
