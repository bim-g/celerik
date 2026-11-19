# Stats and dashboards

Stat tiles show a figure with its trend and a sparkline. With a [tile layout](./tilelayout), a [chart](./chart) and a [grid](./grid), they make a dashboard.

<Demo name="stats" :min-height="900" />

<<< @/demos/stats.php

## Stat tiles

```php
Stat::make('revenue')
    ->label('Revenue')
    ->value('$12,400')                    // a string, or a number: 1204 shows "1,204"
    ->trend(12.5, 'vs last month')        // +12.5%; trend(-0.4, '', ' pts') for points
    ->goodWhen('down')                    // for costs, churn, waiting times
    ->sparkline([8, 9, 12, 11, 14, 13, 16])
    ->color('primary')                    // the sparkline's color
    ->description('Paid invoices only.')
    ->link('/reports/revenue');           // the label links, its click area covers the tile
```

- **The trend is said in words**: "▲ Up 12.5% vs last month". The arrow is hidden from screen readers, and the color (green when good, red when bad) only repeats what the words say.
- **The sparkline** is an SVG drawn by PHP: no JavaScript, no inline styles. It is an image named after its values: "Revenue, last 7 values: from 8 to 16, lowest 8, highest 16." Its colors keep a 3:1 contrast in light and dark modes; in forced colors it uses the system's text color.
- **`labels(['up' => ..., 'down' => ..., 'flat' => ..., 'sparkline' => ...])`** translates them.

## A row of stats

```php
Stats::make('kpis')->label('This month')->columns(4)->add($revenue, $orders, $churn, $tickets);
```

A list named by its label: screen readers say "This month, list, 4 items". It has 1 to 6 columns on large screens, two on tablets and one on phones.

## Dashboard widgets

A dashboard is a [tile layout](./tilelayout) whose tiles hold any component: a `Chart`, a `Grid`, a `Stat`, a `RecordView`.

```php
TileLayout::make('widgets')->columns(3)
    ->tile('sales', 'Sales by month', $chart, cols: 2, rows: 2)
    ->tile('visits', 'Visits', $visitsStat)
    ->tile('orders', 'Latest orders', $ordersGrid)
    ->reorderable()->resizable()->remember();
```

- **Charts follow their tile**: when a tile is resized, its chart redraws at the new size.
- **A stat in a tile** loses its own border, so tiles are not boxes in boxes.
