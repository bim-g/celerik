# Accordion, card and splitter

Containers for a page's content: panels that open and close (the Telerik PanelBar), cards, and panes the user resizes.

<Demo name="containers" :min-height="640" />

<<< @/demos/containers.php

## Accordion

```php
Accordion::make('faq')
    ->panel('Shipping', 'We ship within two days.', open: true)
    ->panel('Returns', Html::raw('<p>Thirty days, <a href="/returns">free</a>.</p>'))
    ->multiple();   // panels open on their own; by default, one at a time
```

- **The [WAI-ARIA accordion](https://www.w3.org/WAI/ARIA/apg/patterns/accordion/)**: each heading (`headingLevel(3)` by default) holds a button with `aria-expanded` that shows or hides its panel, a region named by the heading.
- **Keyboard**: Enter or Space opens and closes; Up, Down, Home and End move between the headings.
- **Without JavaScript**, every panel is shown.
- **Content** is a component or `Html::raw()`; a string is escaped text.

## Card

```php
Card::make('revenue')
    ->title('Revenue', level: 3)
    ->subtitle('November')
    ->text('Up 12% on October.')
    ->image('/img/revenue.png', 'Revenue by week')
    ->body($chart)
    ->link('/reports/revenue')
    ->actions(Action::link('export', 'Export', '/reports/revenue.csv'))
    ->footer('Updated today');
```

- **`link()`** makes the title a link whose click area covers the card. The card stays one short link in the reading order, named by its title, instead of a link wrapping all its text.
- **Actions** sit above that area: they keep their own clicks and focus.
- **The picture** is relative, `http` or `https`; without `$alt`, it is decorative.

## Splitter

```php
Splitter::make('mail')
    ->pane($folders, size: 30, min: 20, max: 60, label: 'Folders')
    ->pane($message, label: 'Message')
    ->remember();   // keeps the user's sizes in the browser
```

- **`orientation('horizontal')`** puts the panes side by side (the default); `'vertical'` puts them one above the other. Sizes are percentages of the splitter; panes without one share what is left.
- **The [WAI-ARIA window splitter](https://www.w3.org/WAI/ARIA/apg/patterns/windowsplitter/)**: each divider is a focusable `separator` named "Resize Folders" (`labels(['resize' => ...])`), valued by the size of the pane before it.
- **Keyboard**: Left and Right (Up and Down when vertical) resize by 5%, Home and End go to the pane's limits, Enter collapses it to its minimum and back.
- **Pointer**: drag the divider with a mouse, pen or finger. Its target is 24px wide.
- **The splitter fires `celerik:resize`** with the panes' sizes.
- **No inline styles**: sizes are set from JavaScript, so pages keep a strict Content Security Policy. Without JavaScript the panes share the space and the dividers are hidden.
