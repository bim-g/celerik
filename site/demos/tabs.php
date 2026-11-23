<?php

use Caelrik\Ui\Components\Tabs;
use Caelrik\Ui\Support\Html;

// A string is escaped text; Html::raw() (or another component) is inserted as HTML.
$security = Html::raw('<p class="mb-0">Change your password, or turn on <strong>two-factor</strong> sign-in.</p>');

return Tabs::make('account')
    ->label('Account settings')
    ->tab('profile', 'Profile', 'Your name and e-mail.')
    ->tab('security', 'Security', $security)
    ->tab('billing', 'Billing', 'Invoices and payment methods.');
