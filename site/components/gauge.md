# Gauges and sparklines

A value on a scale, and small charts the size of a word. Both are SVGs drawn by PHP: no JavaScript, no inline styles.

<Demo name="gauge" :min-height="640" />

<<< @/demos/gauge.php

## Gauge

```php
use Celerik\Ui\Components\Gauge;

Gauge::make('cpu')
    ->label('CPU')
    ->value(72)
    ->format('{value}%')                       // "{value} ms", "${value}"
    ->band(0, 60, 'success', 'Normal')
    ->band(60, 85, 'warning', 'High')
    ->band(85, 100, 'danger', 'Critical')
    ->target(90);

Gauge::make('quota')->label('Storage used')->value(1250)->range(0, 2000)->format('{value} GB')->linear();
```

- **`range($min, $max)`**: the scale, 0 to 100 by default. A value outside it fills the gauge to its end, and its text still gives the real value.
- **`band($from, $to, $color, $label)`**: colored ranges, drawn along the scale. The value takes its band's color, and the band's name is written under it.
- **`target($value)`**: a mark across the scale.
- **`linear()`**: a bar with the label and the value above it, instead of a semicircle.
- **`format()`**, **`decimals()`**: how numbers are written; by default up to 2 decimals, with thousands separators.
- **`size()`** (`sm`, `md`, `lg`), **`color()`** (the value's color outside the bands), **`scale(false)`** hides the minimum and maximum.
- **`labels(['target' => 'objectif {target}'])`** translates the target's text.

### Accessibility

- **A meter** (`role="meter"`) named by its visible label, with `aria-valuemin`, `aria-valuemax`, `aria-valuenow` and a value text that says it all: "72%, High, target 90%".
- **Not by color alone**: the band's name is written next to the value.
- **Colors keep 3:1** against the page and the track (warning and info use darker shades), and the gauge shows in forced-colors mode with system colors.

## Sparkline

```php
use Celerik\Ui\Components\Sparkline;

Sparkline::make()->label('Revenue')->values([8, 9, 12, 11, 14, 13, 16])->type('area')->markLast();
Sparkline::make()->label('Profit')->values([3, -2, 0, 5])->type('bar')->color('success');
Sparkline::make()->values([120, 140, 90])->decorative();   // next to the written values
```

- **Types**: `line` (default), `area`, `bar` (from zero: negative values are outlined bars below the line).
- **Sizes**: `sm`, `md`, `lg`. It scales with the text around it, so it fits in a sentence, a table cell or a record view.
- **An image named after its values' range**: "Revenue, last 7 values: from 8 to 16, lowest 8, highest 16." `labels(['name' => …])` translates it (`{label}`, `{count}`, `{first}`, `{last}`, `{min}`, `{max}`).
- **`decorative()`** hides it from screen readers when the values are written next to it.
- The stat tile's sparkline (`Stat::sparkline()`) is drawn the same way.
