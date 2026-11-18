<?php

declare(strict_types=1);

namespace Celerik\Ui\Components\Grid;

use Celerik\Ui\Data\Aggregate;
use Celerik\Ui\Data\DataRequest;
use InvalidArgumentException;

final class Column
{
    private string $title;
    private bool $sortable = true;
    private bool $searchable = true;
    private bool $visible = true;
    private bool $toggleable = true;
    private bool $initiallyHidden = false;
    private ?string $className = null;
    private ?string $width = null;
    private ?Filter $filter = null;
    private ?string $total = null;
    private ?int $totalDecimals = null;
    private ?Editor $editor = null;

    public function __construct(private readonly string $field, ?string $title = null)
    {
        if (!DataRequest::isValidField($field)) {
            throw new InvalidArgumentException(sprintf('Invalid column field "%s".', $field));
        }

        $this->title = $title ?? ucfirst(str_replace(['_', '.'], ' ', $field));
    }

    public static function make(string $field, ?string $title = null): self
    {
        return new self($field, $title);
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Hidden columns are still sent to the client (e.g. an id for row actions).
     */
    public function hidden(bool $hidden = true): self
    {
        $this->visible = !$hidden;

        return $this;
    }

    /**
     * With Grid::columnToggle(): false keeps the column always shown, out of the Columns menu.
     */
    public function toggleable(bool $toggleable = true): self
    {
        $this->toggleable = $toggleable;

        return $this;
    }

    /**
     * With Grid::columnToggle(): hidden until the user shows it in the Columns menu. Unlike
     * hidden(), it is a column to read, only not shown by default.
     */
    public function initiallyHidden(bool $initiallyHidden = true): self
    {
        $this->initiallyHidden = $initiallyHidden;

        return $this;
    }

    public function className(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * A filter in the grid's filter bar: Filter::text(), select([...]), boolean() or dateRange().
     * respond() applies it on the server; it works on a column that is not searchable.
     */
    public function filter(?Filter $filter): self
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * A total in the grid's footer, over every row that passes the search and filters: 'sum',
     * 'avg', 'count', 'min' or 'max'. A grouped grid (Grid::groupBy()) totals each group too.
     * Without `$decimals`: none for a count or a whole number, 2 otherwise.
     */
    public function total(?string $function = Aggregate::SUM, ?int $decimals = null): self
    {
        if ($function !== null) {
            Aggregate::assertFunction($function);
        }
        if ($decimals !== null && ($decimals < 0 || $decimals > 10)) {
            throw new InvalidArgumentException('A total has between 0 and 10 decimals.');
        }
        $this->total = $function;
        $this->totalDecimals = $decimals;

        return $this;
    }

    public function getTotal(): ?string
    {
        return $this->total;
    }

    public function getTotalDecimals(): ?int
    {
        return $this->totalDecimals;
    }

    /**
     * Lets the user change the cell in place, with an Editor: text(), number(), select([...]),
     * boolean() or date(). The grid needs an endpoint for the changes (Grid::inlineEdit()).
     */
    public function editable(?Editor $editor = null): self
    {
        $this->editor = $editor;

        return $this;
    }

    public function getEditor(): ?Editor
    {
        return $this->editor;
    }

    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'field' => $this->field,
            'title' => $this->title,
            'sortable' => $this->sortable,
            'searchable' => $this->searchable,
            'visible' => $this->visible && !$this->initiallyHidden,
            // A hidden() column holds data, not something to show: never in the Columns menu.
            'toggleable' => $this->visible && $this->toggleable ? null : false,
            'className' => $this->className,
            'width' => $this->width,
            'filter' => $this->filter?->toArray(),
            'editor' => $this->editor?->toArray(),
            'total' => $this->total === null ? null : array_filter(
                ['function' => $this->total, 'decimals' => $this->totalDecimals],
                static fn (mixed $value): bool => $value !== null,
            ),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
