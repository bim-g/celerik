<?php

/**
 * QR codes and barcodes, included by server.php (GET /codes?mode=…).
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Barcode;
use Caelrik\Ui\Components\QrCode;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>QR codes and barcodes (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>QR codes and barcodes</h1>
    <div class="d-flex flex-wrap gap-4">
        <?= QrCode::make('site')->value('https://example.com/a-rather-long-address/that-wraps?under=the-code')
            ->label('Our website') ?>
        <?= QrCode::make('wifi')->value('WIFI:T:WPA;S:Office;P:correct horse battery staple;;')->label('Office Wi-Fi')
            ->hideValue()->level('Q') ?>
        <?= QrCode::make('phone')->value('tel:+243999999999')->size(120) ?>
    </div>
    <div class="d-flex flex-wrap gap-4">
        <?= Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN') ?>
        <?= Barcode::make('order')->value('ORDER-2026-000042')->label('Order number') ?>
    </div>
</main>
</body>
</html>
