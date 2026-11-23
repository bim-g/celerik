<?php

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\AdminLayout;
use Caelrik\Ui\Components\Breadcrumb;
use Caelrik\Ui\Components\Navigation;
use Caelrik\Ui\Components\UserMenu;
use Caelrik\Ui\Support\Html;

// Links in the demo go nowhere; Sign out is answered in the browser.
return AdminLayout::make('app')
    ->brand('Acme', '#')
    ->navigation(Navigation::make('main')->label('Main')
        ->item('Dashboard', '#dashboard')
        ->group('Members', static fn (Navigation $group) => $group
            ->item('All members', '/members')
            ->item('Invitations', '/members/invitations', badge: '3'))
        ->item('Reports', '#reports')
        ->item('Settings', '#settings')
        ->current('/members/42'))
    ->userMenu(UserMenu::make('account')->user('Ada Lovelace', 'ada@example.com')
        ->link('Profile', '#profile')
        ->link('Settings', '#settings')
        ->divider()
        ->action(Action::request('logout', 'Sign out', '/demo-api/logout')))
    ->breadcrumb(Breadcrumb::make()->item('Dashboard', '#dashboard')->item('Members', '/members')->item('Ada Lovelace'))
    ->title('Ada Lovelace')
    ->content(Html::raw('<p>Engineer, member since 2024. Resize the window: below 992px, '
        . 'the navigation moves behind the menu button.</p>'));
