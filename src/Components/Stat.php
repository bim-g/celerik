<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A stat tile: a label, a value, a trend against a previous period and a sparkline.
 *
 * The trend's direction is said in words ("Up", "Down"), not only by its arrow and color; the
 * sparkline is an SVG drawn by PHP (no JavaScript, no inline styles) whose name gives its
 * values' range. Put several in Stats.
 *
 *     Stat::make('revenue')->label('Revenue')->value('$12,400')
 *         ->trend(12.5, 'vs last month')->sparkline([8, 9, 12, 11, 14, 13, 16]);
 */
final class Stat extends Component
{
    public const LABELS = [
        'up' => 'Up',
        'down' => 'Down',
        'flat' => 'No change',
        'sparkline' => '{label}, last {count} values: from {first} to {last}, lowest {min}, highest {max}.',
    ];

    private const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    private const WIDTH = 100;
    private const HEIGHT = 32;

    private string $label = '';
    private string $value = '';
    private ?string $description = null;
    private ?float $change = null;
    private string $changeText = '';
    private string $changeSuffix = '%';
    private string $good = 'up';
    private ?string $link = null;
    private string $color = 'primary';

    /** @var list<float> */
    private array $sparkline = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'stat';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The value as shown: a string, or a number (thousands separated, up to 2 decimals).
     */
    public function value(string|int|float $value): self
    {
        $this->value = is_string($value) ? $value : self::number($value);

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * The change against a previous period (12.5 for +12.5%), what it compares with, and its
     * unit ('%' by default; '' for a count).
     */
    public function trend(float $change, string $text = '', string $suffix = '%'): self
    {
        $this->change = $change;
        $this->changeText = $text;
        $this->changeSuffix = $suffix;

        return $this;
    }

    /**
     * Which way is good: 'up' (the default; green when it rises) or 'down' (costs, churn,
     * response times: green when it falls).
     */
    public function goodWhen(string $direction): self
    {
        if (!in_array($direction, ['up', 'down'], true)) {
            throw new InvalidArgumentException('A stat is good when it goes up or down.');
        }
        $this->good = $direction;

        return $this;
    }

    /**
     * The last values, oldest first (two or more numbers).
     *
     * @param list<int|float> $values
     */
    public function sparkline(array $values): self
    {
        $this->sparkline = Sparkline::numbers($values);

        return $this;
    }

    /**
     * The sparkline's color (Bootstrap's): primary by default.
     */
    public function color(string $color): self
    {
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown stat color "%s".', $color));
        }
        $this->color = $color;

        return $this;
    }

    /**
     * Makes the label a link whose click area covers the tile (relative, http or https).
     */
    public function link(?string $url): self
    {
        $this->link = $url === null ? null : Url::safe($url);

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown stat label "%s".', $key));
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
            throw new LogicException(sprintf('Stat "%s" needs a label.', $this->id));
        }
        $label = Html::escape($this->label);
        if ($this->link !== null) {
            $label = Html::tag('a', ['href' => $this->link, 'class' => 'stretched-link'], $label);
        }
        $body = Html::tag('p', ['class' => 'celerik-stat-label'], $label)
            . Html::tag('p', ['class' => 'celerik-stat-value'], Html::escape($this->value))
            . $this->renderTrend()
            . ($this->description === null
                ? ''
                : Html::tag('p', ['class' => 'celerik-stat-description'], Html::escape($this->description)))
            . $this->renderSparkline();

        return Html::tag(
            'div',
            $this->rootAttributes(['celerik-stat', 'card', 'celerik-stat-' . $this->color]),
            Html::tag('div', ['class' => 'card-body'], $body),
        );
    }

    private function renderTrend(): string
    {
        if ($this->change === null) {
            return '';
        }
        $direction = $this->change > 0 ? 'up' : ($this->change < 0 ? 'down' : 'flat');
        $mood = $direction === 'flat' ? 'flat' : ($direction === $this->good ? 'good' : 'bad');
        $arrow = ['up' => '▲', 'down' => '▼', 'flat' => '→'][$direction];
        $amount = $direction === 'flat' ? '' : ' ' . self::number(abs($this->change)) . $this->changeSuffix;
        $change = $this->labels[$direction] . $amount;

        return Html::tag(
            'p',
            ['class' => 'celerik-stat-trend celerik-stat-trend-' . $mood],
            Html::tag('span', ['aria-hidden' => 'true', 'class' => 'celerik-stat-arrow'], $arrow) . ' '
                . Html::tag('span', ['class' => 'celerik-stat-change'], Html::escape($change))
                . ($this->changeText === ''
                    ? ''
                    : ' ' . Html::tag('span', ['class' => 'celerik-stat-period'], Html::escape($this->changeText))),
        );
    }

    private function renderSparkline(): string
    {
        if ($this->sparkline === []) {
            return '';
        }
        $line = implode(' ', Sparkline::points($this->sparkline, self::WIDTH, self::HEIGHT));
        $area = '0,' . self::HEIGHT . ' ' . $line . ' ' . self::WIDTH . ',' . self::HEIGHT;
        $name = Sparkline::describe($this->labels['sparkline'], $this->label, $this->sparkline);

        return Html::tag('svg', [
            'class' => 'celerik-stat-sparkline',
            'viewBox' => '0 0 ' . self::WIDTH . ' ' . self::HEIGHT,
            'preserveAspectRatio' => 'none',
            'role' => 'img',
            'aria-label' => $name,
            'focusable' => 'false',
        ], Html::tag('polygon', ['class' => 'celerik-stat-area', 'points' => $area], '')
            . Html::tag('polyline', [
                'class' => 'celerik-stat-line',
                'points' => $line,
                'fill' => 'none',
                'vector-effect' => 'non-scaling-stroke',
            ], ''));
    }

    private static function number(int|float $value): string
    {
        $text = number_format((float) $value, 2, '.', ',');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
