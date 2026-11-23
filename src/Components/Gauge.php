<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A gauge: a value on a scale, as a semicircle (default) or a bar, drawn by PHP as an SVG (no
 * JavaScript, no inline styles). It is a meter (role="meter") named by its label, whose value
 * text gives the value, its band and the target ("72%, High, target 90%").
 * - band(): colored ranges of the scale; the value takes its band's color, and the band's name
 *   is written next to the value, so the color is never the only sign.
 * - target(): a mark across the scale.
 *
 *     Gauge::make('cpu')->label('CPU')->value(72)->format('{value}%')
 *         ->band(0, 60, 'success', 'Normal')->band(60, 85, 'warning', 'High')->band(85, 100, 'danger', 'Critical')
 *         ->target(90);
 */
final class Gauge extends Component
{
    public const SHAPES = ['arc', 'linear'];
    public const SIZES = ['sm', 'md', 'lg'];
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    public const LABELS = ['target' => 'target {target}'];

    // The semicircle: centered at (50, 50), its value arc on radius 38, its bands on radius 47.
    private const ARC = 38;
    private const BANDS = 47;

    private string $label = '';
    private ?float $value = null;
    private float $min = 0.0;
    private float $max = 100.0;
    private ?float $target = null;
    private string $format = '{value}';
    private ?int $decimals = null;
    private string $shape = 'arc';
    private string $size = 'md';
    private string $color = 'primary';
    private bool $scale = true;

    /** @var list<array{from: float, to: float, color: string, label: ?string}> */
    private array $bands = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'gauge';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function value(int|float $value): self
    {
        $this->value = (float) $value;

        return $this;
    }

    /**
     * The scale, 0 to 100 by default.
     */
    public function range(int|float $min, int|float $max): self
    {
        if ($max <= $min) {
            throw new InvalidArgumentException('A gauge\'s maximum is above its minimum.');
        }
        $this->min = (float) $min;
        $this->max = (float) $max;

        return $this;
    }

    public function target(int|float|null $target): self
    {
        $this->target = $target === null ? null : (float) $target;

        return $this;
    }

    /**
     * How numbers are written: "{value}%", "${value}", "{value} ms". "{value}" by default.
     */
    public function format(string $format): self
    {
        if (!str_contains($format, '{value}')) {
            throw new InvalidArgumentException('A gauge format contains {value}.');
        }
        $this->format = $format;

        return $this;
    }

    /**
     * Decimals shown (0 to 4); by default up to 2, without trailing zeros.
     */
    public function decimals(?int $decimals): self
    {
        if ($decimals !== null && ($decimals < 0 || $decimals > 4)) {
            throw new InvalidArgumentException('A gauge shows 0 to 4 decimals.');
        }
        $this->decimals = $decimals;

        return $this;
    }

    /**
     * A colored range of the scale, named for the value text ("High").
     */
    public function band(int|float $from, int|float $to, string $color, ?string $label = null): self
    {
        if ($to <= $from) {
            throw new InvalidArgumentException('A gauge band ends after it starts.');
        }
        $this->bands[] = [
            'from' => (float) $from,
            'to' => (float) $to,
            'color' => self::checkColor($color),
            'label' => $label,
        ];

        return $this;
    }

    /**
     * arc (a semicircle, default) or linear (a bar).
     */
    public function shape(string $shape): self
    {
        if (!in_array($shape, self::SHAPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown gauge shape "%s".', $shape));
        }
        $this->shape = $shape;

        return $this;
    }

    public function linear(bool $linear = true): self
    {
        return $this->shape($linear ? 'linear' : 'arc');
    }

    public function size(string $size): self
    {
        if (!in_array($size, self::SIZES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown gauge size "%s".', $size));
        }
        $this->size = $size;

        return $this;
    }

    /**
     * The value's color outside the bands: primary by default.
     */
    public function color(string $color): self
    {
        $this->color = self::checkColor($color);

        return $this;
    }

    /**
     * The minimum and maximum written at the ends of the scale (shown by default).
     */
    public function scale(bool $scale = true): self
    {
        $this->scale = $scale;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown gauge label "%s".', $key));
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
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Gauge "%s" needs a label.', $this->id));
        }
        if ($this->value === null) {
            throw new LogicException(sprintf('Gauge "%s" needs a value.', $this->id));
        }
        $band = null;
        foreach ($this->bands as $candidate) {
            if ($this->value >= $candidate['from'] && $this->value <= $candidate['to']) {
                $band = $candidate;
                break;
            }
        }
        $color = $band['color'] ?? $this->color;
        $value = $this->text($this->value);
        $valueText = implode(', ', array_filter([
            $value,
            $band['label'] ?? null,
            $this->target === null ? null : strtr($this->labels['target'], ['{target}' => $this->text($this->target)]),
        ], static fn (?string $part): bool => $part !== null && $part !== ''));

        $labelId = $this->id . '-label';
        $label = Html::tag('p', ['class' => 'caelrik-gauge-label', 'id' => $labelId], Html::escape($this->label));
        $meter = Html::tag('div', [
            'class' => 'caelrik-gauge-meter',
            'role' => 'meter',
            'aria-labelledby' => $labelId,
            'aria-valuemin' => self::number($this->min),
            'aria-valuemax' => self::number($this->max),
            'aria-valuenow' => self::number($this->clamp($this->value)),
            'aria-valuetext' => $valueText,
        ], $this->shape === 'arc' ? $this->arc($color) : $this->bar($color));
        // The meter says all this to screen readers.
        $reading = Html::tag(
            'p',
            ['class' => 'caelrik-gauge-reading', 'aria-hidden' => 'true'],
            Html::tag('span', ['class' => 'caelrik-gauge-value'], Html::escape($value))
            . (($band['label'] ?? null) === null
                ? ''
                : ' ' . Html::tag('span', ['class' => 'caelrik-gauge-band'], Html::escape($band['label']))),
        );
        $scale = $this->scale
            ? Html::tag(
                'p',
                ['class' => 'caelrik-gauge-scale', 'aria-hidden' => 'true'],
                Html::tag('span', [], Html::escape($this->text($this->min)))
                . Html::tag('span', [], Html::escape($this->text($this->max))),
            )
            : '';

        $inner = $this->shape === 'arc'
            ? $label . Html::tag('div', ['class' => 'caelrik-gauge-body'], $meter . $reading) . $scale
            : Html::tag('div', ['class' => 'caelrik-gauge-head'], $label . $reading) . $meter . $scale;

        return Html::tag('div', array_merge($this->attributes, [
            'id' => $this->id,
            'class' => Html::classes([
                'caelrik-gauge',
                'caelrik-gauge-' . $this->shape,
                'caelrik-gauge-' . $this->size,
                ...$this->classes,
            ]),
        ]), $inner);
    }

    private function arc(string $color): string
    {
        $svg = Html::tag('path', ['class' => 'caelrik-gauge-track', 'd' => self::arcPath(0.0, 1.0, self::ARC)], '');
        foreach ($this->bands as $band) {
            $svg .= Html::tag('path', [
                'class' => 'caelrik-gauge-range caelrik-gauge-' . $band['color'],
                'd' => self::arcPath($this->fraction($band['from']), $this->fraction($band['to']), self::BANDS),
            ], '');
        }
        $fraction = $this->fraction((float) $this->value);
        if ($fraction > 0) {
            $svg .= Html::tag('path', [
                'class' => 'caelrik-gauge-fill caelrik-gauge-' . $color,
                'd' => self::arcPath(0.0, $fraction, self::ARC),
            ], '');
        }
        if ($this->target !== null) {
            [$x1, $y1] = self::point($this->fraction($this->target), self::ARC - 8);
            [$x2, $y2] = self::point($this->fraction($this->target), self::BANDS + 2);
            $svg .= Html::tag('line', [
                'class' => 'caelrik-gauge-target',
                'x1' => $x1,
                'y1' => $y1,
                'x2' => $x2,
                'y2' => $y2,
            ], '');
        }

        return Html::tag('svg', [
            'class' => 'caelrik-gauge-svg',
            'viewBox' => '0 0 100 52',
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ], $svg);
    }

    private function bar(string $color): string
    {
        $svg = Html::tag('rect', [
            'class' => 'caelrik-gauge-track',
            'x' => '0',
            'y' => '4',
            'width' => '100',
            'height' => '6',
        ], '');
        foreach ($this->bands as $band) {
            $from = $this->fraction($band['from']) * 100;
            $svg .= Html::tag('rect', [
                'class' => 'caelrik-gauge-range caelrik-gauge-' . $band['color'],
                'x' => self::coordinate($from),
                'y' => '0',
                'width' => self::coordinate($this->fraction($band['to']) * 100 - $from),
                'height' => '2',
            ], '');
        }
        $fraction = $this->fraction((float) $this->value);
        if ($fraction > 0) {
            $svg .= Html::tag('rect', [
                'class' => 'caelrik-gauge-fill caelrik-gauge-' . $color,
                'x' => '0',
                'y' => '4',
                'width' => self::coordinate($fraction * 100),
                'height' => '6',
            ], '');
        }
        if ($this->target !== null) {
            $x = self::coordinate($this->fraction($this->target) * 100);
            $svg .= Html::tag('line', [
                'class' => 'caelrik-gauge-target',
                'x1' => $x,
                'y1' => '0',
                'x2' => $x,
                'y2' => '12',
                'vector-effect' => 'non-scaling-stroke',
            ], '');
        }

        return Html::tag('svg', [
            'class' => 'caelrik-gauge-svg',
            'viewBox' => '0 0 100 12',
            'preserveAspectRatio' => 'none',
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ], $svg);
    }

    private function clamp(float $value): float
    {
        return max($this->min, min($this->max, $value));
    }

    private function fraction(float $value): float
    {
        return ($this->clamp($value) - $this->min) / ($this->max - $this->min);
    }

    private function text(float $value): string
    {
        $number = $this->decimals === null
            ? self::number($value, true)
            : number_format($value, $this->decimals, '.', ',');

        return strtr($this->format, ['{value}' => $number]);
    }

    /**
     * The arc from one fraction of the semicircle to another (0 is the left end, 1 the right).
     */
    private static function arcPath(float $from, float $to, int $radius): string
    {
        [$x1, $y1] = self::point($from, $radius);
        [$x2, $y2] = self::point($to, $radius);

        return sprintf('M%s %sA%d %d 0 0 1 %s %s', $x1, $y1, $radius, $radius, $x2, $y2);
    }

    /**
     * @return array{string, string}
     */
    private static function point(float $fraction, int $radius): array
    {
        $angle = M_PI * (1 - $fraction);

        return [self::coordinate(50 + $radius * cos($angle)), self::coordinate(50 - $radius * sin($angle))];
    }

    private static function checkColor(string $color): string
    {
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown gauge color "%s".', $color));
        }

        return $color;
    }

    private static function number(float $value, bool $thousands = false): string
    {
        $text = number_format($value, 2, '.', $thousands ? ',' : '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }

    private static function coordinate(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
