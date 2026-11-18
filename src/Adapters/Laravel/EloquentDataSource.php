<?php

declare(strict_types=1);

namespace Celerik\Ui\Adapters\Laravel;

use Celerik\Ui\Data\Aggregate;
use Celerik\Ui\Data\DataRequest;
use Celerik\Ui\Data\DataResult;
use Celerik\Ui\Data\DataSourceInterface;
use Celerik\Ui\Support\Arr;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Data source for an Eloquent or query builder, e.g. `new EloquentDataSource(User::query())`.
 *
 * Models are serialized with toArray(), so `$hidden` attributes are never sent.
 */
final class EloquentDataSource implements DataSourceInterface
{
    /**
     * @param list<string> $searchable Search fields used when the request does not specify any.
     */
    public function __construct(
        private readonly EloquentBuilder|QueryBuilder $query,
        private readonly array $searchable = [],
    ) {
    }

    public function fetch(DataRequest $request): DataResult
    {
        $total = (clone $this->query)->count();
        $query = clone $this->query;

        $fields = $request->searchFields !== [] ? $request->searchFields : $this->searchable;

        $pattern = $request->likePattern();

        if ($pattern !== null && $fields !== []) {
            $grammar = ($query instanceof EloquentBuilder ? $query->getQuery() : $query)->getGrammar();

            $query->where(static function (EloquentBuilder|QueryBuilder $where) use ($fields, $pattern, $grammar) {
                foreach ($fields as $field) {
                    $where->orWhereRaw(
                        sprintf("%s like ? escape '%s'", $grammar->wrap($field), DataRequest::LIKE_ESCAPE),
                        [$pattern],
                    );
                }
            });
        }

        foreach ($request->filters as $field => $value) {
            $query->where($field, '=', $value);
        }

        foreach ($request->conditions as $condition) {
            if ($condition['operator'] === DataRequest::CONTAINS) {
                $grammar = ($query instanceof EloquentBuilder ? $query->getQuery() : $query)->getGrammar();
                $query->whereRaw(
                    sprintf("%s like ? escape '%s'", $grammar->wrap($condition['field']), DataRequest::LIKE_ESCAPE),
                    [DataRequest::containsPattern($condition['value'])],
                );
            } else {
                $operator = $condition['operator'] === DataRequest::GTE ? '>=' : '<';
                $query->where($condition['field'], $operator, $condition['value']);
            }
        }

        $filtered = (clone $query)->count();

        $totals = [];
        foreach ($request->totals as $field => $function) {
            $totals[$field] = Aggregate::number((clone $query)->{$function}($field));
        }
        $grouped = clone $query;

        foreach ($request->sort as $rule) {
            $query->orderBy($rule['field'], $rule['direction']);
        }

        $rows = [];
        foreach ($query->offset($request->offset())->limit($request->perPage)->get() as $row) {
            $rows[] = $row instanceof Arrayable ? $row->toArray() : (array) $row;
        }

        $groups = $request->groupBy === null ? [] : $this->groups($grouped, $request->groupBy, $request->totals, $rows);

        return new DataResult($rows, $total, $filtered, $request, $totals, $groups);
    }

    /**
     * The groups of the page's rows, counted and totalled in one GROUP BY query.
     *
     * @param array<string, string> $totals
     * @param list<array<string, mixed>> $rows
     * @return list<array{value: mixed, count: int, totals: array<string, int|float|null>}>
     */
    private function groups(EloquentBuilder|QueryBuilder $query, string $field, array $totals, array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = Arr::get($row, $field);
            $values[Aggregate::groupKey($value)] ??= $value;
        }
        if ($values === []) {
            return [];
        }

        $base = clone ($query instanceof EloquentBuilder ? $query->toBase() : $query);
        $grammar = $base->getGrammar();
        $selects = [$grammar->wrap($field) . ' as celerik_group', 'count(*) as celerik_count'];
        $index = 0;
        foreach ($totals as $total => $function) {
            $selects[] = sprintf('%s(%s) as celerik_total_%d', $function, $grammar->wrap($total), $index++);
        }
        $present = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
        $base->reorder()->select([])->selectRaw(implode(', ', $selects))
            ->where(static function (QueryBuilder $where) use ($field, $present, $values): void {
                if ($present !== []) {
                    $where->whereIn($field, $present);
                }
                if (in_array(null, $values, true)) {
                    $where->orWhereNull($field);
                }
            })
            ->groupBy($field);

        $found = [];
        foreach ($base->get() as $result) {
            $result = (array) $result;
            $sums = [];
            $index = 0;
            foreach ($totals as $total => $function) {
                $sums[$total] = Aggregate::number($result['celerik_total_' . $index++]);
            }
            $found[Aggregate::groupKey($result['celerik_group'])] = [
                'count' => (int) $result['celerik_count'],
                'totals' => $sums,
            ];
        }

        $groups = [];
        foreach ($values as $key => $value) {
            $groups[] = ['value' => $value] + ($found[$key] ?? ['count' => 0, 'totals' => []]);
        }

        return $groups;
    }
}
