<?php

declare(strict_types=1);

namespace Caelrik\Ui\Adapters\Symfony;

use Caelrik\Ui\Data\Aggregate;
use Caelrik\Ui\Data\DataRequest;
use Caelrik\Ui\Data\DataResult;
use Caelrik\Ui\Data\DataSourceInterface;
use Caelrik\Ui\Support\Arr;
use Doctrine\ORM\QueryBuilder;

/**
 * Data source for a Doctrine ORM query builder, e.g.
 * `new DoctrineDataSource($repository->createQueryBuilder('u')->select('u.id, u.name, u.email'))`.
 *
 * Plain fields resolve against the root alias (`name` => `u.name`); dotted fields are used
 * as-is (`a.name`). For joined columns shown in a Grid, select them under an alias and map
 * that alias to its DQL path, so the same field name sorts, searches and appears in the rows:
 *
 *     $qb->select('b.id, b.title, a.name AS author')->join('b.author', 'a');
 *     new DoctrineDataSource($qb, fieldMap: ['author' => 'a.name']);
 *
 * Select scalar fields rather than whole entities: rows are returned with getArrayResult().
 */
final class DoctrineDataSource implements DataSourceInterface
{
    /**
     * @param list<string> $searchable Search fields used when the request does not specify any.
     * @param array<string, string> $fieldMap Public field name => DQL path (e.g. 'author' => 'a.name').
     */
    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly array $searchable = [],
        private readonly array $fieldMap = [],
    ) {
    }

    public function fetch(DataRequest $request): DataResult
    {
        $alias = $this->queryBuilder->getRootAliases()[0];

        $total = $this->count($this->queryBuilder, $alias);
        $query = clone $this->queryBuilder;

        $fields = $request->searchFields !== [] ? $request->searchFields : $this->searchable;

        $pattern = $request->likePattern();

        if ($pattern !== null && $fields !== []) {
            $search = $query->expr()->orX();
            foreach ($fields as $field) {
                $search->add(sprintf(
                    "LOWER(%s) LIKE :caelrik_search ESCAPE '%s'",
                    $this->path($alias, $field),
                    DataRequest::LIKE_ESCAPE,
                ));
            }
            $query->andWhere($search)->setParameter('caelrik_search', mb_strtolower($pattern));
        }

        $index = 0;
        foreach ($request->filters as $field => $value) {
            $parameter = 'caelrik_filter_' . $index++;
            $query->andWhere($this->path($alias, $field) . ' = :' . $parameter)->setParameter($parameter, $value);
        }

        foreach ($request->conditions as $condition) {
            $parameter = 'caelrik_filter_' . $index++;
            $path = $this->path($alias, $condition['field']);

            if ($condition['operator'] === DataRequest::CONTAINS) {
                $query->andWhere(sprintf("LOWER(%s) LIKE :%s ESCAPE '%s'", $path, $parameter, DataRequest::LIKE_ESCAPE))
                    ->setParameter($parameter, mb_strtolower(DataRequest::containsPattern($condition['value'])));
            } else {
                $operator = $condition['operator'] === DataRequest::GTE ? '>=' : '<';
                $query->andWhere(sprintf('%s %s :%s', $path, $operator, $parameter))
                    ->setParameter($parameter, $condition['value']);
            }
        }

        $filtered = $this->count($query, $alias);

        $totals = [];
        foreach ($request->totals as $field => $function) {
            $totals[$field] = Aggregate::number($this->aggregates($query)
                ->select(sprintf('%s(%s)', strtoupper($function), $this->path($alias, $field)))
                ->getQuery()
                ->getSingleScalarResult());
        }
        $grouped = clone $query;

        foreach ($request->sort as $rule) {
            $query->addOrderBy($this->path($alias, $rule['field']), $rule['direction']);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->setFirstResult($request->offset())
            ->setMaxResults($request->perPage)
            ->getQuery()
            ->getArrayResult();

        $groups = $request->groupBy === null
            ? []
            : $this->groups($grouped, $alias, $request->groupBy, $request->totals, $rows);

        return new DataResult($rows, $total, $filtered, $request, $totals, $groups);
    }

    /**
     * The groups of the page's rows, counted and totalled in one GROUP BY query.
     *
     * @param array<string, string> $totals
     * @param list<array<string, mixed>> $rows
     * @return list<array{value: mixed, count: int, totals: array<string, int|float|null>}>
     */
    private function groups(QueryBuilder $query, string $alias, string $field, array $totals, array $rows): array
    {
        $values = [];
        foreach ($rows as $row) {
            $value = Arr::get($row, $field);
            $values[Aggregate::groupKey($value)] ??= $value;
        }
        if ($values === []) {
            return [];
        }

        $path = $this->path($alias, $field);
        $base = $this->aggregates($query)
            ->select($path . ' AS caelrik_group')
            ->addSelect('COUNT(DISTINCT ' . $alias . ') AS caelrik_count');
        $index = 0;
        foreach ($totals as $total => $function) {
            $base->addSelect(sprintf(
                '%s(%s) AS caelrik_total_%d',
                strtoupper($function),
                $this->path($alias, $total),
                $index++,
            ));
        }
        $present = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
        $where = $base->expr()->orX();
        if ($present !== []) {
            $where->add($path . ' IN (:caelrik_groups)');
            $base->setParameter('caelrik_groups', $present);
        }
        if (in_array(null, $values, true)) {
            $where->add($path . ' IS NULL');
        }
        $base->andWhere($where)->groupBy($path);

        $found = [];
        /** @var array<string, mixed> $result */
        foreach ($base->getQuery()->getArrayResult() as $result) {
            $sums = [];
            $index = 0;
            foreach ($totals as $total => $function) {
                $sums[$total] = Aggregate::number($result['caelrik_total_' . $index++]);
            }
            $found[Aggregate::groupKey($result['caelrik_group'])] = [
                'count' => (int) $result['caelrik_count'],
                'totals' => $sums,
            ];
        }

        $groups = [];
        foreach ($values as $key => $value) {
            $groups[] = ['value' => $value] + ($found[$key] ?? ['count' => 0, 'totals' => []]);
        }

        return $groups;
    }

    /**
     * The filtered query, unsorted and unpaged, to compute totals on.
     */
    private function aggregates(QueryBuilder $query): QueryBuilder
    {
        return (clone $query)
            ->resetDQLPart('orderBy')
            ->setFirstResult(0)
            ->setMaxResults(null);
    }

    private function count(QueryBuilder $queryBuilder, string $alias): int
    {
        return (int) (clone $queryBuilder)
            ->select('COUNT(DISTINCT ' . $alias . ')')
            ->resetDQLPart('orderBy')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function path(string $alias, string $field): string
    {
        if (isset($this->fieldMap[$field])) {
            return $this->fieldMap[$field];
        }

        return str_contains($field, '.') ? $field : $alias . '.' . $field;
    }
}
