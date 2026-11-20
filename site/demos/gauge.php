<?php

use Celerik\Ui\Components\Gauge;
use Celerik\Ui\Components\Sparkline;
use Celerik\Ui\Support\Html;

$load = static fn (Gauge $gauge): Gauge => $gauge
    ->format('{value}%')
    ->band(0, 60, 'success', 'Normal')
    ->band(60, 85, 'warning', 'High')
    ->band(85, 100, 'danger', 'Critical');

return [
    Html::raw('<div class="d-flex flex-wrap gap-4 align-items-end mb-3">'),
    $load(Gauge::make('cpu')->label('CPU')->value(72)->target(90)),
    $load(Gauge::make('memory')->label('Memory')->value(41)->size('lg')),
    $load(Gauge::make('disk')->label('Disk')->value(93)->size('sm')),
    Gauge::make('latency')->label('Response time')->value(180)->range(0, 500)->format('{value} ms')->color('info'),
    Html::raw('</div><div class="col-md-8">'),
    Gauge::make('quota')->label('Storage used')->value(1250)->range(0, 2000)->format('{value} GB')->linear()
        ->band(0, 1500, 'success', 'OK')->band(1500, 2000, 'danger', 'Almost full')->target(1500),
    Gauge::make('sales')->label('Sales vs goal')->value(84000)->range(0, 120000)->format('${value}')->linear()
        ->target(100000),
    Html::raw('</div><table class="table w-auto"><caption>Weekly figures</caption><thead><tr>'
        . '<th scope="col">Figure</th><th scope="col">Last 7 weeks</th><th scope="col">Now</th></tr></thead><tbody>'
        . '<tr><th scope="row">Revenue</th><td>'
        . Sparkline::make()->label('Revenue')->values([8, 9, 12, 11, 14, 13, 16])->type('area')->markLast()
        . '</td><td>16</td></tr><tr><th scope="row">Profit</th><td>'
        . Sparkline::make()->label('Profit')->values([3, -2, 0, 5, 4, -1, 6])->type('bar')->color('success')
        . '</td><td>6</td></tr><tr><th scope="row">Errors</th><td>'
        . Sparkline::make()->label('Errors')->values([5, 4, 6, 3, 2, 2, 1])->color('danger')
        . '</td><td>1</td></tr></tbody></table>'),
];
