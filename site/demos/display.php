<?php

use Celerik\Ui\Components\Avatar;
use Celerik\Ui\Components\Badge;
use Celerik\Ui\Components\Chip;
use Celerik\Ui\Components\Loader;
use Celerik\Ui\Components\Popover;
use Celerik\Ui\Components\Progress;
use Celerik\Ui\Components\Skeleton;
use Celerik\Ui\Components\Tooltip;
use Celerik\Ui\Support\Html;

// A row of small elements.
$row = static fn (string ...$parts) => Html::raw(
    '<div class="d-flex flex-wrap gap-2 align-items-center mb-3">' . implode('', $parts) . '</div>',
);

return [
    $row(
        'Inbox',
        Badge::make()->text(3)->color('danger')->pill()->hiddenText('unread messages')->render(),
        Badge::make()->text('Active')->color('success')->render(),
        Badge::make()->text('Draft')->color('warning')->render(),
    ),
    $row(
        Avatar::make()->person('Ada Lovelace')->status('online')->render(),
        Avatar::make()->person('Alan Turing')->status('away')->size('lg')->render(),
        Avatar::make()->person('Grace Hopper')->status('busy')->size('xl')->render(),
        Avatar::make()->person('Linus Torvalds')->status('offline')->render(),
    ),
    $row(
        Chip::make('chip-php')->label('PHP')->removable('skills[]', 'php')->render(),
        Chip::make('chip-js')->label('JavaScript')->removable('skills[]', 'js')->render(),
        Chip::make('chip-remote')->label('Remote')->checkbox('remote', '1', checked: true)->render(),
        Chip::make('chip-onsite')->label('On site')->checkbox('onsite', '1')->render(),
    ),
    $row(
        Tooltip::make('save-tip')->text('Saves a draft you can finish later')
            ->trigger(Html::raw('<button type="button" class="btn btn-primary">Save</button>'))->render(),
        Popover::make('pricing-help')->button('?', 'About pricing')->title('Pricing')
            ->content('Prices include taxes and are set per seat.')->render(),
    ),
    Progress::make('upload')->label('Uploading report.pdf')->value(40)->showValue(),
    $row(
        Progress::make('profile')->label('Profile complete')->value(3)->max(4)->circle()->showValue()->render(),
        Loader::make()->label('Loading orders')->showLabel()->render(),
    ),
    Skeleton::make()->label('Loading profile')->avatar()->heading()->lines(3),
];
