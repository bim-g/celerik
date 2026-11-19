<?php

/**
 * Display elements, included by server.php (GET /display?mode=…): badges, avatars, chips,
 * tooltips, a popover, progress, a loader and a skeleton.
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\Avatar;
use Celerik\Ui\Components\Badge;
use Celerik\Ui\Components\Chip;
use Celerik\Ui\Components\Loader;
use Celerik\Ui\Components\Popover;
use Celerik\Ui\Components\Progress;
use Celerik\Ui\Components\Skeleton;
use Celerik\Ui\Components\Tooltip;
use Celerik\Ui\Support\Html;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Display elements (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-0">
    <p class="mb-5">
        <?= Tooltip::make('edge-tip')->text('Shown below: there is no room above')
            ->trigger(Html::raw('<button type="button" class="btn btn-sm celerik-action-button btn-outline-secondary">At the edge</button>')) ?>
    </p>
    <h1>Display elements</h1>
    <p>
        Inbox <?= Badge::make()->text(3)->color('danger')->pill()->hiddenText('unread messages') ?>
        <?= Badge::make()->text('Active')->color('success') ?>
        <?= Badge::make()->text('Draft')->color('warning') ?>
        <?= Badge::make()->text('New')->color('info') ?>
    </p>
    <p class="d-flex gap-2 align-items-center">
        <?= Avatar::make()->person('Ada Lovelace')->status('online') ?>
        <?= Avatar::make()->person('Alan Turing')->status('away')->size('lg') ?>
        <?= Avatar::make()->person('Grace Hopper')->status('busy')->size('xl') ?>
        <?= Avatar::make()->person('Linus')->status('offline')->size('sm') ?>
        <?= Avatar::make()->person('Katherine Johnson')->decorative() ?> Katherine Johnson
    </p>
    <form id="skills" class="d-flex flex-wrap gap-2 mb-3">
        <?= Chip::make('chip-php')->label('PHP')->removable('skills[]', 'php') ?>
        <?= Chip::make('chip-js')->label('JavaScript')->removable('skills[]', 'js') ?>
        <?= Chip::make('chip-go')->label('Go')->removable('skills[]', 'go')->avatar(Avatar::make()->person('Go Gopher')) ?>
        <?= Chip::make('chip-remote')->label('Remote')->checkbox('remote', '1', checked: true) ?>
        <?= Chip::make('chip-onsite')->label('On site')->checkbox('onsite', '1') ?>
    </form>
    <p>
        <?= Tooltip::make('save-tip')->text('Saves a draft you can finish later')
            ->trigger(Html::raw('<button type="button" class="btn btn-primary">Save</button>')) ?>
        The <?= Tooltip::make('api-tip')->text('A secret that identifies your application')->trigger('API key') ?>
        is in your settings.
        <?= Popover::make('pricing-help')->button('?', 'About pricing')->title('Pricing')
            ->content(Html::raw('<p>Prices include taxes. <a href="/pricing">See all prices</a>.</p>')) ?>
    </p>
    <div class="row g-3 align-items-center">
        <div class="col-md-6">
            <?= Progress::make('upload')->label('Uploading report.pdf')->value(40)->showValue() ?>
            <?= Progress::make('sync')->label('Syncing')->color('info')->addClass('mt-2') ?>
            <?= Progress::make('quota')->label('Storage used')->value(92)->showValue()->color('warning')->addClass('mt-2') ?>
        </div>
        <div class="col-md-6 d-flex gap-3">
            <?= Progress::make('profile')->label('Profile complete')->value(3)->max(4)->circle()->showValue() ?>
            <?= Progress::make('steps')->label('Steps')->value(9)->max(10)->circle()->showValue()->size('lg')->color('success') ?>
            <?= Loader::make()->label('Loading orders')->showLabel() ?>
        </div>
    </div>
    <?= Skeleton::make()->label('Loading profile')->avatar()->heading()->lines(3)->addClass('mt-3 col-md-6') ?>
</main>
</body>
</html>
