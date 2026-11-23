<?php

use Caelrik\Ui\Components\Chart;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Stat;
use Caelrik\Ui\Components\Stats;
use Caelrik\Ui\Components\TileLayout;

return [
    Stats::make('kpis')->label('This month')->columns(4)->add(
        Stat::make('kpi-revenue')->label('Revenue')->value('$12,400')
            ->trend(12.5, 'vs last month')->sparkline([8, 9, 12, 11, 14, 13, 16]),
        Stat::make('kpi-orders')->label('Orders')->value(312)
            ->trend(-3, 'vs last month')->sparkline([30, 34, 31, 29, 28]),
        Stat::make('kpi-churn')->label('Churn')->value('2.1%')->goodWhen('down')
            ->trend(-0.4, 'vs last month', ' pts')->sparkline([2.6, 2.4, 2.3, 2.1])->color('success'),
        Stat::make('kpi-tickets')->label('Open tickets')->value(18)->trend(0)->description('Answered within a day.'),
    ),

    TileLayout::make('widgets')->columns(3)
        ->tile('sales', 'Sales by month', Chart::make('sales-chart')->type('line')->title('Sales by month', false)
            ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
            ->dataset('Sales', [120, 190, 150, 210, 240, 230]), cols: 2, rows: 2)
        ->tile('visits', 'Visits', Stat::make('tile-visits')->label('Visits today')->value(1204)
            ->trend(8, 'vs yesterday')->sparkline([900, 1010, 980, 1100, 1204]))
        ->tile('orders', 'Latest orders', Grid::make('orders-grid')->caption('Three most recent orders')
            ->column('id', 'Order')->column('total', 'Total')
            ->rows([
                ['id' => 'A-1', 'total' => '$40'],
                ['id' => 'A-2', 'total' => '$95'],
                ['id' => 'A-3', 'total' => '$12'],
            ]))
        ->reorderable()->resizable(),
];
