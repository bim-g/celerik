<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Filter;
use Celerik\Ui\Data\DataRequest;
use Celerik\Ui\Data\DataResult;
use Celerik\Ui\Data\DataSourceInterface;
use Celerik\Ui\Support\Arr;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * Data grid backed by DataTables.
 *
 * Client-side mode: pass rows with rows(); they are rendered as HTML (readable without JS).
 * Server-side mode: pass an endpoint with url(); the endpoint answers with respond().
 * Columns with a filter() get a filter bar above the table, shown once JavaScript runs.
 * Actions (actions()) get a last column with a button for each row. A selectable grid
 * (selectable()) gets a first column of checkboxes, and bulk actions (bulkActions()) a bar
 * above the table that sends the selected rows.
 */
final class Grid extends Component
{
    public const EXPORT_FORMATS = ['copy', 'csv', 'print'];

    /** Texts of the filter bar, with their English defaults. */
    public const LABELS = [
        'filters' => 'Filters',
        'any' => 'Any',
        'yes' => 'Yes',
        'no' => 'No',
        'from' => 'From',
        'to' => 'To',
        'clear' => 'Clear filters',
        'actions' => 'Actions',
        'select' => 'Select',
        'selectAll' => 'Select all rows on this page',
        'selected' => '{count} selected',
        'noneSelected' => 'No rows selected',
        'bulkActions' => 'Selected rows',
        'columns' => 'Columns',
        'showColumns' => 'Show columns',
        'empty' => 'No rows to show',
        'noMatches' => 'No rows match your search or filters',
        'reset' => 'Clear filters and search',
    ];

    /** How respond() reads each kind of column filter. */
    private const REQUEST_TYPES = [
        Filter::TEXT => DataRequest::FILTER_TEXT,
        Filter::SELECT => DataRequest::FILTER_EXACT,
        Filter::BOOLEAN => DataRequest::FILTER_BOOLEAN,
        Filter::DATE_RANGE => DataRequest::FILTER_DATE_RANGE,
    ];

    /** @var list<Column> */
    private array $columns = [];

    private ?string $url = null;

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    private int $pageSize = 10;

    /** @var list<int> */
    private array $pageSizes = [10, 25, 50, 100];

    private bool $searchable = true;

    /** @var list<string> */
    private array $export = [];

    private ?string $caption = null;

    private bool $captionHidden = false;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    /** @var list<Action> */
    private array $actions = [];

    private ?string $rowTitle = null;

    private ?string $selectKey = null;

    private string $selectName = 'ids';

    /** @var list<Action> */
    private array $bulkActions = [];

    private bool $columnToggle = false;

    private bool $rememberColumns = true;

    private ?string $emptyTitle = null;

    private ?string $emptyDescription = null;

    private ?Action $emptyAction = null;

    public static function name(): string
    {
        return 'grid';
    }

    /**
     * @param (callable(Column): mixed)|null $configure
     */
    public function column(string $field, ?string $title = null, ?callable $configure = null): self
    {
        $column = Column::make($field, $title);

        if ($configure !== null) {
            $configure($column);
        }

        $this->columns[] = $column;

        return $this;
    }

    public function addColumn(Column ...$columns): self
    {
        array_push($this->columns, ...$columns);

        return $this;
    }

    /**
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Switches to server-side mode: paging, sorting and search are done by this endpoint.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     */
    public function rows(iterable $rows): self
    {
        $this->rows = [];

        foreach ($rows as $row) {
            $this->rows[] = $row;
        }

        return $this;
    }

    public function pageSize(int $pageSize): self
    {
        if ($pageSize < 1 || $pageSize > DataRequest::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('Page size must be between 1 and %d.', DataRequest::MAX_PER_PAGE),
            );
        }

        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * @param list<int> $pageSizes
     */
    public function pageSizes(array $pageSizes): self
    {
        $this->pageSizes = array_values(array_map('intval', $pageSizes));

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function export(string ...$formats): self
    {
        $unknown = array_diff($formats, self::EXPORT_FORMATS);

        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported export format(s): %s. Supported: %s.',
                implode(', ', $unknown),
                implode(', ', self::EXPORT_FORMATS),
            ));
        }

        $this->export = array_values(array_unique($formats));

        return $this;
    }

    /**
     * Table caption, used by screen readers to identify the table (WCAG 1.3.1).
     */
    public function caption(string $caption, bool $visuallyHidden = false): self
    {
        $this->caption = $caption;
        $this->captionHidden = $visuallyHidden;

        return $this;
    }

    /**
     * Buttons for each row, in a last column: Action::link() and Action::request(). The
     * fields their URLs and texts read (`{id}`) must be columns of the grid; hide the ones
     * not to show (Column::hidden()).
     */
    public function actions(Action ...$actions): self
    {
        array_push($this->actions, ...$actions);

        return $this;
    }

    /**
     * A first column of checkboxes to select rows, and a box that selects every row of the
     * page. `$key` identifies a row (a column, hidden or not); bulk actions send the selected
     * keys as `$name[]`. The selection is kept across pages, filters and sorting.
     */
    public function selectable(string $key = 'id', string $name = 'ids'): self
    {
        if (!DataRequest::isValidField($key)) {
            throw new InvalidArgumentException(sprintf('Invalid row key "%s".', $key));
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid selection name "%s".', $name));
        }
        $this->selectKey = $key;
        $this->selectName = $name;

        return $this;
    }

    /**
     * Request actions for the selected rows, in a bar above the table ("3 selected"):
     * Action::request('delete', 'Delete', '/members/bulk-delete')->confirm('Delete {count} members?').
     * They post the selected keys (`ids[]`) to their URL. Needs selectable().
     */
    public function bulkActions(Action ...$actions): self
    {
        foreach ($actions as $action) {
            if (!$action->isRequest()) {
                throw new InvalidArgumentException(sprintf(
                    'Bulk action "%s" must be a request (Action::request()).',
                    $action->getName(),
                ));
            }
        }
        array_push($this->bulkActions, ...$actions);

        return $this;
    }

    /**
     * What the grid shows when it has no rows at all, e.g. 'No members yet', 'Invite your team
     * to get started.' and an Action::link() to the invitation page. When search or filters
     * match no row, the grid says so instead, with a button that clears them.
     */
    public function emptyState(string $title, ?string $description = null, ?Action $action = null): self
    {
        $this->emptyTitle = $title;
        $this->emptyDescription = $description;
        $this->emptyAction = $action;

        return $this;
    }

    /**
     * A Columns button that lets the user show or hide columns, except those that are not
     * toggleable() and hidden() ones. With `$remember`, the choice is kept in the browser
     * (localStorage), per grid id.
     */
    public function columnToggle(bool $enabled = true, bool $remember = true): self
    {
        $this->columnToggle = $enabled;
        $this->rememberColumns = $remember;

        return $this;
    }

    /**
     * The field that names a row in its buttons' accessible names ("Delete Ada Lovelace"):
     * by default, the first visible column.
     */
    public function rowTitle(string $field): self
    {
        if (!DataRequest::isValidField($field)) {
            throw new InvalidArgumentException(sprintf('Invalid row title field "%s".', $field));
        }
        $this->rowTitle = $field;

        return $this;
    }

    /**
     * Translates the filter bar and the actions column, e.g. ['clear' => 'Effacer les filtres'].
     *
     * @param array<string, string> $labels Keys from self::LABELS.
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown grid label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    /**
     * Answers a server-side request for this grid.
     *
     * Only declared columns may be sorted or searched, a column filter applies only as its
     * type says, and the returned rows contain only declared column fields, so nothing else
     * from the data source leaks out.
     *
     * @param array<string, mixed> $query Usually `$_GET` or `$request->query()`.
     */
    public function respond(DataSourceInterface $source, array $query): DataResult
    {
        $request = DataRequest::fromArray(
            $query,
            sortable: $this->fields(static fn (Column $column): bool => $column->isSortable()),
            searchable: $this->fields(static fn (Column $column): bool => $column->isSearchable()),
            defaultPerPage: $this->pageSize,
            filterTypes: $this->filterTypes(),
        );

        $fields = $this->fields();

        return $source->fetch($request)->map(static function (array $row) use ($fields): array {
            $projected = [];

            foreach ($fields as $field) {
                Arr::set($projected, $field, Arr::get($row, $field));
            }

            return $projected;
        });
    }

    public function toArray(): array
    {
        $this->assertActionFields();
        $this->assertSelection();

        return array_filter([
            'columns' => array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
            'actions' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            'actionsTitle' => $this->actions === [] ? null : $this->labels['actions'],
            'rowTitle' => $this->actions === [] && $this->selectKey === null ? null : $this->rowTitleField(),
            'selection' => $this->selectKey === null ? null : array_filter([
                'key' => $this->selectKey,
                'name' => $this->selectName,
                'form' => $this->bulkActions === [] ? null : $this->id . '-bulk',
                'labels' => array_intersect_key(
                    $this->labels,
                    array_flip(['select', 'selectAll', 'selected', 'noneSelected']),
                ),
            ], static fn (mixed $value): bool => $value !== null),
            'columnToggle' => !$this->columnToggle ? null : [
                'remember' => $this->rememberColumns,
                'labels' => ['columns' => $this->labels['columns'], 'showColumns' => $this->labels['showColumns']],
            ],
            'emptyHtml' => $this->renderEmpty(),
            'noMatchesHtml' => $this->renderNoMatches(),
            'url' => $this->url,
            'pageSize' => $this->pageSize,
            'pageSizes' => $this->pageSizes,
            'searchable' => $this->searchable,
            'export' => $this->export,
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function render(): string
    {
        if ($this->columns === []) {
            throw new LogicException(sprintf('Grid "%s" has no columns.', $this->id));
        }

        $caption = $this->caption === null ? '' : Html::tag(
            'caption',
            ['class' => $this->captionHidden ? 'visually-hidden' : null],
            Html::escape($this->caption),
        );

        $filters = $this->renderFilters();
        $this->assertSelection();
        $bulk = $this->renderBulkBar();

        $headers = '';
        if ($this->selectKey !== null) {
            $headers .= Html::tag('th', ['scope' => 'col', 'class' => 'celerik-grid-select'], Html::tag('input', [
                'type' => 'checkbox',
                'class' => 'form-check-input',
                'aria-label' => $this->labels['selectAll'],
                'data-celerik-select-all' => true,
            ]));
        }
        foreach ($this->columns as $column) {
            $headers .= Html::tag('th', ['scope' => 'col'], Html::escape($column->getTitle()));
        }
        if ($this->actions !== []) {
            $this->assertActionFields();
            $header = ['scope' => 'col', 'class' => 'celerik-grid-actions'];
            $headers .= Html::tag('th', $header, Html::escape($this->labels['actions']));
        }

        $body = '';
        if ($this->url === null) {
            foreach ($this->rows as $row) {
                $cells = $this->selectKey === null ? '' : Html::tag(
                    'td',
                    ['class' => 'celerik-grid-select'],
                    $this->renderCheckbox($row),
                );
                foreach ($this->columns as $column) {
                    $value = Arr::get($row, $column->getField());
                    $attributes = ['data-value' => self::filterValue($column, $value)];
                    $cells .= Html::tag('td', $attributes, Html::escape(Arr::toText($value)));
                }
                if ($this->actions !== []) {
                    $cells .= Html::tag('td', ['class' => 'celerik-grid-actions'], $this->renderActions($row));
                }
                $body .= Html::tag('tr', [], $cells);
            }
        }

        return $filters . $bulk . Html::tag(
            'table',
            $this->rootAttributes(['table', 'table-striped', 'table-hover', 'w-100', 'celerik-grid']),
            $caption . Html::tag('thead', [], Html::tag('tr', [], $headers)) . Html::tag('tbody', [], $body),
        );
    }

    /**
     * A row's buttons, named with the row's title ("Delete Ada Lovelace").
     *
     * @param array<string, mixed> $row
     */
    private function renderActions(array $row): string
    {
        $title = Arr::toText(Arr::get($row, $this->rowTitleField()));
        $buttons = '';
        foreach ($this->actions as $action) {
            $name = trim($action->getLabel() . ' ' . $title);
            $buttons .= $action->render($row, small: true, ariaLabel: $action->hasAriaLabel() ? null : $name);
        }

        return Html::tag('div', ['class' => 'celerik-grid-action-list'], $buttons);
    }

    /**
     * The empty state, as DataTables shows it in the table's only row: a title, a description
     * and an action, all escaped.
     */
    private function renderEmpty(): string
    {
        $title = Html::escape($this->emptyTitle ?? $this->labels['empty']);
        $html = Html::tag('p', ['class' => 'celerik-grid-empty-title'], $title);
        if ($this->emptyDescription !== null) {
            $description = Html::escape($this->emptyDescription);
            $html .= Html::tag('p', ['class' => 'celerik-grid-empty-description'], $description);
        }
        if ($this->emptyAction !== null) {
            $action = $this->emptyAction->render(small: true);
            $html .= Html::tag('div', ['class' => 'celerik-grid-empty-action'], $action);
        }

        return Html::tag('div', ['class' => 'celerik-grid-empty'], $html);
    }

    /**
     * Search or filters match no row: say so, with a button that clears them (grid.js).
     */
    private function renderNoMatches(): string
    {
        $button = Html::tag('button', [
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-secondary celerik-action-button',
            'data-celerik-grid-reset' => true,
        ], Html::escape($this->labels['reset']));

        return Html::tag(
            'div',
            ['class' => 'celerik-grid-empty'],
            Html::tag('p', ['class' => 'celerik-grid-empty-title'], Html::escape($this->labels['noMatches']))
            . Html::tag('div', ['class' => 'celerik-grid-empty-action'], $button),
        );
    }

    /**
     * A row's checkbox: named "Select Ada Lovelace", and part of the bulk form, so that
     * without JavaScript a bulk action posts the checked rows.
     *
     * @param array<string, mixed> $row
     */
    private function renderCheckbox(array $row): string
    {
        $title = Arr::toText(Arr::get($row, $this->rowTitleField()));

        return Html::tag('input', [
            'type' => 'checkbox',
            'class' => 'form-check-input',
            'name' => $this->selectName . '[]',
            'value' => Arr::toText(Arr::get($row, (string) $this->selectKey)),
            'form' => $this->bulkActions === [] ? null : $this->id . '-bulk',
            'aria-label' => trim($this->labels['select'] . ' ' . $title),
            'data-celerik-select' => true,
        ]);
    }

    /**
     * The bulk form: the number of rows selected (a status) and the bulk actions.
     */
    private function renderBulkBar(): string
    {
        if ($this->selectKey === null || $this->bulkActions === []) {
            return '';
        }

        $csrf = '';
        $buttons = '';
        foreach ($this->bulkActions as $action) {
            $csrf = $csrf !== '' ? $csrf : $action->renderCsrfField();
            $buttons .= $action->renderBulkButton();
        }
        $status = Html::tag(
            'span',
            ['class' => 'celerik-grid-selected', 'role' => 'status', 'data-celerik-selected' => true],
            Html::escape($this->labels['noneSelected']),
        );

        return Html::tag('form', [
            'id' => $this->id . '-bulk',
            'method' => 'post',
            'class' => 'celerik-action celerik-grid-bulk d-flex flex-wrap align-items-center gap-2 mb-3',
            'role' => 'group',
            'aria-label' => $this->labels['bulkActions'],
            'data-celerik-bulk' => $this->selectName,
        ], $csrf . $status . $buttons);
    }

    private function assertSelection(): void
    {
        if ($this->bulkActions !== [] && $this->selectKey === null) {
            throw new LogicException(sprintf('Grid "%s" has bulk actions: call selectable() too.', $this->id));
        }
        if ($this->selectKey !== null && !in_array($this->selectKey, $this->fields(), true)) {
            throw new LogicException(sprintf(
                'The row key "%s" of grid "%s" is not a column: add one (it can be hidden).',
                $this->selectKey,
                $this->id,
            ));
        }
    }

    private function rowTitleField(): string
    {
        if ($this->rowTitle !== null) {
            return $this->rowTitle;
        }
        foreach ($this->columns as $column) {
            if ($column->isVisible()) {
                return $column->getField();
            }
        }

        return $this->columns[0]->getField();
    }

    /**
     * Actions only read declared columns: in server-side mode, respond() returns nothing else.
     */
    private function assertActionFields(): void
    {
        if ($this->actions === []) {
            return;
        }
        $fields = $this->fields();
        $needed = $this->rowTitle === null ? [] : [$this->rowTitle];
        foreach ($this->actions as $action) {
            foreach ($action->fields() as $field) {
                if (!in_array($field, $fields, true)) {
                    throw new LogicException(sprintf(
                        'Action "%s" of grid "%s" reads {%s}: add a column for it (it can be hidden).',
                        $action->getName(),
                        $this->id,
                        $field,
                    ));
                }
            }
        }
        foreach ($needed as $field) {
            if (!in_array($field, $fields, true)) {
                throw new LogicException(sprintf(
                    'The row title "%s" of grid "%s" is not a column.',
                    $field,
                    $this->id,
                ));
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function filterTypes(): array
    {
        $types = [];
        foreach ($this->columns as $column) {
            $filter = $column->getFilter();
            if ($filter !== null) {
                $types[$column->getField()] = self::REQUEST_TYPES[$filter->getType()];
            }
        }

        return $types;
    }

    /**
     * The value a select, yes/no or date filter compares, for client-side rows: true and
     * false as 1 and 0, dates as Y-m-d H:i:s.
     */
    private static function filterValue(Column $column, mixed $value): ?string
    {
        $type = $column->getFilter()?->getType();

        return match (true) {
            $type === null, $type === Filter::TEXT => null,
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => Arr::toText($value),
        };
    }

    /**
     * The filter bar: a labelled field per filtered column and a button that clears them.
     * It is hidden until the grid's JavaScript runs, as it needs it to filter.
     */
    private function renderFilters(): string
    {
        $fields = '';

        foreach ($this->columns as $column) {
            $filter = $column->getFilter();
            if ($filter === null) {
                continue;
            }

            $field = $column->getField();
            $id = $this->id . '-filter-' . str_replace('.', '-', $field);
            $title = Html::escape($column->getTitle());
            $data = ['data-celerik-filter' => $field];

            if ($filter->getType() === Filter::DATE_RANGE) {
                // A group named by the column title, with a From and a To date.
                $inputs = '';
                foreach (['from', 'to'] as $end) {
                    $endId = $id . '-' . $end;
                    $label = Html::escape($this->labels[$end]);
                    $input = $data + ['type' => 'date', 'class' => 'form-control', 'id' => $endId];
                    $inputs .= Html::tag('label', ['class' => 'visually-hidden', 'for' => $endId], $label)
                        . Html::tag('input', $input + ['data-celerik-filter-end' => $end]);
                }
                $legend = Html::tag('legend', ['class' => 'form-label fs-6 mb-2'], $title);
                $group = Html::tag('div', ['class' => 'input-group'], $inputs);
                $fields .= Html::tag('fieldset', ['class' => 'col-sm-6 col-lg-4'], $legend . $group);
                continue;
            }

            if ($filter->getType() === Filter::TEXT) {
                $input = ['type' => 'search', 'class' => 'form-control', 'id' => $id, 'autocomplete' => 'off'];
                $control = Html::tag('input', $data + $input);
            } else {
                $options = $filter->getType() === Filter::BOOLEAN
                    ? ['1' => $this->labels['yes'], '0' => $this->labels['no']]
                    : $filter->getOptions();
                $html = Html::tag('option', ['value' => ''], Html::escape($this->labels['any']));
                foreach ($options as $value => $label) {
                    $html .= Html::tag('option', ['value' => (string) $value], Html::escape($label));
                }
                $control = Html::tag('select', $data + ['class' => 'form-select', 'id' => $id], $html);
            }

            $label = Html::tag('label', ['class' => 'form-label', 'for' => $id], $title);
            $fields .= Html::tag('div', ['class' => 'col-sm-6 col-lg-3'], $label . $control);
        }

        if ($fields === '') {
            return '';
        }

        $clear = Html::tag('div', ['class' => 'col-auto'], Html::tag(
            'button',
            ['type' => 'button', 'class' => 'btn btn-outline-secondary', 'data-celerik-filter-clear' => true],
            Html::escape($this->labels['clear']),
        ));

        return Html::tag('div', [
            'class' => 'celerik-grid-filters row g-3 align-items-end mb-3',
            'id' => $this->id . '-filters',
            'role' => 'search',
            'aria-label' => $this->labels['filters'],
            'hidden' => true,
        ], $fields . $clear);
    }

    /**
     * @param (callable(Column): bool)|null $filter
     * @return list<string>
     */
    private function fields(?callable $filter = null): array
    {
        $columns = $filter === null ? $this->columns : array_filter($this->columns, $filter);

        return array_values(array_map(static fn (Column $column): string => $column->getField(), $columns));
    }
}
