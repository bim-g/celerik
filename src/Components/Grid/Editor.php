<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components\Grid;

use Caelrik\Ui\Data\InvalidCellEdit;
use InvalidArgumentException;

/**
 * How a grid cell is edited in place (Column::editable()): a text, a number, a choice from a
 * list, yes or no, or a date. The browser checks the value before sending it, and
 * Grid::cellEdit() checks it again on the server with the same rules.
 */
final class Editor
{
    public const TEXT = 'text';
    public const NUMBER = 'number';
    public const SELECT = 'select';
    public const BOOLEAN = 'boolean';
    public const DATE = 'date';

    private bool $required = false;
    private ?int $maxLength = null;
    private int|float|null $min = null;
    private int|float|null $max = null;
    private int|float|null $step = null;

    /**
     * @param array<string|int, string> $options
     */
    private function __construct(private readonly string $type, private readonly array $options = [])
    {
    }

    public static function text(?int $maxLength = null): self
    {
        $editor = new self(self::TEXT);
        if ($maxLength !== null && $maxLength < 1) {
            throw new InvalidArgumentException('A text editor allows at least 1 character.');
        }
        $editor->maxLength = $maxLength;

        return $editor;
    }

    public static function number(
        int|float|null $min = null,
        int|float|null $max = null,
        int|float|null $step = null,
    ): self {
        if ($min !== null && $max !== null && $min > $max) {
            throw new InvalidArgumentException('A number editor\'s minimum is above its maximum.');
        }
        if ($step !== null && $step <= 0) {
            throw new InvalidArgumentException('A number editor\'s step is above 0.');
        }
        $editor = new self(self::NUMBER);
        $editor->min = $min;
        $editor->max = $max;
        $editor->step = $step;

        return $editor;
    }

    /**
     * @param array<string|int, string> $options value => label, in the order shown.
     */
    public static function select(array $options): self
    {
        if ($options === []) {
            throw new InvalidArgumentException('A select editor needs options.');
        }

        return new self(self::SELECT, $options);
    }

    public static function boolean(): self
    {
        return (new self(self::BOOLEAN))->required();
    }

    /**
     * A day, written Y-m-d.
     */
    public static function date(): self
    {
        return new self(self::DATE);
    }

    /**
     * A value is needed: the cell cannot be emptied.
     */
    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Reads a value the browser sent, as its column holds it: a trimmed text, an int or a
     * float, an option's value, true or false, or a day written Y-m-d. An empty value is null,
     * unless the editor is required().
     *
     * @param array<string, string> $messages Grid labels: required, invalid, tooLong, tooLow, tooHigh.
     * @throws InvalidCellEdit With a message for the user.
     */
    public function parse(string $field, mixed $raw, array $messages): string|int|float|bool|null
    {
        if (!is_scalar($raw) && $raw !== null) {
            throw new InvalidCellEdit($field, $messages['invalid']);
        }
        $text = trim((string) $raw);
        if ($text === '') {
            if ($this->required) {
                throw new InvalidCellEdit($field, $messages['required']);
            }

            return null;
        }

        return match ($this->type) {
            self::NUMBER => $this->parseNumber($field, $text, $messages),
            // '1' finds the key 1; '01' does not.
            self::SELECT => array_key_exists($text, $this->options)
                ? $text
                : throw new InvalidCellEdit($field, $messages['invalid']),
            self::BOOLEAN => match (strtolower($text)) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => throw new InvalidCellEdit($field, $messages['invalid']),
            },
            self::DATE => self::isDay($text) ? $text : throw new InvalidCellEdit($field, $messages['invalid']),
            default => $this->parseText($field, $text, $messages),
        };
    }

    /**
     * The value an editor starts from, from a row's value: '1' or '0' for yes or no, a day for
     * a date.
     */
    public function editValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? '1' : '0',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            $this->type === self::DATE && is_string($value) => substr($value, 0, 10),
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $options = [];
        foreach ($this->options as $value => $label) {
            // A list of pairs: a JSON object would put numeric keys first.
            $options[] = [(string) $value, $label];
        }

        return array_filter([
            'type' => $this->type,
            'required' => $this->required ?: null,
            'maxLength' => $this->maxLength,
            'min' => $this->min,
            'max' => $this->max,
            'step' => $this->step,
            'options' => $options === [] ? null : $options,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<string, string> $messages
     */
    private function parseText(string $field, string $text, array $messages): string
    {
        if ($this->maxLength !== null && mb_strlen($text) > $this->maxLength) {
            throw new InvalidCellEdit($field, str_replace('{max}', (string) $this->maxLength, $messages['tooLong']));
        }

        return $text;
    }

    /**
     * @param array<string, string> $messages
     */
    private function parseNumber(string $field, string $text, array $messages): int|float
    {
        if (!is_numeric($text)) {
            throw new InvalidCellEdit($field, $messages['invalid']);
        }
        $number = $text + 0;
        if ($this->min !== null && $number < $this->min) {
            throw new InvalidCellEdit($field, str_replace('{min}', (string) $this->min, $messages['tooLow']));
        }
        if ($this->max !== null && $number > $this->max) {
            throw new InvalidCellEdit($field, str_replace('{max}', (string) $this->max, $messages['tooHigh']));
        }

        return $number;
    }

    private static function isDay(string $text): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $text);

        return $date !== false && $date->format('Y-m-d') === $text;
    }
}
