<?php
/**
 * @var array $config
 * @var string $activePage
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle ?? $config['app_name']) ?> — VOIDBILL</title>
<meta name="description" content="VOIDBILL — a professional PHP invoice generator.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/variables.css">
<link rel="stylesheet" href="assets/css/app.css">
<link rel="stylesheet" href="assets/css/print.css" media="print">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2220%22 fill=%22%230d0f12%22/><text x=%2250%22 y=%2266%22 font-size=%2260%22 text-anchor=%22middle%22 fill=%22%234ee1a0%22 font-family=%22monospace%22>V</text></svg>">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar">
    <a href="index.php" class="brand">
        <span class="brand__mark">VOID<span>BILL</span></span>
        <span class="brand__tagline">PHP Invoice Engine</span>
    </a>
    <nav class="topnav" aria-label="Primary">
        <a href="index.php" <?= $activePage === 'invoice' ? 'aria-current="page"' : '' ?>>New Invoice</a>
        <a href="dashboard.php" <?= $activePage === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
        <a href="settings.php" <?= $activePage === 'settings' ? 'aria-current="page"' : '' ?>>Settings</a>
    </nav>
    <button type="button" class="topbar__hint" id="open-palette-btn" aria-label="Open command palette">⌘K</button>
</header>
<main id="main" class="shell">
