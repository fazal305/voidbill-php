<?php
/**
 * VOIDBILL — Phase 1: project foundation only.
 *
 * No invoice form yet (that's Phase 3), no calculation engine yet
 * (Phase 4). This page just proves out the folder structure, the config
 * file, and the two-panel application shell the rest of the app will be
 * built inside of.
 */

declare(strict_types=1);

// --- Variables & data types ---------------------------------------------
// $config is an associative array (string keys => values) loaded from a
// plain PHP file — this is VOIDBILL's whole "configuration system".
$config = require __DIR__ . '/../config/config.php';

$appName    = $config['app_name'];     // string
$tagline    = $config['app_tagline'];  // string
$isDev      = $config['env'] === 'development'; // bool, via a comparison operator
$phpVersion = phpversion();            // string, e.g. "8.4.24"

// --- Arrays --------------------------------------------------------------
// The line-item list will become a real, populated array in Phase 2. For
// now it's an empty indexed array, which is enough to demonstrate count()
// and a basic conditional against real (if currently empty) data rather
// than a hardcoded example.
$items = [];
$itemCount = count($items);

// --- Basic conditional ----------------------------------------------------
// A plain if/else, not a contrived one: whether the builder panel shows
// placeholder text or real items depends entirely on $itemCount.
if ($itemCount === 0) {
    $itemsMessage = 'No invoice items yet. Line items arrive in Phase 2.';
} else {
    $itemsMessage = $itemCount . ' item(s) loaded.';
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
                <p class="empty-state"><?= htmlspecialchars($itemsMessage, ENT_QUOTES, 'UTF-8') ?></p>
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
                </div>
            </div>
        </section>
    </div>

    <p class="footer-note">Phase 1 of 15 — foundation only. No form, no calculations yet.</p>
</main>
</body>
</html>
