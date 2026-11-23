# Diagram

A flowchart drawn by PHP as an SVG, and written as text under it: steps, decisions and the arrows between them.

<Demo name="diagram" :min-height="720" />

<<< @/demos/diagram.php

## Usage

```php
use Caelrik\Ui\Components\Diagram;

Diagram::make('signup')
    ->label('Sign-up')
    ->node('form', 'Fill in the form')
    ->node('valid', 'Is it valid?', 'decision')
    ->node('done', 'Account created', 'terminal', 'success')
    ->edge('form', 'valid')
    ->edge('valid', 'done', 'Yes')
    ->edge('valid', 'form', 'No');
```

- **`node($id, $text, $shape, $color)`**: a step. Shapes: `box` (the default), `terminal` (a start or an end, rounded) and `decision` (a diamond). Colors: `primary`, `secondary`, `success`, `danger`, `warning`, `info`. Long texts wrap.
- **`edge($from, $to, $label)`**: an arrow, with an optional label such as "Yes".
- **`direction('right')`**: from left to right instead of downwards. Keep it for short flows: a wide drawing shrinks to fit its container.
- **`labels()`** translates the texts (`image`, `text`, `next`, `start`, `end`).

### The layout

The steps are placed in ranks: each step comes after every step that leads to it. An arrow that goes back to an earlier step (a loop, such as "No: fill in the form again") is dashed and drawn around the side. An arrow that skips ranks gets a lane of its own, so it does not cross the steps between. Each rank is ordered to cross fewer arrows.

It draws flowcharts; it is not a diagram editor, and very large graphs are better split into several diagrams.

## Accessibility

- **The drawing is an image** named by its caption ("Sign-up. The steps are written as text below.").
- **"Steps as text"** (a native disclosure under the caption) lists every step in order, marks the starts and the ends, and says where each arrow leads ("Yes: Account created · No: Fill in the form"), with links from step to step. It helps everyone who prefers reading to following arrows.
- **Colors are not the only sign**: shapes and texts say what a step is. Text keeps the page's text color; shapes and arrows keep 3:1 against the page, and forced-colors mode uses system colors.
- **No JavaScript, no inline styles**: the drawing is an SVG sized by attributes, which works under a strict Content Security Policy. A diagram library such as Mermaid adds `<style>` elements to its drawings, which such a policy blocks.
