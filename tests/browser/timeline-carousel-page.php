<?php

/**
 * Timelines and carousels, included by server.php (GET /timeline-carousel?mode=…). The
 * pictures are the documentation's (site/public/images/carousel), served under /images/.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Carousel;
use Caelrik\Ui\Components\Timeline;
use Caelrik\Ui\Support\Html;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Timeline and carousel (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Timeline and carousel</h1>
    <div class="row g-4">
        <div class="col-md-5">
            <h2 class="h5">Order 1042</h2>
            <?= Timeline::make('order')->label('Order history')
                ->event('Order placed', new DateTimeImmutable('2026-11-01 09:30'), 'Paid by card.', color: 'success', marker: '✓')
                ->event('Packed', new DateTimeImmutable('2026-11-02 14:00'), color: 'success', marker: '✓')
                ->event('Shipped', 'Yesterday', Html::raw('<p>Tracking number <a href="/track/TR-1">TR-1</a></p>'), color: 'primary', marker: '3', current: true)
                ->event('Delivered', 'Expected Friday', marker: '4') ?>
        </div>
        <div class="col-md-7">
            <?= Carousel::make('news')->label('News')
                ->image('/images/carousel/lake.svg', 'Lake Kivu at dawn, hills behind the water', caption: 'Our new office looks over Lake Kivu.')
                ->image('/images/carousel/hills.svg', 'Green hills under a blue sky', caption: 'Team retreat in the hills.', label: 'Retreat')
                ->image('/images/carousel/city.svg', 'A city at night, lit windows', caption: 'Late releases.') ?>
        </div>
    </div>
    <h2 class="h5 mt-4">Project history</h2>
    <?= Timeline::make('history')->label('Project history')->alternate()->headingLevel(3)
        ->event('Founded', new DateTimeImmutable('2024-03-01'), 'Two people and a grid.', color: 'info')
        ->event('First release', new DateTimeImmutable('2025-01-15'), 'Grid, forms and charts.', color: 'primary')
        ->event('Version 0.4', new DateTimeImmutable('2026-11-19'), 'Navigation and layout.', color: 'success', current: true)
        ->event('Version 1.0', 'Next year', 'Stable.', color: 'warning') ?>
    <?= Carousel::make('tips')->label('Tips')->autoplay(3)->loop(false)->addClass('mt-4')
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">Keyboard</h3><p class="mb-0">Every component works from the keyboard. <a href="/keys">Keys</a></p></div>'))
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">Dark mode</h3><p class="mb-0">Every component has a dark mode.</p></div>'))
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">No inline styles</h3><p class="mb-0">A strict Content Security Policy works.</p></div>')) ?>
</main>
</body>
</html>
