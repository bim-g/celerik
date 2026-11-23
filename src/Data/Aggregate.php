<?php

declare(strict_types=1);

namespace Caelrik\Ui\Data;

use InvalidArgumentException;

/**
 * Column totals: the functions a Grid column can total with, how rows of a PHP array are
 * totalled, and how a total is written. resources/js/grid-summary.js does the same in the
 * browser, so both write a total alike.
 */
final class Aggregate
{
    public const SUM = 'sum';
    public const AVG = 'avg';
    public const COUNT = 'count';
    public const MIN = 'min';
    public const MAX = 'max';

    public const FUNCTIONS = [self::SUM, self::AVG, self::COUNT, self::MIN, self::MAX];

    public static function assertFunction(string $function): void
    {
        if (!in_array($function, self::FUNCTIONS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown total "%s". Available: %s.',
                $function,
                implode(', ', self::FUNCTIONS),
            ));
        }
    }

    /**
     * Totals values as SQL does: empty values (null, '') are left out, `count` counts the others,
     * and the other functions only read numbers. With no number, only `sum` and `count` give 0.
     *
     * @param iterable<mixed> $values
     */
    public static function compute(string $function, iterable $values): int|float|null
    {
        $numbers = [];
        $count = 0;
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $count++;
            $number = self::number($value);
            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return match ($function) {
            self::COUNT => $count,
            self::SUM => array_sum($numbers),
            self::AVG => $numbers === [] ? null : array_sum($numbers) / count($numbers),
            self::MIN => $numbers === [] ? null : min($numbers),
            self::MAX => $numbers === [] ? null : max($numbers),
            default => null,
        };
    }

    /**
     * Matches a group's value as a database and PHP may give it: 3 and '3', true and 1, a
     * date and its text are the same group. resources/js/grid-summary.js has the same key.
     */
    public static function groupKey(mixed $value): string
    {
        return match (true) {
            $value === null => "\0",
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => (string) json_encode($value),
        };
    }

    /**
     * A number from PHP or a database ('1250.50', 3, true): int when it has no fraction.
     */
    public static function number(mixed $value): int|float|null
    {
        if (is_bool($value)) {
            return (int) $value;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (!is_string($value) || !is_numeric(trim($value))) {
            return null;
        }
        $number = trim($value) + 0;

        return is_float($number) && floor($number) === $number && abs($number) < PHP_INT_MAX
            ? (int) $number
            : $number;
    }

    /**
     * Writes a total with its separators. Without `$decimals`: none for a count or a whole
     * number, 2 otherwise.
     */
    public static function format(
        int|float|null $value,
        string $function,
        ?int $decimals = null,
        string $decimal = '.',
        string $thousands = ',',
    ): string {
        if ($value === null) {
            return '';
        }
        $decimals ??= match (true) {
            $function === self::COUNT => 0,
            $function === self::AVG => 2,
            is_int($value), floor($value) === $value => 0,
            default => 2,
        };

        return number_format($value, $decimals, $decimal, $thousands);
    }
}
