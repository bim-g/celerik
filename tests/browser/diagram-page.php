<?php

/**
 * Diagrams, included by server.php (GET /diagram?mode=…).
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Diagram;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Diagram (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Diagram</h1>
    <div class="row g-4">
        <div class="col-md-6">
            <?= Diagram::make('signup')->label('Sign-up')
                ->node('visit', 'Visit the site', 'terminal')
                ->node('form', 'Fill in the sign-up form')
                ->node('valid', 'Is the form valid?', 'decision')
                ->node('mail', 'Send a confirmation e-mail', color: 'info')
                ->node('done', 'Account created', 'terminal', 'success')
                ->edge('visit', 'form')
                ->edge('form', 'valid')
                ->edge('valid', 'mail', 'Yes')
                ->edge('valid', 'form', 'No')
                ->edge('mail', 'done') ?>
        </div>
        <div class="col-12">
            <?= Diagram::make('order')->label('Order <processing>')->direction('right')
                ->node('paid', 'Order paid', 'terminal', 'primary')
                ->node('stock', 'In stock?', 'decision')
                ->node('ship', 'Ship it')
                ->node('wait', 'Order from the supplier', color: 'warning')
                ->node('end', 'Delivered', 'terminal', 'success')
                ->edge('paid', 'stock')
                ->edge('stock', 'ship', 'Yes')
                ->edge('stock', 'wait', 'No')
                ->edge('wait', 'ship')
                ->edge('ship', 'end') ?>
        </div>
    </div>
</main>
</body>
</html>
