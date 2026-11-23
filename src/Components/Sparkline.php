<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A sparkline: a small chart drawn by PHP as an SVG (no JavaScript, no inline styles), the
 * size of a word, for text, table cells and record views. It is an image named after its
 * values' range ("Revenue, last 7 values: from 8 to 16, lowest 8, highest 16."), or
 * decorative() when the values are written next to it.
 *
 *     Sparkline::make()->label('Revenue')->values([8, 9, 12, 11, 14, 13, 16])->type('area')->markLast();
 */
final class Sparkline extends Component
{
    public const TYPES = ['line', 'area', 'bar'];
    public const SIZES = ['sm', 'md', 'lg'];
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    public const LABELS = [
        'name' => '{label}, last {count} values: from {first} to {last}, lowest {min}, highest {max}.',
    ];

    // The drawing's units: 4 wide for 1 high, the shape of every size.
    private const WIDTH = 100;
    private const HEIGHT = 25;

    private string $label = '';
    private string $type = 'line';
    private string $size = 'md';
    private string $color = 'primary';
    private bool $markLast = false;
    private bool $decorative = false;

    /** @var list<float> */
    private array $values = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'sparkline';
    }

    /**
     * What the values are ("Revenue"): the start of the image's name.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param list<int|float> $values two or more numbers, oldest first
     */
    public function values(array $values): self
    {
        $this->values = self::numbers($values);

        return $this;
    }

    /**
     * line (default), area (a line over a tinted area) or bar.
     */
    public function type(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown sparkline type "%s".', $type));
        }
        $this->type = $type;

        return $this;
    }

    /**
     * sm, md (default) or lg; it scales with the text around it.
     */
    public function size(string $size): self
    {
        if (!in_array($size, self::SIZES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown sparkline size "%s".', $size));
        }
        $this->size = $size;

        return $this;
    }

    /**
     * Bootstrap's colors, kept at 3:1 against the page: primary by default.
     */
    public function color(string $color): self
    {
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown sparkline color "%s".', $color));
        }
        $this->color = $color;

        return $this;
    }

    /**
     * A dot on the last value (line and area).
     */
    public function markLast(bool $mark = true): self
    {
        $this->markLast = $mark;

        return $this;
    }

    /**
     * Hidden from screen readers, when the values are written next to it.
     */
    public function decorative(bool $decorative = true): self
    {
        $this->decorative = $decorative;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown sparkline label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->values === []) {
            throw new LogicException('A sparkline needs its values.');
        }
        if (!$this->decorative && trim($this->label) === '') {
            throw new LogicException('A sparkline needs a label, or decorative() next to its written values.');
        }

        if ($this->type === 'bar') {
            $shapes = $this->bars();
        } else {
            $points = self::points($this->values, self::WIDTH, self::HEIGHT);
            $line = implode(' ', $points);
            $shapes = $this->type === 'area'
                ? Html::tag('polygon', [
                    'class' => 'caelrik-sparkline-area',
                    'points' => '0,' . self::HEIGHT . ' ' . $line . ' ' . self::WIDTH . ',' . self::HEIGHT,
                ], '')
                : '';
            $shapes .= Html::tag('polyline', [
                'class' => 'caelrik-sparkline-line',
                'points' => $line,
                'fill' => 'none',
            ], '');
            if ($this->markLast) {
                [$x, $y] = explode(',', $points[count($points) - 1]);
                $shapes .= Html::tag('circle', [
                    'class' => 'caelrik-sparkline-dot',
                    'cx' => $x,
                    'cy' => $y,
                    'r' => '2.5',
                ], '');
            }
        }

        return Html::tag('svg', array_merge($this->attributes, [
            'id' => $this->id,
            'class' => Html::classes([
                'caelrik-sparkline',
                'caelrik-sparkline-' . $this->size,
                'caelrik-sparkline-' . $this->color,
                ...$this->classes,
            ]),
            'viewBox' => '0 0 ' . self::WIDTH . ' ' . self::HEIGHT,
            'role' => $this->decorative ? null : 'img',
            'aria-label' => $this->decorative
                ? null
                : self::describe($this->labels['name'], $this->label, $this->values),
            'aria-hidden' => $this->decorative ? 'true' : null,
            'focusable' => 'false',
        ]), $shapes);
    }

    /**
     * The points of a line through the values, in a width × height box: a 2-unit margin keeps
     * the stroke inside, and equal values draw a line in the middle.
     *
     * @param list<float> $values
     *
     * @return list<string> "x,y" pairs
     */
    public static function points(array $values, int $width, int $height): array
    {
        $min = min($values);
        $max = max($values);
        $count = count($values);
        $points = [];
        foreach ($values as $index => $value) {
            $x = $index * $width / ($count - 1);
            $y = $max === $min ? $height / 2 : $height - 2 - ($value - $min) / ($max - $min) * ($height - 4);
            $points[] = self::coordinate($x) . ',' . self::coordinate($y);
        }

        return $points;
    }

    /**
     * The name of a sparkline: the template with {label}, {count}, {first}, {last}, {min}, {max}.
     *
     * @param list<float> $values
     */
    public static function describe(string $template, string $label, array $values): string
    {
        return strtr($template, [
            '{label}' => $label,
            '{count}' => (string) count($values),
            '{first}' => self::number($values[0]),
            '{last}' => self::number($values[count($values) - 1]),
            '{min}' => self::number(min($values)),
            '{max}' => self::number(max($values)),
        ]);
    }

    /**
     * @param array<mixed> $values
     *
     * @return list<float>
     */
    public static function numbers(array $values): array
    {
        if (count($values) < 2) {
            throw new InvalidArgumentException('A sparkline needs two values or more.');
        }
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new InvalidArgumentException('A sparkline holds numbers.');
            }
        }

        return array_map('floatval', array_values($values));
    }

    /**
     * Bars from zero (up for positive values, down for negative ones), with a gap between them.
     */
    private function bars(): string
    {
        $low = min(0.0, min($this->values));
        $high = max(0.0, max($this->values));
        $range = $high - $low ?: 1.0;
        $scale = static fn (float $value): float => self::HEIGHT - ($value - $low) / $range * self::HEIGHT;
        $zero = $scale(0.0);
        $slot = self::WIDTH / count($this->values);
        $bars = '';
        foreach ($this->values as $index => $value) {
            $top = min($zero, $scale($value));
            // A zero still shows as a thin bar.
            $height = max(1.0, abs($scale($value) - $zero));
            $bars .= Html::tag('rect', [
                'class' => 'caelrik-sparkline-bar' . ($value < 0 ? ' is-negative' : ''),
                'x' => self::coordinate($index * $slot + $slot * 0.15),
                'y' => self::coordinate($value < 0 ? $zero : min($top, $zero - $height)),
                'width' => self::coordinate($slot * 0.7),
                'height' => self::coordinate($height),
            ], '');
        }

        return $bars;
    }

    private static function number(float $value): string
    {
        $text = number_format($value, 2, '.', ',');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    private static function coordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
