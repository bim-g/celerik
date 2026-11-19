# Task board

A Kanban board: columns of cards that move from one column to another. Cards are dragged, moved from the keyboard, or moved with their **Move** button, which also works without JavaScript.

<Demo name="taskboard" :min-height="520" />

<<< @/demos/taskboard.php

## Columns and cards

```php
$board = TaskBoard::make('sprint')
    ->label('Sprint')
    ->columns([
        'todo' => 'To do',
        'doing' => ['label' => 'In progress', 'limit' => 3],
        'done' => 'Done',
    ])
    ->cards($tasks, id: 'id', column: 'status', title: 'title')
    ->description('summary')
    ->meta('owner', 'due')
    ->link('/tasks/{id}')
    ->moveUrl('/tasks/{id}/move')
    ->csrf($token);
```

- **Columns** are in order: `key => label`, or `key => ['label' => ..., 'limit' => 3]`. Keys use letters, digits, `-` and `_`.
- **Cards** are in order within each column; a card's `column` field holds its column key. Cards whose column is not on the board are left out.
- **A limit** stops cards from being dropped into a full column in the browser, and the column's heading says "2 of 3 cards". Check it on the server too.
- **Without `moveUrl()`**, the board is read only.

## Moving cards

Three ways, for every user:

| How | Does |
|---|---|
| **Move** button | Each card has a column choice and a **Move** button. Without JavaScript, it posts to `moveUrl()`; with it, the card moves at once, last in that column. It is a way to move cards without dragging ([WCAG 2.5.7](https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements)). |
| Drag | Drag a card within its column or to another one ([SortableJS](https://sortablejs.github.io/Sortable/)); its controls and links keep working. |
| Keyboard | On a focused card: **Space** picks it up, **Up** and **Down** move it in its column, **Left** and **Right** to the next columns, **Space** drops it, **Escape** puts it back. Each step is announced. |

## The endpoint

Each move is a form-encoded `POST` to the card's `moveUrl()`, with `column`, `position` (from 0 at the top; left out without JavaScript, which means last), `_method` (`PATCH` by default) and the CSRF token:

```php
// POST /tasks/{id}/move
try {
    $move = $board->cardMove($_POST);       // a column of the board, a position or null
    $tasks->move($id, $move->column, $move->position);
    echo json_encode(['message' => 'Moved.']);
} catch (InvalidCardMove $refusal) {
    http_response_code(422);
    echo json_encode($refusal->toArray());   // {"message": "..."}
}
```

A refused move puts the card back where it was, and its `message` is read out with "The card could not be moved". Throw `InvalidCardMove` for your own rules too, e.g. a full column or a move the user may not make. Without JavaScript, redirect back to the board.

The board fires `celerik:move` with `{id, column, position, ok}` after each move.

## Options

| Method | Use |
|---|---|
| `label($label)` | The board's accessible name (a `region`). |
| `columns([...])` | The columns, with optional limits. |
| `cards($rows, $id = 'id', $column = 'status', $title = 'title')` | The cards. |
| `description($field)`, `meta($field, ...)` | A paragraph and short texts under each card's title. |
| `link('/tasks/{id}')` | Makes the titles links. |
| `moveUrl($url, $method = 'PATCH')`, `csrf($token)` | Where moves are sent. |
| `headingLevel(2)` | The columns' heading level, 2 to 5; cards' titles are one below. |
| `labels([...])` | Translates `move`, `moveTo` (with `{card}`), `help`, `grabbed`, `over`, `moved`, `cancelled`, `failed`, `full`, `count`, `countOne`, `countLimit` and `invalid`. |

## Accessibility

- **The board** is a named region; each column is a section named by its heading, which says how many cards it has.
- **Cards** are focusable, titled by headings, and described by the keyboard instructions.
- **Moves are announced** in a status region: picked up, where the card is ("In progress, position 2 of 3"), dropped, cancelled, refused, or a full column.
- **Focus stays on the card** as it moves, and returns to it when a move is refused.
- **A picked-up card** has a dashed outline, also in Windows High Contrast. Animations follow `prefers-reduced-motion`.
