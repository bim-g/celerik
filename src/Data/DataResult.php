<?php

declare(strict_types=1);

namespace Celerik\Ui\Data;

use JsonSerializable;

/**
 * One page of rows plus the metadata every Celerik endpoint returns:
 *
 *     {"data": [...], "meta": {"page", "perPage", "total", "filtered", "draw", "sort", "search", "filters",
 *     "conditions"}}
 *
 * A request with totals adds "totals" (field => value), and one grouped by a field adds
 * "groups": [{"value", "count", "totals"}] for the groups on the page.
 */
final class DataResult implements JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $rows
     * @param int $total Row count before search and filters.
     * @param int $filtered Row count after search and filters, before paging.
     * @param array<string, int|float|null> $totals The request's totals, over the filtered rows.
     * @param list<array{value: mixed, count: int, totals: array<string, int|float|null>}> $groups
     *     The groups of the page's rows (DataRequest::$groupBy), counted and totalled over the
     *     filtered rows.
     */
    public function __construct(
        public readonly array $rows,
        public readonly int $total,
        public readonly int $filtered,
        public readonly DataRequest $request,
        public readonly array $totals = [],
        public readonly array $groups = [],
    ) {
    }

    /**
     * Returns a copy with transformed rows (used to project rows onto the declared columns).
     *
     * @param callable(array<string, mixed>): array<string, mixed> $callback
     */
    public function map(callable $callback): self
    {
        return new self(
            array_values(array_map($callback, $this->rows)),
            $this->total,
            $this->filtered,
            $this->request,
            $this->totals,
            $this->groups,
        );
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function toArray(): array
    {
        $meta = [
            'page' => $this->request->page,
            'perPage' => $this->request->perPage,
            'total' => $this->total,
            'filtered' => $this->filtered,
            'draw' => $this->request->draw,
            'sort' => $this->request->sort,
            'search' => $this->request->search,
            'filters' => (object) $this->request->filters,
            'conditions' => $this->request->conditions,
        ];
        if ($this->request->totals !== []) {
            $meta['totals'] = (object) $this->totals;
        }
        if ($this->request->groupBy !== null) {
            $meta['groups'] = array_map(static fn (array $group): array => [
                'value' => $group['value'],
                'count' => $group['count'],
                'totals' => (object) $group['totals'],
            ], $this->groups);
        }

        return ['data' => $this->rows, 'meta' => $meta];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
