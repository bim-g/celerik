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
     * Translates the filter bar, e.g. ['clear' => 'Effacer les filtres'].
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
        return array_filter([
            'columns' => array_map(static fn (Column $column): array => $column->toArray(), $this->columns),
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

        $headers = '';
        foreach ($this->columns as $column) {
            $headers .= Html::tag('th', ['scope' => 'col'], Html::escape($column->getTitle()));
        }

        $body = '';
        if ($this->url === null) {
            foreach ($this->rows as $row) {
                $cells = '';
                foreach ($this->columns as $column) {
                    $value = Arr::get($row, $column->getField());
                    $attributes = ['data-value' => self::filterValue($column, $value)];
                    $cells .= Html::tag('td', $attributes, Html::escape(Arr::toText($value)));
                }
                $body .= Html::tag('tr', [], $cells);
            }
        }

        return $filters . Html::tag(
            'table',
            $this->rootAttributes(['table', 'table-striped', 'table-hover', 'w-100', 'celerik-grid']),
            $caption . Html::tag('thead', [], Html::tag('tr', [], $headers)) . Html::tag('tbody', [], $body),
        );
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
