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

        return new DataResult(array_slice($rows, $request->offset(), $request->perPage), $total, $filtered, $request);
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
