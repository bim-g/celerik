<?php

use Caelrik\Ui\Components\Accordion;
use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\Card;
use Caelrik\Ui\Components\Splitter;
use Caelrik\Ui\Support\Html;

return [
    Accordion::make('faq')
        ->panel('How fast do you ship?', 'Within two working days, anywhere in the country.', open: true)
        ->panel('Can I return an order?', Html::raw('<p>Yes, for thirty days, and the return is free.</p>'))
        ->panel('Which payments do you take?', 'Cards, bank transfers and mobile money.'),

    Card::make('revenue')
        ->title('Revenue')
        ->subtitle('November')
        ->text('Up 12% on October, led by the new subscriptions.')
        ->link('#revenue-report')
        ->actions(Action::link('export', 'Export', '#export'))
        ->footer('Updated today'),

    Splitter::make('mail')
        ->pane('Inbox · Sent · Drafts · Archive', size: 30, min: 20, max: 60, label: 'Folders')
        ->pane('Drag the divider, or focus it and use the arrow keys.', label: 'Message')
        ->remember(),
];
