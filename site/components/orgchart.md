# Org chart

People and who they report to, drawn as a tree. Under the drawing it is nested lists, so it needs no chart library and no JavaScript.

<Demo name="orgchart" :min-height="420" />

<<< @/demos/orgchart.php

## Usage

```php
use Celerik\Ui\Components\OrgChart;

OrgChart::make('team')
    ->label('Engineering')
    ->node('ada', 'Ada Lovelace', 'CTO', url: '/people/ada')
    ->node('alan', 'Alan Turing', 'Lead engineer', parent: 'ada', image: '/avatars/alan.jpg');

// From database rows (arrays or objects): id, parent_id, name and title by default.
OrgChart::make('company')->label('Company')->fromRows($people)->collapsible();
OrgChart::make('company')->label('Company')->fromRows($employees, parent: 'manager_id', image: 'photo_url');
```

- **`node($id, $name, $title, $parent, $image, $url)`**: a person, their manager's id, a picture (their initials otherwise, as in `Avatar`) and a link on their name. People without a manager are at the top; there can be several.
- **`fromRows()`** reads the same fields from rows, with the column names you give.
- **`collapsible()`**: a button on each manager shows or hides their team. It shows the size of the team, everyone under them.
- **`expandLevels(2)`**: how many levels are open at first; deeper teams start hidden (it makes the chart collapsible).
- **`labels()`** translates the buttons' names: `team` ("{name}'s team, {count} people") and `teamOne` ("{name}'s team, 1 person").
- A manager who does not exist, or a loop (A reports to B who reports to A), throws an error that names the people.

From JavaScript, `celerik:toggle` gives the team's list `id` and whether it is `expanded`.

## Accessibility

- **Nested lists** named by the chart's label: screen readers say how many people each team has and how deep they are ("list, 3 items, level 2"). Pictures are decorative, as the name is written next to them.
- **The buttons** are named after the manager and the size of their team ("Ada Lovelace's team, 3 people"), show the number, and have `aria-expanded`. They are 24px targets, hidden without JavaScript, when everyone is shown.
- **Wide charts scroll sideways** in a named region reached with Tab. **In a narrow container** (under 36rem, such as a phone or a sidebar) the chart becomes an indented list, without sideways scrolling.
- **The lines are borders**, so they stay in forced-colors mode. No inline styles.
