<?php

/**
 * Org charts, included by server.php (GET /orgchart?mode=…).
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\OrgChart;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$rows = [
    ['id' => 1, 'parent_id' => null, 'name' => 'Grace Hopper', 'title' => 'Chief executive'],
    ['id' => 2, 'parent_id' => 1, 'name' => 'Ada Lovelace', 'title' => 'Chief technology officer'],
    ['id' => 3, 'parent_id' => 1, 'name' => 'Katherine Johnson', 'title' => 'Chief financial officer'],
    ['id' => 4, 'parent_id' => 1, 'name' => 'Margaret Hamilton', 'title' => 'Head of operations'],
    ['id' => 5, 'parent_id' => 2, 'name' => 'Alan Turing', 'title' => 'Lead engineer'],
    ['id' => 6, 'parent_id' => 2, 'name' => 'Linus Torvalds', 'title' => 'Kernel engineer'],
    ['id' => 7, 'parent_id' => 5, 'name' => 'Barbara Liskov', 'title' => 'Engineer'],
    ['id' => 8, 'parent_id' => 3, 'name' => 'Dorothy Vaughan', 'title' => 'Accountant'],
];
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Org chart (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Org chart</h1>
    <?= OrgChart::make('company')->label('Company')->fromRows($rows)->collapsible() ?>
    <div class="row mt-4">
        <div class="col-md-4">
            <?= OrgChart::make('narrow')->label('Engineering, narrow')
                ->node('2', 'Ada Lovelace', 'Chief technology officer')
                ->node('5', 'Alan Turing', 'Lead engineer', parent: '2', url: '/people/alan')
                ->node('7', 'Barbara Liskov', 'Engineer', parent: '5')
                ->node('6', 'Linus Torvalds', 'Kernel engineer', parent: '2')
                ->expandLevels(2) ?>
        </div>
    </div>
</main>
</body>
</html>
