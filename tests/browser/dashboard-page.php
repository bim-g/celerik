<?php

/**
 * A dashboard, included by server.php (GET /dashboard?mode=…): stat tiles, then a tile layout
 * holding a chart, a grid and a stat (the dashboard widgets).
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\Chart;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Stat;
use Celerik\Ui\Components\Stats;
use Celerik\Ui\Components\TileLayout;

$stats = Stats::make('kpis')->label('This month')->columns(4)->add(
    Stat::make('kpi-revenue')->label('Revenue')->value('$12,400')
        ->trend(12.5, 'vs last month')->sparkline([8, 9, 12, 11, 14, 13, 16])->link('/reports/revenue'),
    Stat::make('kpi-orders')->label('Orders')->value(312)->trend(-3, 'vs last month')->sparkline([30, 34, 31, 29, 28]),
    Stat::make('kpi-churn')->label('Churn')->value('2.1%')->goodWhen('down')
        ->trend(-0.4, 'vs last month', ' pts')->sparkline([2.6, 2.4, 2.3, 2.1])->color('success'),
    Stat::make('kpi-tickets')->label('Open tickets')->value(18)->trend(0)->color('warning')->sparkline([18, 18]),
);

$tiles = TileLayout::make('widgets')->columns(3)->addClass('mt-3')
    ->tile('sales', 'Sales by month', Chart::make('sales-chart')->type('line')->title('Sales by month', false)
        ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
        ->dataset('Sales', [120, 190, 150, 210, 240, 230]), cols: 2, rows: 2)
    ->tile('visits', 'Visits', Stat::make('tile-visits')->label('Visits today')->value(1204)
        ->trend(8, 'vs yesterday')->sparkline([900, 1010, 980, 1100, 1204]))
    ->tile('orders', 'Latest orders', Grid::make('orders-grid')->caption('Latest orders')
        ->column('id', 'Order')->column('total', 'Total')
        ->rows([['id' => 'A-1', 'total' => '$40'], ['id' => 'A-2', 'total' => '$95'], ['id' => 'A-3', 'total' => '$12']]))
    ->reorderable()->resizable();

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Dashboard (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Dashboard</h1>
    <?= $stats->render() ?>
    <?= $tiles->render() ?>
</main>
</body>
</html>
