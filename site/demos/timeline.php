<?php

use Celerik\Ui\Components\Timeline;
use Celerik\Ui\Support\Html;

return [
    Html::raw('<div class="row g-4"><div class="col-md-5"><h2 class="h5">Order 1042</h2>'),
    Timeline::make('order')->label('Order history')
        ->event('Order placed', new DateTimeImmutable('2026-11-01 09:30'), 'Paid by card.', 'success', '✓')
        ->event('Packed', new DateTimeImmutable('2026-11-02 14:00'), color: 'success', marker: '✓')
        ->event(
            'Shipped',
            'Yesterday',
            Html::raw('<p>Tracking number <a href="#tracking">TR-1042</a></p>'),
            marker: '3',
            current: true,
        )
        ->event('Delivered', 'Expected Friday', marker: '4'),
    Html::raw('</div><div class="col-md-7"><h2 class="h5">Project history</h2>'),
    Timeline::make('history')->label('Project history')->alternate()
        ->event('Founded', new DateTimeImmutable('2024-03-01'), 'Two people and a grid.', color: 'info')
        ->event('First release', new DateTimeImmutable('2025-01-15'), 'Grid, forms and charts.', color: 'primary')
        ->event('Version 0.4', new DateTimeImmutable('2026-11-19'), 'Navigation and layout.', 'success', current: true)
        ->event('Version 1.0', 'Next year', 'Stable.', color: 'warning'),
    Html::raw('</div></div>'),
];
