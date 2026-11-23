<?php

/**
 * Gauges and sparklines, included by server.php (GET /gauges?mode=…).
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Gauge;
use Caelrik\Ui\Components\Sparkline;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$bands = static fn (Gauge $gauge): Gauge => $gauge
    ->band(0, 60, 'success', 'Normal')->band(60, 85, 'warning', 'High')->band(85, 100, 'danger', 'Critical');
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Gauges and sparklines (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Gauges and sparklines</h1>
    <div class="d-flex flex-wrap gap-4 align-items-end">
        <?= $bands(Gauge::make('cpu')->label('CPU')->value(72)->format('{value}%')->target(90)) ?>
        <?= $bands(Gauge::make('memory')->label('Memory')->value(41)->format('{value}%')->size('lg')) ?>
        <?= $bands(Gauge::make('disk')->label('Disk')->value(93)->format('{value}%')->size('sm')) ?>
        <?= Gauge::make('speed')->label('Response time')->value(180)->range(0, 500)->format('{value} ms')->color('info') ?>
    </div>
    <div class="row mt-3">
        <div class="col-md-6">
            <?= Gauge::make('quota')->label('Storage used')->value(1250)->range(0, 2000)->format('{value} GB')->linear()
                ->band(0, 1500, 'success', 'OK')->band(1500, 2000, 'danger', 'Almost full')->target(1500) ?>
            <?= Gauge::make('sales')->label('Sales vs goal')->value(84000)->range(0, 120000)->format('${value}')->linear()
                ->target(100000)->size('lg') ?>
        </div>
    </div>
    <table class="table w-auto">
        <caption>Weekly figures</caption>
        <thead><tr><th scope="col">Figure</th><th scope="col">Last 7 weeks</th><th scope="col">Now</th></tr></thead>
        <tbody>
        <tr><th scope="row">Revenue</th>
            <td><?= Sparkline::make('spark-revenue')->label('Revenue')->values([8, 9, 12, 11, 14, 13, 16])->type('area')->markLast() ?></td>
            <td>16</td></tr>
        <tr><th scope="row">Profit</th>
            <td><?= Sparkline::make('spark-profit')->label('Profit')->values([3, -2, 0, 5, 4, -1, 6])->type('bar')->color('success') ?></td>
            <td>6</td></tr>
        <tr><th scope="row">Errors</th>
            <td><?= Sparkline::make('spark-errors')->label('Errors')->values([5, 4, 6, 3, 2, 2, 1])->color('danger')->size('lg') ?></td>
            <td>1</td></tr>
        </tbody>
    </table>
    <p>Visits this week <?= Sparkline::make()->values([120, 140, 90, 160])->size('sm')->decorative() ?> 120, 140, 90, 160.</p>
</main>
</body>
</html>
