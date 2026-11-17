# Grid

A data table on [DataTables 3](https://datatables.net/): sorting, search, column filters, columns the user can show or hide, row actions, row selection with bulk actions, paging and export, client-side or server-side.

<Demo name="grid" :min-height="640" />

<<< @/demos/grid.php

## Client-side or server-side

- **Client-side**: pass the rows with `rows()`. PHP renders them in the table, and the browser sorts, searches and pages them. Good for up to a few hundred rows.
- **Server-side**: pass an endpoint with `url()`, and answer it with `respond()` using the same grid definition (see [Data endpoints](../guide/data#grid)). The browser asks for one page at a time.

```php
$grid = Grid::make('users')->caption('Users')->url('/users/data')->column('name')->column('email', 'E-mail');

// GET /users/data
echo json_encode($grid->respond(new ArrayDataSource($rows), $_GET));
```

## Options

| Method | Use |
|---|---|
| `column($field, $title = null, $configure = null)` | Adds a column; the title defaults to the field name. `$configure` receives the `Column`. |
| `addColumn(Column ...$columns)` | Adds prepared columns (`Column::make('email', 'E-mail')`). |
| `caption($caption, $visuallyHidden = false)` | The table's caption, which names it for screen readers. Always set one. |
| `rows(iterable $rows)` | Client-side data: arrays or objects with the column fields (dot notation for nested values). |
| `url($url)` | Server-side mode. |
| `pageSize($size)`, `pageSizes([10, 25, 50])` | Rows per page, and the choices offered. |
| `searchable(false)` | Hides the search box. |
| `export('copy', 'csv', 'print')` | Export buttons. |
| `actions(Action ...$actions)` | Buttons for each row, in a last column (see [Row actions](#row-actions)). |
| `columnToggle($enabled = true, $remember = true)` | A Columns menu to show or hide columns (see [Columns the user can show or hide](#columns-the-user-can-show-or-hide)). |
| `selectable($key = 'id', $name = 'ids')` | A checkbox per row (see [Row selection](#row-selection-and-bulk-actions)). |
| `bulkActions(Action ...$actions)` | Request actions for the selected rows, in a bar above the table. |
| `rowTitle($field)` | The field that names a row in its buttons' and checkbox's accessible names. Default: the first visible column. |
| `labels([...])` | Translates the filter bar, the actions column and the selection: `filters`, `any`, `yes`, `no`, `from`, `to`, `clear`, `actions`, `select`, `selectAll`, `selected` (with `{count}`), `noneSelected`, `bulkActions`, `columns`, `showColumns`. |
| `options([...])` | Raw DataTables options, applied last. |

### Columns

```php
Grid::make('orders')
    ->column('id', 'ID', static fn (Column $column) => $column->searchable(false)->width('5rem'))
    ->column('customer.name', 'Customer')
    ->column('total', 'Total', static fn (Column $column) => $column->className('text-end'))
    ->column('internal_ref', null, static fn (Column $column) => $column->hidden());
```

| Method | Use |
|---|---|
| `sortable(false)` | Not sortable (also refused by `respond()`). |
| `searchable(false)` | Not searched (also refused as a filter). |
| `hidden()` | Sent to the client but not shown, e.g. an id for row actions. Never in the Columns menu. |
| `toggleable(false)` | Always shown: not in the Columns menu. |
| `initiallyHidden()` | Hidden until the user shows it from the Columns menu. |
| `className($class)`, `width($width)` | CSS class of the cells, and the column width. |
| `filter(Filter::text())` | A filter in the filter bar (see [Column filters](#column-filters)). |

## Column filters

A column with a filter gets a labelled field in a filter bar above the table, with a **Clear filters** button. The rows update as the user types or picks a value.

```php
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Filter;

Grid::make('orders')
    ->column('customer', 'Customer', static fn (Column $c) => $c->filter(Filter::text()))
    ->column('status', 'Status', static fn (Column $c) => $c->filter(Filter::select([
        'open' => 'Open',
        'paid' => 'Paid',
        'refunded' => 'Refunded',
    ])))
    ->column('gift', 'Gift', static fn (Column $c) => $c->filter(Filter::boolean()))
    ->column('ordered_at', 'Ordered', static fn (Column $c) => $c->filter(Filter::dateRange()));
```

| Filter | Field | Keeps the rows whose value |
|---|---|---|
| `Filter::text()` | A search box | contains the text, ignoring case |
| `Filter::select([value => label])` | A select, with **Any** first | is the chosen value |
| `Filter::boolean()` | A select: **Any**, **Yes**, **No** | is true or false (`1` or `0` in a database) |
| `Filter::dateRange()` | Two date fields, **From** and **To**, grouped under the column title | is a date (`Y-m-d`, with a time or not) in the range, both days included |

- **Client-side**, the browser filters the rows PHP rendered.
- **Server-side**, the grid sends the filters to your endpoint, and `respond()` applies them through the data source: `ArrayDataSource`, `EloquentDataSource` and `DoctrineDataSource` all do (see [Data endpoints](../guide/data#column-filters)).
- **Filters and search** work together, and a filter works on a column that is not `searchable()`. With `searchable(false)` on the grid, the search box is hidden and the filters keep working.
- **Without JavaScript**, the filter bar is not shown, since it needs it to filter.

## Columns the user can show or hide

`columnToggle()` adds a **Columns** button next to the page length. It opens a group of checkboxes, one for each column the user may show or hide.

```php
Grid::make('contacts')
    ->column('id', 'ID', static fn (Column $c) => $c->hidden())         // data only: not in the menu
    ->column('name', 'Name', static fn (Column $c) => $c->toggleable(false)) // always shown
    ->column('email', 'E-mail')
    ->column('phone', 'Phone', static fn (Column $c) => $c->initiallyHidden())
    ->columnToggle();
```

- **The choice is remembered** in the browser (`localStorage`), per grid id. `columnToggle(remember: false)` turns that off.
- **The last shown column** cannot be hidden, unless a `toggleable(false)` column is always there.
- **The menu** is a disclosure: the button says whether it is open (`aria-expanded`), and Escape closes it and returns focus to the button. It also closes when focus or a click leaves it.
- **Without JavaScript**, every column but `hidden()` ones is shown, `initiallyHidden()` ones included.

## Row actions

`actions()` adds a last column with a button for each row: [actions](./actions) that open a page or send a request.

```php
use Celerik\Ui\Components\Action;

Grid::make('members')
    ->url('/members/data')
    ->column('id', 'ID', static fn (Column $c) => $c->hidden())
    ->column('name', 'Name')
    ->column('email', 'E-mail')
    ->actions(
        Action::link('edit', 'Edit', '/members/{id}/edit'),
        Action::request('delete', 'Delete', '/members/{id}')
            ->method('DELETE')
            ->variant('danger')
            ->confirm('Delete {name}?', 'This cannot be undone.')
            ->csrf($csrfToken),
    );
```

- `{field}` takes the row's value. **The field must be a column of the grid**, hidden if it should not show: in server-side mode `respond()` returns nothing else. The grid throws a `LogicException` naming the missing column.
- Each button is named with its label and the row's title, for example "Delete Ada Lovelace", because a screen reader lists many "Delete" buttons otherwise. `rowTitle()` chooses the field, and `ariaLabel()` on an action replaces the name.
- After a request succeeds:
  - a **server-side grid** reloads its page of rows;
  - a **client-side grid** removes the row of a `DELETE`.

  Focus moves to the same button in the row that takes its place, or to the table when the page is empty.
- Exports leave the actions column out.

## Row selection and bulk actions

`selectable()` adds a first column with a checkbox for each row, and a box in its header that selects every row of the page. `bulkActions()` adds a bar above the table: the number of rows selected, and a button for each action.

```php
Grid::make('members')
    ->url('/members/data')
    ->column('id', 'ID', static fn (Column $c) => $c->hidden())
    ->column('name', 'Name')
    ->selectable('id')
    ->bulkActions(
        Action::request('delete', 'Delete selected', '/members/bulk-delete')
            ->method('DELETE')
            ->variant('danger')
            ->confirm('Delete {count} members?', 'This cannot be undone.')
            ->csrf($csrfToken),
    );

// DELETE /members/bulk-delete: ids[]=4&ids[]=9
// Check authorization, and that the user may change each of these rows.
$ids = array_map('intval', $_POST['ids'] ?? []);
```

- **The key** (`id` by default) identifies a row. It must be a column of the grid, hidden or not.
- **The selection** is kept across pages, filters and sorting.
  - The header box is checked when every row of the page is selected, and mixed when some are.
  - Every checkbox is named after its row, for example "Select Ada Lovelace".
- **The bar** says how many rows are selected, in a status that screen readers announce. Its buttons are disabled while no row is selected.
- **A bulk action** posts the selected keys as `ids[]` (the second argument of `selectable()` names them), with the action's method and the CSRF token. `{count}` in its confirmation is the number of rows.
- **After a bulk action succeeds:**
  - a server-side grid reloads its page;
  - a client-side grid removes the rows of a `DELETE`.

  The selection is then cleared, and focus moves to the header box.
- **Without JavaScript**, the checkboxes of a client-side grid belong to the bulk form, so a bulk action posts the checked rows of the page.
- Exports leave the selection column out.

## Accessibility

- A real `<table>` with a `<caption>` and `scope="col"` headers, before and after JavaScript.
- Sort buttons are named ("Name: Activate to sort") and `aria-sort` reports the order; DataTables announces results in a polite status region.
- Every control can be used from the keyboard, and focus stays visible in Windows High Contrast.
- The filter bar is a `search` landmark named "Filters". Each field has a visible label (the column title); a date range is a group named by the column title, with **From** and **To** fields. Paging information is updated as rows are filtered.

## Security

`respond()` only sorts, searches and filters declared columns, rejects malformed field names, escapes `%` and `_` in the search term and text filters, and returns only declared fields, whatever your data source returns. A column filter applies only as its type says: a yes/no filter accepts `1` or `0`, a date range only real dates written `Y-m-d`, and anything else is ignored. Cells are always rendered as text.
