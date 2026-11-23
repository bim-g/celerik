<?php

declare(strict_types=1);

namespace Caelrik\Ui\Data;

/**
 * Normalized paging, sorting, search and filter parameters.
 *
 * Built from query parameters in either of two formats:
 * - DataTables server-side protocol: draw, start, length, order[i][column|dir], columns[i][data], search[value]
 * - Caelrik simple format: page, per_page, sort=name,-created_at, search, filter[field]=value
 *
 * Typed filters (a Grid's column filters) are read from filter[field] in both formats:
 * filter[name]=ada (text), filter[status]=active (exact), filter[verified]=1 (boolean),
 * filter[created_at][from]=2026-01-01&filter[created_at][to]=2026-01-31 (date range).
 */
final class DataRequest
{
    /** A plain field (`email`) or one level of relation (`author.name`). */
    public const FIELD_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';
    public const MAX_PER_PAGE = 1000;
    public const MAX_SEARCH_LENGTH = 255;

    /**
     * Escape character for SQL LIKE. Pass it explicitly (`LIKE ? ESCAPE '!'`): SQLite and
     * SQL Server have no default escape character, and `!` needs no quoting in any dialect.
     */
    public const LIKE_ESCAPE = '!';

    /** Filter types for fromArray()'s $filterTypes. */
    public const FILTER_TEXT = 'text';
    public const FILTER_EXACT = 'exact';
    public const FILTER_BOOLEAN = 'boolean';
    public const FILTER_DATE_RANGE = 'dateRange';

    /** Condition operators: contains the text (ignoring case), on or after, before. */
    public const CONTAINS = 'contains';
    public const GTE = 'gte';
    public const LT = 'lt';

    /**
     * @param list<array{field: string, direction: 'asc'|'desc'}> $sort
     * @param array<string, string> $filters Exact-match filters, field => value.
     * @param list<string> $searchFields Fields the search term applies to; empty lets the source decide.
     * @param list<array{field: string, operator: 'contains'|'gte'|'lt', value: string}> $conditions
     *     Typed filters: `contains` (ignoring case), `gte` (on or after) and `lt` (before). A date
     *     range's last day is included: its `lt` value is the next day.
     * @param array<string, string> $totals Column totals, field => function (see Aggregate), over
     *     every row that passes the search and filters.
     * @param string|null $groupBy The field rows are grouped by: rows are sorted by it first, and
     *     the source counts and totals the groups on the page.
     */
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 10,
        public readonly array $sort = [],
        public readonly ?string $search = null,
        public readonly array $filters = [],
        public readonly array $searchFields = [],
        public readonly int $draw = 0,
        public readonly array $conditions = [],
        public readonly array $totals = [],
        public readonly ?string $groupBy = null,
    ) {
    }

    /**
     * @param array<string, mixed> $query Usually `$_GET` or `$request->query()`.
     * @param list<string>|null $sortable Fields the client may sort on; null allows any valid field name.
     * @param list<string>|null $searchable Fields searched and allowed as filters; null allows any valid field name.
     * @param array<string, 'text'|'exact'|'boolean'|'dateRange'> $filterTypes Typed filters, field => type.
     *     These fields are filtered only as their type says, whether searchable or not.
     * @param array<string, string> $totals Column totals the source computes, field => function.
     * @param string|null $groupBy A field to group by: the sort starts with it, ascending unless
     *     the client sorts it descending first.
     */
    public static function fromArray(
        array $query,
        ?array $sortable = null,
        ?array $searchable = null,
        int $defaultPerPage = 10,
        array $filterTypes = [],
        array $totals = [],
        ?string $groupBy = null,
    ): self {
        $isDataTables = isset($query['draw']) || isset($query['start']);

        if ($isDataTables) {
            $length = self::int($query['length'] ?? $defaultPerPage);
            $perPage = self::clamp($length === -1 ? self::MAX_PER_PAGE : $length);
            $page = intdiv(max(0, self::int($query['start'] ?? 0)), $perPage) + 1;
            $columns = is_array($query['columns'] ?? null) ? $query['columns'] : [];
            $sort = self::dataTablesSort($query['order'] ?? [], $columns);
            $search = self::search(is_array($query['search'] ?? null) ? ($query['search']['value'] ?? null) : null);
            $filters = self::dataTablesFilters($columns);
            $typed = is_array($query['filter'] ?? null) ? $query['filter'] : [];
        } else {
            $perPage = self::clamp(self::int($query['per_page'] ?? $defaultPerPage));
            $page = max(1, self::int($query['page'] ?? 1));
            $sort = self::simpleSort($query['sort'] ?? null);
            $search = self::search($query['search'] ?? null);
            $filters = is_array($query['filter'] ?? null) ? $query['filter'] : [];
            $typed = $filters;
        }

        $sort = array_values(array_filter(
            $sort,
            static fn (array $rule): bool => self::isAllowed($rule['field'], $sortable),
        ));

        if ($groupBy !== null && self::isValidField($groupBy)) {
            $first = $sort[0] ?? null;
            $direction = $first !== null && $first['field'] === $groupBy ? $first['direction'] : 'asc';
            $sort = [
                ['field' => $groupBy, 'direction' => $direction],
                ...array_values(array_filter($sort, static fn (array $rule): bool => $rule['field'] !== $groupBy)),
            ];
        } else {
            $groupBy = null;
        }

        $cleanTotals = [];
        foreach ($totals as $field => $function) {
            if (is_string($field) && self::isValidField($field) && in_array($function, Aggregate::FUNCTIONS, true)) {
                $cleanTotals[$field] = $function;
            }
        }

        $cleanFilters = [];
        foreach ($filters as $field => $value) {
            if (!is_string($field) || isset($filterTypes[$field]) || !is_scalar($value) || (string) $value === '') {
                continue;
            }

            if (self::isAllowed($field, $searchable)) {
                $cleanFilters[$field] = (string) $value;
            }
        }

        $conditions = [];
        foreach ($filterTypes as $field => $type) {
            if (!self::isValidField($field) || !array_key_exists($field, $typed)) {
                continue;
            }

            $value = $typed[$field];

            if ($type === self::FILTER_DATE_RANGE) {
                $range = is_array($value) ? $value : [];
                $from = self::date($range['from'] ?? null);
                $to = self::date($range['to'] ?? null);
                if ($from !== null) {
                    $conditions[] = ['field' => $field, 'operator' => self::GTE, 'value' => $from->format('Y-m-d')];
                }
                if ($to !== null) {
                    $next = $to->modify('+1 day')->format('Y-m-d');
                    $conditions[] = ['field' => $field, 'operator' => self::LT, 'value' => $next];
                }
                continue;
            }

            $text = self::search($value);
            if ($text === null) {
                continue;
            }

            if ($type === self::FILTER_TEXT) {
                $conditions[] = ['field' => $field, 'operator' => self::CONTAINS, 'value' => $text];
            } elseif ($type === self::FILTER_EXACT) {
                $cleanFilters[$field] = $text;
            } elseif ($type === self::FILTER_BOOLEAN && in_array($text, ['0', '1'], true)) {
                $cleanFilters[$field] = $text;
            }
        }

        return new self(
            page: $page,
            perPage: $perPage,
            sort: $sort,
            search: $search,
            filters: $cleanFilters,
            searchFields: $searchable ?? [],
            draw: max(0, self::int($query['draw'] ?? 0)),
            conditions: $conditions,
            totals: $cleanTotals,
            groupBy: $groupBy,
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /**
     * The search term as a "contains" LIKE pattern, with `%` and `_` matched literally.
     * Use it with an explicit escape clause: `LIKE ? ESCAPE '!'` (see LIKE_ESCAPE).
     */
    public function likePattern(): ?string
    {
        if ($this->search === null) {
            return null;
        }

        return self::containsPattern($this->search);
    }

    /**
     * A "contains" LIKE pattern for a text (the search term, or a `contains` condition's value),
     * with `%` and `_` matched literally. Use it with `LIKE ? ESCAPE '!'`.
     */
    public static function containsPattern(string $value): string
    {
        $escape = self::LIKE_ESCAPE;
        $escaped = str_replace(
            [$escape, '%', '_'],
            [$escape . $escape, $escape . '%', $escape . '_'],
            $value,
        );

        return '%' . $escaped . '%';
    }

    public static function isValidField(string $field): bool
    {
        return preg_match(self::FIELD_PATTERN, $field) === 1;
    }

    /**
     * @param list<string>|null $whitelist
     */
    private static function isAllowed(string $field, ?array $whitelist): bool
    {
        return self::isValidField($field) && ($whitelist === null || in_array($field, $whitelist, true));
    }

    /**
     * @param mixed $order
     * @param array<array-key, mixed> $columns
     * @return list<array{field: string, direction: 'asc'|'desc'}>
     */
    private static function dataTablesSort(mixed $order, array $columns): array
    {
        $sort = [];

        foreach (is_array($order) ? $order : [] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $column = $columns[self::int($rule['column'] ?? -1)] ?? null;
            $field = is_array($column) ? ($column['data'] ?? null) : null;

            if (is_string($field)) {
                $sort[] = ['field' => $field, 'direction' => self::direction($rule['dir'] ?? 'asc')];
            }
        }

        return $sort;
    }

    /**
     * @param array<array-key, mixed> $columns
     * @return array<string, mixed>
     */
    private static function dataTablesFilters(array $columns): array
    {
        $filters = [];

        foreach ($columns as $column) {
            if (!is_array($column) || !is_array($column['search'] ?? null)) {
                continue;
            }

            $field = $column['data'] ?? null;
            $value = $column['search']['value'] ?? null;

            if (is_string($field) && is_scalar($value)) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    /**
     * @return list<array{field: string, direction: 'asc'|'desc'}>
     */
    private static function simpleSort(mixed $sort): array
    {
        if (!is_string($sort) || $sort === '') {
            return [];
        }

        $rules = [];

        foreach (explode(',', $sort) as $field) {
            $field = trim($field);
            $descending = str_starts_with($field, '-');
            $rules[] = ['field' => ltrim($field, '-+'), 'direction' => $descending ? 'desc' : 'asc'];
        }

        return $rules;
    }

    /**
     * @return 'asc'|'desc'
     */
    private static function direction(mixed $direction): string
    {
        return is_string($direction) && strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private static function search(mixed $search): ?string
    {
        if (!is_scalar($search)) {
            return null;
        }

        $search = trim((string) $search);

        return $search === '' ? null : mb_substr($search, 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * A real calendar day written Y-m-d, or null.
     */
    private static function date(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    private static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function clamp(int $perPage): int
    {
        return max(1, min(self::MAX_PER_PAGE, $perPage));
    }
}
