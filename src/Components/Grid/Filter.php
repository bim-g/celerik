<?php

declare(strict_types=1);

namespace Celerik\Ui\Components\Grid;

use InvalidArgumentException;

/**
 * A column filter of a Grid, shown in the filter bar above the table:
 *
 * - text(): rows whose value contains the text, ignoring case;
 * - select($options): rows whose value is the chosen option;
 * - boolean(): yes or no, for true/false or 1/0 values;
 * - dateRange(): rows whose date (Y-m-d, or a date and time) is in the range, both days included.
 */
final class Filter
{
    public const TEXT = 'text';
    public const SELECT = 'select';
    public const BOOLEAN = 'boolean';
    public const DATE_RANGE = 'dateRange';

    /**
     * @param array<string, string> $options
     */
    private function __construct(private readonly string $type, private readonly array $options = [])
    {
    }

    public static function text(): self
    {
        return new self(self::TEXT);
    }

    /**
     * @param array<int|string, string> $options Value => label.
     */
    public static function select(array $options): self
    {
        if ($options === []) {
            throw new InvalidArgumentException('A select filter needs at least one option.');
        }

        $normalized = [];
        foreach ($options as $value => $label) {
            $normalized[(string) $value] = (string) $label;
        }

        return new self(self::SELECT, $normalized);
    }

    public static function boolean(): self
    {
        return new self(self::BOOLEAN);
    }

    public static function dateRange(): self
    {
        return new self(self::DATE_RANGE);
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @return array{type: string, options?: list<array{value: string, label: string}>}
     */
    public function toArray(): array
    {
        if ($this->options === []) {
            return ['type' => $this->type];
        }

        $options = [];
        foreach ($this->options as $value => $label) {
            $options[] = ['value' => (string) $value, 'label' => $label];
        }

        return ['type' => $this->type, 'options' => $options];
    }
}
