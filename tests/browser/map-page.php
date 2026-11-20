<?php

/**
 * Maps, included by server.php (GET /map?mode=…). Tiles come from /tiles/ (one local SVG for
 * every tile), so the tests never depend on the network.
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\Map;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$tiles = static fn (Map $map): Map => $map->tiles('/tiles/{z}/{x}/{y}.svg', 'Test tiles', '/tiles/licence');
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Map (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Map</h1>
    <?= $tiles(Map::make('offices')->label('Our offices'))
        ->marker(-1.6792, 29.2228, 'Goma <HQ>', 'Avenue du Lac 12', url: '/offices/goma')
        ->marker(-2.5083, 28.8608, 'Bukavu', 'Place du 24 Novembre', color: 'success')
        ->marker(-1.9441, 30.0619, 'Kigali', color: 'warning') ?>
    <p>Text between the maps, so the page scrolls.</p>
    <?= $tiles(Map::make('single')->label('Our shop'))->size('sm')->zoom(14)
        ->marker(-1.6792, 29.2228, 'Shop', 'Open 9 to 5', color: 'danger')->places(false) ?>
</main>
</body>
</html>
