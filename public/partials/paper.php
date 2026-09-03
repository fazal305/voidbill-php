<?php
/**
 * Renders the printable invoice document.
 * @var array $invoice
 * @var array $business
 */
$statusClass = strtolower($invoice['status'] ?? 'draft');
$symbol = $business['currency_symbol'] ?? 'Rs.';
$customer = $invoice['customer'] ?? [];
?>
<div class="paper" id="invoice-paper">
    <div class="paper__header">
        <div>
            <div class="paper__brand">VOID<span>BILL</span></div>
            <div class="paper__tagline"><?= e($business['title'] ?? 'PHP Invoice Engine') ?></div>
        </div>
        <div class="paper__meta">
            <div class="paper__invoice-number"><?= e($invoice['number']) ?></div>
            <div><?= e(date('d F Y', strtotime($invoice['date']))) ?></div>
            <span class="paper__status status--<?= e($statusClass) ?>"><?= e($invoice['status']) ?></span>
        </div>
    </div>

    <div class="paper__parties">
        <div class="paper__party">
            <div class="paper__label">From</div>
            <strong><?= e($business['name'] ?? '') ?></strong>
            <?php if (!empty($business['owner']) && $business['owner'] !== $business['name']): ?>
                <span><?= e($business['owner']) ?></span>
            <?php endif; ?>
            <?php if (!empty($business['email'])): ?><span><?= e($business['email']) ?></span><?php endif; ?>
            <?php if (!empty($business['phone'])): ?><span><?= e($business['phone']) ?></span><?php endif; ?>
            <?php if (!empty($business['address'])): ?><span><?= e($business['address']) ?></span><?php endif; ?>
        </div>
        <div class="paper__party">
            <div class="paper__label">Bill To</div>
            <strong><?= e($customer['name'] ?? '') ?></strong>
            <?php if (!empty($customer['company'])): ?><span><?= e($customer['company']) ?></span><?php endif; ?>
            <?php if (!empty($customer['email'])): ?><span><?= e($customer['email']) ?></span><?php endif; ?>
            <?php if (!empty($customer['phone'])): ?><span><?= e($customer['phone']) ?></span><?php endif; ?>
            <?php if (!empty($customer['address'])): ?><span><?= e($customer['address']) ?></span><?php endif; ?>
        </div>
    </div>

    <table>
        <thead>
        <tr>
            <th>Description</th>
            <th class="num">Qty</th>
            <th class="num">Price</th>
            <th class="num">Total</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($invoice['items'] as $item): ?>
            <tr>
                <td><?= e($item['description']) ?></td>
                <td class="num"><?= e(rtrim(rtrim(number_format((float)$item['quantity'], 3, '.', ''), '0'), '.')) ?></td>
                <td class="num"><?= e(number_format((float)$item['unit_price'], 2)) ?></td>
                <td class="num"><?= e(number_format((float)$item['line_total'], 2)) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="paper__totals">
        <div class="paper__totals-row">
            <span>Subtotal</span>
            <span><?= e(money((float)$invoice['subtotal'], $symbol)) ?></span>
        </div>
        <?php if ((float)$invoice['discount_amount'] > 0): ?>
        <div class="paper__totals-row">
            <span>Discount<?= $invoice['discount_type'] === 'percent' ? ' (' . e(rtrim(rtrim(number_format((float)$invoice['discount_value'], 2), '0'), '.')) . '%)' : '' ?></span>
            <span>- <?= e(money((float)$invoice['discount_amount'], $symbol)) ?></span>
        </div>
        <?php endif; ?>
        <?php if ((float)$invoice['tax_amount'] > 0): ?>
        <div class="paper__totals-row">
            <span>Tax (<?= e(rtrim(rtrim(number_format((float)$invoice['tax_percent'], 2), '0'), '.')) ?>%)</span>
            <span>+ <?= e(money((float)$invoice['tax_amount'], $symbol)) ?></span>
        </div>
        <?php endif; ?>
        <div class="paper__totals-row paper__totals-row--grand">
            <span>Total</span>
            <span><?= e(money((float)$invoice['total'], $symbol)) ?></span>
        </div>
    </div>

    <?php if (!empty($invoice['notes']) || !empty($invoice['payment_terms']) || !empty($invoice['terms_conditions'])): ?>
    <div class="paper__footer">
        <?php if (!empty($invoice['notes'])): ?>
            <div>
                <div class="paper__label">Notes</div>
                <div><?= nl2br(e($invoice['notes'])) ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($invoice['payment_terms'])): ?>
            <div>
                <div class="paper__label">Payment Terms</div>
                <div><?= nl2br(e($invoice['payment_terms'])) ?></div>
            </div>
        <?php endif; ?>
        <?php if (!empty($invoice['terms_conditions'])): ?>
            <div>
                <div class="paper__label">Terms &amp; Conditions</div>
                <div><?= nl2br(e($invoice['terms_conditions'])) ?></div>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="paper__thanks">Thank you for your business.</div>
</div>
