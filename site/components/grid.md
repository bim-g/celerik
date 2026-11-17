# Grid

A data table on [DataTables 3](https://datatables.net/): sorting, search, column filters, paging and export, client-side or server-side.

<Demo name="grid" :min-height="560" />

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
| `labels([...])` | Translates the filter bar: `filters`, `any`, `yes`, `no`, `from`, `to`, `clear`. |
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
| `hidden()` | Sent to the client but not shown, e.g. an id for row actions. |
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

## Accessibility

- A real `<table>` with a `<caption>` and `scope="col"` headers, before and after JavaScript.
- Sort buttons are named ("Name: Activate to sort") and `aria-sort` reports the order; DataTables announces results in a polite status region.
- Every control can be used from the keyboard, and focus stays visible in Windows High Contrast.
- The filter bar is a `search` landmark named "Filters". Each field has a visible label (the column title); a date range is a group named by the column title, with **From** and **To** fields. Paging information is updated as rows are filtered.

## Security

`respond()` only sorts, searches and filters declared columns, rejects malformed field names, escapes `%` and `_` in the search term and text filters, and returns only declared fields, whatever your data source returns. A column filter applies only as its type says: a yes/no filter accepts `1` or `0`, a date range only real dates written `Y-m-d`, and anything else is ignored. Cells are always rendered as text.
