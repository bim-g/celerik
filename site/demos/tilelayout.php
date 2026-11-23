<?php

use Caelrik\Ui\Components\TileLayout;

return TileLayout::make('dashboard')
    ->columns(3)
    ->tile('revenue', 'Revenue', 'Up 12% on October, led by the new subscriptions.', cols: 2)
    ->tile('orders', 'Orders', '48 new orders today, 6 waiting to ship.', rows: 2)
    ->tile('visits', 'Visits', '1,204 visits, 3.1% converted.')
    ->tile('support', 'Support', '5 open tickets, answered in 2 hours on average.')
    ->reorderable()
    ->resizable()
    ->remember();
