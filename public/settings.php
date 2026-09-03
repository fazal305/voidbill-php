<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$activePage = 'settings';
$pageTitle = 'Settings';
$errors = [];
$old = $business;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $v = new Validator();
        $old = array_merge($old, $_POST);

        $name = $v->requireText($_POST, 'name', 'Business name', 150);
        $owner = $v->requireText($_POST, 'owner', 'Owner name', 150);
        $title = $v->optionalText($_POST, 'title', 'Title', 150);
        $email = $v->optionalEmail($_POST, 'email', 'Email');
        $phone = $v->optionalText($_POST, 'phone', 'Phone', 40);
        $address = $v->optionalText($_POST, 'address', 'Address', 300);
        $website = $v->optionalText($_POST, 'website', 'Website', 150);
        $invoicePrefix = $v->requireText($_POST, 'invoice_prefix', 'Invoice prefix', 10);
        $currencySymbol = $v->requireText($_POST, 'currency_symbol', 'Currency symbol', 10);
        $paymentTerms = $v->optionalText($_POST, 'payment_terms', 'Payment terms', 500);
        $termsConditions = $v->optionalText($_POST, 'terms_conditions', 'Terms & conditions', 500);

        $logoPath = $business['logo'] ?? '';

        if (!empty($_FILES['logo']['name'])) {
            $file = $_FILES['logo'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $v->fail('logo', 'Logo upload failed. Please try again.');
            } elseif ($file['size'] > $config['upload']['max_bytes']) {
                $v->fail('logo', 'Logo must be smaller than 2MB.');
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($mime, $config['upload']['allowed_mime'], true) || !in_array($ext, $config['upload']['allowed_ext'], true)) {
                    $v->fail('logo', 'Logo must be a PNG, JPG, or WEBP image.');
                } else {
                    $uploadDir = $config['upload']['dir'];
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0775, true);
                    }
                    $safeName = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $destination = $uploadDir . '/' . $safeName;
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $logoPath = 'uploads/' . $safeName;
                    } else {
                        $v->fail('logo', 'Could not save the uploaded logo.');
                    }
                }
            }
        }

        if (!$v->hasErrors()) {
            $newBusiness = [
                'name' => $name,
                'owner' => $owner,
                'title' => $title,
                'email' => $email,
                'phone' => $phone,
                'address' => $address,
                'website' => $website,
                'logo' => $logoPath,
                'invoice_prefix' => $invoicePrefix,
                'currency_symbol' => $currencySymbol,
                'payment_terms' => $paymentTerms,
                'terms_conditions' => $termsConditions,
            ];
            $storage->saveBusiness($newBusiness);
            flash_set('success', 'Business settings saved.');
            header('Location: settings.php');
            exit;
        }

        $errors = $v->errors();
    }
}

$flashSuccess = flash_get('success');

require __DIR__ . '/partials/header.php';
?>

<div class="page-header">
    <div>
        <h1>Business Settings</h1>
        <p>This information appears on every invoice you generate.</p>
    </div>
</div>

<?php if ($flashSuccess): ?>
    <div class="alert alert--success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if (!empty($errors['form'])): ?>
    <div class="alert alert--error" role="alert"><?= e($errors['form']) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel__body">
        <form method="post" action="settings.php" enctype="multipart/form-data" novalidate>
            <?= csrf_field() ?>
            <div class="settings-grid">
                <fieldset class="field-group">
                    <legend>Business Profile</legend>
                    <div class="field-row">
                        <div class="field <?= isset($errors['name']) ? 'has-error' : '' ?>">
                            <label for="name">Business Name *</label>
                            <input type="text" id="name" name="name" value="<?= old($old, 'name') ?>" required maxlength="150">
                            <?php if (isset($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
                        </div>
                        <div class="field <?= isset($errors['owner']) ? 'has-error' : '' ?>">
                            <label for="owner">Owner Name *</label>
                            <input type="text" id="owner" name="owner" value="<?= old($old, 'owner') ?>" required maxlength="150">
                            <?php if (isset($errors['owner'])): ?><p class="field-error"><?= e($errors['owner']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="title">Title / Role</label>
                            <input type="text" id="title" name="title" value="<?= old($old, 'title') ?>" maxlength="150">
                        </div>
                        <div class="field <?= isset($errors['email']) ? 'has-error' : '' ?>">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?= old($old, 'email') ?>" maxlength="150">
                            <?php if (isset($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="phone">Phone</label>
                            <input type="text" id="phone" name="phone" value="<?= old($old, 'phone') ?>" maxlength="40">
                        </div>
                        <div class="field">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" value="<?= old($old, 'website') ?>" maxlength="150">
                        </div>
                    </div>
                    <div class="field">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" maxlength="300"><?= old($old, 'address') ?></textarea>
                    </div>
                    <div class="field <?= isset($errors['logo']) ? 'has-error' : '' ?>">
                        <label for="logo">Logo</label>
                        <div style="display:flex; align-items:center; gap:12px;">
                            <?php if (!empty($business['logo'])): ?>
                                <img src="<?= e($business['logo']) ?>" alt="Current logo" class="logo-preview">
                            <?php endif; ?>
                            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <span class="hint">PNG, JPG, or WEBP. Max 2MB.</span>
                        <?php if (isset($errors['logo'])): ?><p class="field-error"><?= e($errors['logo']) ?></p><?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="field-group">
                    <legend>Invoice Defaults</legend>
                    <div class="field-row">
                        <div class="field <?= isset($errors['invoice_prefix']) ? 'has-error' : '' ?>">
                            <label for="invoice_prefix">Invoice Prefix</label>
                            <input type="text" id="invoice_prefix" name="invoice_prefix" value="<?= old($old, 'invoice_prefix') ?>" maxlength="10">
                            <span class="hint">e.g. INV — produces INV-2026-0001</span>
                        </div>
                        <div class="field <?= isset($errors['currency_symbol']) ? 'has-error' : '' ?>">
                            <label for="currency_symbol">Currency Symbol</label>
                            <input type="text" id="currency_symbol" name="currency_symbol" value="<?= old($old, 'currency_symbol') ?>" maxlength="10">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="field-group">
                    <legend>Default Notes</legend>
                    <div class="field">
                        <label for="payment_terms">Payment Terms</label>
                        <textarea id="payment_terms" name="payment_terms" maxlength="500"><?= old($old, 'payment_terms') ?></textarea>
                    </div>
                    <div class="field">
                        <label for="terms_conditions">Terms &amp; Conditions</label>
                        <textarea id="terms_conditions" name="terms_conditions" maxlength="500"><?= old($old, 'terms_conditions') ?></textarea>
                    </div>
                </fieldset>
            </div>

            <button type="submit" class="btn btn--primary">Save Settings</button>
        </form>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
