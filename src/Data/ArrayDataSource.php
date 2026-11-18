<?php

declare(strict_types=1);

namespace Celerik\Ui\Data;

use Celerik\Ui\Support\Arr;

/**
 * In-memory data source for plain PHP arrays (framework-free apps, fixtures, tests).
 */
final class ArrayDataSource implements DataSourceInterface
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(private readonly array $rows)
    {
    }

    public function fetch(DataRequest $request): DataResult
    {
        $rows = array_values($this->rows);
        $total = count($rows);

        foreach ($request->filters as $field => $value) {
            $rows = array_filter($rows, static fn (array $row): bool => self::equals(Arr::get($row, $field), $value));
        }

        foreach ($request->conditions as $condition) {
            $rows = array_filter(
                $rows,
                static fn (array $row): bool => self::meets(Arr::get($row, $condition['field']), $condition),
            );
        }

        if ($request->search !== null) {
            $fields = $request->searchFields !== []
                ? $request->searchFields
                : array_keys($rows[array_key_first($rows)] ?? []);
            $rows = array_filter($rows, static function (array $row) use ($fields, $request): bool {
                foreach ($fields as $field) {
                    if (mb_stripos(Arr::toText(Arr::get($row, (string) $field)), (string) $request->search) !== false) {
                        return true;
                    }
                }

                return false;
            });
        }

        $rows = array_values($rows);
        $filtered = count($rows);

        if ($request->sort !== []) {
            usort($rows, static function (array $a, array $b) use ($request): int {
                foreach ($request->sort as $rule) {
                    $result = self::compare(Arr::get($a, $rule['field']), Arr::get($b, $rule['field']));

                    if ($result !== 0) {
                        return $rule['direction'] === 'desc' ? -$result : $result;
                    }
                }

                return 0;
            });
        }

        $page = array_slice($rows, $request->offset(), $request->perPage);

        return new DataResult(
            $page,
            $total,
            $filtered,
            $request,
            self::totals($request->totals, $rows),
            $request->groupBy === null ? [] : self::groups($request->groupBy, $request->totals, $rows, $page),
        );
    }

    /**
     * @param array<string, string> $totals
     * @param list<array<string, mixed>> $rows
     * @return array<string, int|float|null>
     */
    private static function totals(array $totals, array $rows): array
    {
        $values = [];
        foreach ($totals as $field => $function) {
            $values[$field] = Aggregate::compute(
                $function,
                array_map(static fn (array $row): mixed => Arr::get($row, $field), $rows),
            );
        }

        return $values;
    }

    /**
     * The groups of the page's rows, each counted and totalled over all the filtered rows.
     *
     * @param array<string, string> $totals
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, mixed>> $page
     * @return list<array{value: mixed, count: int, totals: array<string, int|float|null>}>
     */
    private static function groups(string $field, array $totals, array $rows, array $page): array
    {
        $members = [];
        foreach ($rows as $row) {
            $members[Aggregate::groupKey(Arr::get($row, $field))][] = $row;
        }

        $groups = [];
        foreach ($page as $row) {
            $value = self::groupValue(Arr::get($row, $field));
            $key = Aggregate::groupKey($value);
            if (isset($groups[$key])) {
                continue;
            }
            $groups[$key] = [
                'value' => $value,
                'count' => count($members[$key]),
                'totals' => self::totals($totals, $members[$key]),
            ];
        }

        return array_values($groups);
    }

    private static function groupValue(mixed $value): mixed
    {
        return match (true) {
            $value === null, is_scalar($value) => $value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => Arr::toText($value),
        };
    }

    /**
     * A true/false value equals "1"/"0" (a yes/no filter) as well as "Yes"/"No".
     */
    private static function equals(mixed $value, string $filter): bool
    {
        if (is_bool($value) && ($filter === '1' || $filter === '0')) {
            return $value === ($filter === '1');
        }

        return Arr::toText($value) === $filter;
    }

    /**
     * @param array{field: string, operator: string, value: string} $condition
     */
    private static function meets(mixed $value, array $condition): bool
    {
        $text = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : Arr::toText($value);

        return match ($condition['operator']) {
            DataRequest::CONTAINS => mb_stripos($text, $condition['value']) !== false,
            // Dates written Y-m-d (with a time or not) compare as text.
            DataRequest::GTE => $text !== '' && strcmp($text, $condition['value']) >= 0,
            DataRequest::LT => $text !== '' && strcmp($text, $condition['value']) < 0,
            default => true,
        };
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }

        return strnatcasecmp(Arr::toText($a), Arr::toText($b));
    }
}
