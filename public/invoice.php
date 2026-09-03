<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$activePage = 'dashboard';
$number = (string)($_GET['number'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    if (csrf_verify($_POST['csrf_token'] ?? null)) {
        $allowed = ['DRAFT', 'GENERATED', 'SENT', 'PAID', 'OVERDUE'];
        $newStatus = (string)$_POST['status'];
        if (in_array($newStatus, $allowed, true)) {
            $storage->updateInvoiceStatus($number, $newStatus);
            flash_set('success', "Status updated to $newStatus.");
        }
    }
    header('Location: invoice.php?number=' . urlencode($number));
    exit;
}

$invoice = $number !== '' ? $storage->findInvoice($number) : null;
$pageTitle = $invoice ? $invoice['number'] : 'Invoice not found';
$flashSuccess = flash_get('success');

require __DIR__ . '/partials/header.php';
?>

<?php if ($flashSuccess): ?>
    <div class="alert alert--success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>

<?php if (!$invoice): ?>
    <div class="panel">
        <div class="panel__body">
            <p class="empty-items">Invoice <?= e($number) ?> could not be found. <a href="dashboard.php">Back to dashboard</a>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="page-header no-print">
        <div>
            <h1><?= e($invoice['number']) ?></h1>
            <p>Generated <?= e(date('d F Y, H:i', $invoice['created_at'] ?? time())) ?></p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <form method="post" action="invoice.php?number=<?= urlencode($invoice['number']) ?>" style="display:flex; gap:8px; align-items:center;">
                <?= csrf_field() ?>
                <label for="status" class="hint" style="margin:0;">Status</label>
                <select id="status" name="status" onchange="this.form.requestSubmit()">
                    <?php foreach (['DRAFT', 'GENERATED', 'SENT', 'PAID', 'OVERDUE'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $invoice['status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button type="button" class="btn btn--primary btn--sm" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <?php require __DIR__ . '/partials/paper.php'; ?>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
