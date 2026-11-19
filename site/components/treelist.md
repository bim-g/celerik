# Tree list

A table whose rows nest like a tree: tasks and subtasks, an organisation, a budget and its lines, folders with their sizes. Rows open and close, and the keyboard moves between rows and cells.

<Demo name="treelist" :min-height="460" />

<<< @/demos/treelist.php

## Rows

From flat rows with a parent field, e.g. from a database:

```php
TreeList::make('tasks')
    ->caption('Tasks')
    ->column('name', 'Task')
    ->column('owner', 'Owner')
    ->column('hours', 'Hours', static fn (Column $c) => $c->className('text-end'))
    ->rows($tasks, id: 'id', parent: 'parent_id');
```

Or nested, each row's children under `children`:

```php
->rows([
    ['id' => 1, 'name' => 'Design', 'children' => [
        ['id' => 2, 'name' => 'Wireframes'],
    ]],
]);
```

- **The first column holds the tree**: its cells are indented by level, with a chevron on rows that open.
- **Ids** must be unique. A row whose parent is not among the rows is a top-level row; rows in a cycle (a row that is its own ancestor) are left out.
- **Rows start closed**, but for `expanded(1, 5)` or `expandAll()`. **Expand all** and **Collapse all** buttons sit above the table.
- **Without JavaScript**, every row is shown, in tree order and indented.

## Options

| Method | Use |
|---|---|
| `column($field, $title = null, $configure = null)` | A column; `$configure` receives a [Grid column](./grid#columns): `className()`, `width()`, `hidden()`. |
| `caption($caption, $visuallyHidden = false)` | The table's caption, which names it. Always set one. |
| `rows(iterable $rows, $id = 'id', $parent = 'parent_id')` | The rows, flat or nested. |
| `expanded(...$ids)`, `expandAll()` | Rows open at first. |
| `actions(Action ...$actions)` | [Actions](./actions) for each row, in a last column, including ones that open a form. A `DELETE` that succeeds removes the row and its descendants. |
| `rowTitle($field)` | The field that names a row in its buttons ("Delete Design"); the first column by default. |
| `labels([...])` | Translates `expandAll`, `collapseAll` and `actions`. |

## Keyboard

The table is one tab stop, on a row or a cell.

| On a row | Does |
|---|---|
| Down, Up | The next or previous visible row. |
| Right | Opens the row; on an open row or one without children, goes to its first cell. |
| Left | Closes the row; on a closed row, goes to its parent. |
| Home, End | The first or last visible row. |
| Enter | Opens or closes the row. |

| On a cell | Does |
|---|---|
| Right, Left | The next or previous cell; Left from the first cell goes back to the row. |
| Down, Up | The same column in the next or previous visible row. |
| Home, End | The first or last cell of the row. |
| Enter | Uses the cell's button or link. |

## Accessibility

- **The table** follows the [WAI-ARIA treegrid pattern](https://www.w3.org/WAI/ARIA/apg/patterns/treegrid/): each row has its level, position and open state, and the caption names the table.
- **Rows and cells** show the focus with an outline, also in Windows High Contrast.
- **Indentation** uses classes rather than inline styles, so pages keep a strict Content Security Policy.
- **Pointer users** open rows with the chevron, or all at once with **Expand all**.
