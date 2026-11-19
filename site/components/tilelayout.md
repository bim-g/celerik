# Tile layout

A dashboard of tiles the user rearranges: each tile a titled region on a grid of columns, as wide and as high as its spans (the Telerik TileLayout).

<Demo name="tilelayout" :min-height="560" />

<<< @/demos/tilelayout.php

## Tiles

```php
TileLayout::make('dashboard')
    ->columns(4)                                   // 1 to 12, 3 by default
    ->tile('revenue', 'Revenue', $chart, cols: 2)  // key, title, content, spans
    ->tile('orders', 'Orders', $list, rows: 2)     // rows: 1 to 6
    ->headingLevel(2);                             // the tiles' headings, h2 to h6
```

- **Each tile is a region** named by its heading. Content is a component or `Html::raw()`; a string is escaped text.
- **The order on screen is the order of the page**: the grid does not fill gaps with later tiles, so reading and focus orders follow what is seen.
- **Spans are classes**, not inline styles: pages keep a strict Content Security Policy.
- **Small screens** show one column, each tile as high as its content.
- **The key** names the tile in ids and in saved layouts: letters, digits, `-` and `_`.

## Rearranging

```php
TileLayout::make('dashboard')
    ->tile(...)
    ->reorderable()   // the user moves tiles
    ->resizable()     // the user changes their spans
    ->remember();     // keeps the layout in the browser
```

- **The Arrange button** in each tile's header shows its tools: Move earlier, Move later, Narrower, Wider, Shorter, Taller. They work with one click or the keyboard, so nothing needs dragging (WCAG 2.5.7). Escape hides them.
- **Keyboard**: on the Arrange button, the arrow keys move the tile (Left and Up earlier, Right and Down later); with Shift they resize it. The button describes these keys.
- **Pointer**: drag the Arrange button onto another tile to take its place, or the tile's corner to a new size.
- **Each change is announced**: "Revenue: position 2 of 4.", "Revenue: width 3 of 4, height 1 of 6." (`labels(['moved' => ..., 'resized' => ...])`).
- **Without JavaScript** the tiles stay as PHP laid them out, and the Arrange buttons are hidden.

## Saving the layout

`remember()` keeps it in the browser (localStorage), per layout id. To keep it on the server, listen to `celerik:change`, fired after each change:

```js
document.getElementById('dashboard').addEventListener('celerik:change', (event) => {
  // event.detail.tiles: [{ key: 'revenue', cols: 2, rows: 1 }, ...], in order
  fetch('/dashboard/layout', { method: 'PUT', body: JSON.stringify(event.detail.tiles) });
});
```

Then pass the saved order and spans back to `tile()` when rendering.
