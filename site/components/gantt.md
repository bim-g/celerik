# Gantt chart

Tasks on a timeline, with their progress, milestones and what each task waits for.

<Demo name="gantt" :min-height="620" />

<<< @/demos/gantt.php

## Usage

```php
use Celerik\Ui\Components\Gantt;

Gantt::make('launch')
    ->label('Product launch')
    ->scale('week')                                     // day, week or month
    ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100)
    ->task('build', 'Build', '2026-11-16', '2026-12-11', progress: 40, depends: ['design'], color: 'success')
    ->milestone('release', 'Release', '2026-12-14', depends: ['build']);
```

- **`task($id, $name, $start, $end, $progress, $depends, $color)`**: both days are included. Dates are `Y-m-d` strings or `DateTimeInterface` objects. `$depends` lists the ids of the tasks it waits for.
- **`milestone($id, $name, $date, $depends, $color)`**: one day, drawn as a diamond.
- **`scale()`**: `day` (28 px a day, weekends shaded), `week` (the default, from Monday) or `month`. The timeline starts and ends on whole days, weeks or months.
- **`today($date)`**: the day of the red "today" line; the current day by default, `null` to hide it.
- **`dateFormat()`**: how the start and end are written ("M j, Y" by default).
- **`labels()`** translates the column headers and "Milestone" (`task`, `start`, `end`, `progress`, `depends`, `timeline`, `milestone`).

## Accessibility

- **A real table** named by the chart's label: task, start and end (`<time datetime>` elements), progress, and the names of the tasks it depends on. The bars are drawn from the same data, and hidden from screen readers, so nothing is told by the drawing alone.
- **Milestones** say "Milestone" in their progress cell, not only by their diamond shape.
- **The timeline scrolls sideways** in a named region reached with Tab and scrolled with the arrow keys. The task names stay in view, and the first scroll brings today's line into view.
- **Bars** are a light fill with a border in their color (3:1), the done part filled. The arrows between tasks need JavaScript, and are decorative: the "Depends on" column says the same.
- **No inline styles**: each row's bar is an SVG sized by attributes, so it lines up with its row and works under a strict Content Security Policy. In forced-colors mode it uses system colors.
