<?php

/**
 * PDF viewers, included by server.php (GET /pdf?mode=…; the document is GET /files/report.pdf).
 * The page has a strict Content Security Policy: no inline scripts, styles or eval.
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\PdfViewer;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; worker-src 'self' blob:; font-src 'self' data:">
    <link rel="icon" href="data:,">
    <title>PDF viewer (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>PDF viewer</h1>
    <?= PdfViewer::make('report')->url('/files/report.pdf')->label('Annual report 2026') ?>
    <?= PdfViewer::make('missing')->url('/files/missing.pdf')->label('A missing document')->size('sm') ?>
</main>
</body>
</html>
