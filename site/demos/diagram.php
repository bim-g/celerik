<?php

use Celerik\Ui\Components\Diagram;
use Celerik\Ui\Support\Html;

return [
    Html::raw('<div class="row g-4"><div class="col-md-6">'),
    Diagram::make('signup')->label('Sign-up')
        ->node('visit', 'Visit the site', 'terminal')
        ->node('form', 'Fill in the sign-up form')
        ->node('valid', 'Is the form valid?', 'decision')
        ->node('mail', 'Send a confirmation e-mail', color: 'info')
        ->node('done', 'Account created', 'terminal', 'success')
        ->edge('visit', 'form')
        ->edge('form', 'valid')
        ->edge('valid', 'mail', 'Yes')
        ->edge('valid', 'form', 'No')
        ->edge('mail', 'done'),
    Html::raw('</div><div class="col-md-6">'),
    Diagram::make('order')->label('An order, from left to right')->direction('right')
        ->node('stock', 'In stock?', 'decision')
        ->node('wait', 'Order from the supplier', color: 'warning')
        ->node('ship', 'Ship it', 'terminal', 'success')
        ->edge('stock', 'ship', 'Yes')
        ->edge('stock', 'wait', 'No')
        ->edge('wait', 'ship'),
    Html::raw('</div></div>'),
];
