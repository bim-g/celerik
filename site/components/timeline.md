# Timeline

An ordered list of events: an order's history, a project's milestones, an activity feed.

<Demo name="timeline" :min-height="560" />

<<< @/demos/timeline.php

## Usage

```php
use Celerik\Ui\Components\Timeline;

Timeline::make('order')
    ->label('Order history')
    ->event('Order placed', new DateTimeImmutable('2026-11-01 09:30'), 'Paid by card.', color: 'success', marker: '✓')
    ->event('Shipped', 'Yesterday', Html::raw('<p>Tracking <a href="/track/1042">TR-1042</a></p>'), current: true)
    ->event('Delivered', 'Expected Friday');
```

`event()` takes:

| Argument | |
| --- | --- |
| `title` | The event's name (text). |
| `time` | A date, shown in the `dateFormat()` ("M j, Y" by default) inside a `<time datetime>` element; or a text such as "Yesterday". |
| `content` | Text, or HTML from a component or `Html::raw()`. |
| `color` | The marker's color: `primary`, `secondary`, `success`, `danger`, `warning`, `info`. |
| `marker` | A short text in the marker ("✓", "3"). |
| `url` | Makes the title a link (relative, http or https). |
| `current` | The current step. Only one event is current. |

## Options

- **`label()`** names the list for screen readers: "Order history, list, 4 items".
- **`headingLevel(3)`** makes the titles headings, to move from event to event with a screen reader's heading keys. They are paragraphs by default.
- **`alternate()`** puts the events on both sides of the line on wide screens, and on one side on phones.

## Accessibility

- **An ordered list** (`<ol>`): screen readers say how many events there are and where they are.
- **The current step** has `aria-current="step"`, and its marker has a ring: it is not shown by color alone. Colors and markers are decorative; the title and the text tell the event.
- **Dates** are `<time>` elements with a machine-readable `datetime`.
- The line is a border, so it stays visible in forced-colors mode. Plain HTML: no JavaScript.
