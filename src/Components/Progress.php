<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * Progress: a bar (a native <progress>, sized without inline styles) or a circle (an SVG
 * progressbar), named by its label, its value shown or not. Without a value it is
 * indeterminate: something is going on, how much is not known. Plain HTML, no JavaScript;
 * to move it, set the bar's value, or the circle's aria-valuenow and its arc.
 *
 *     Progress::make()->label('Uploading report.pdf')->value(40)->showValue();
 *     Progress::make()->label('Profile complete')->value(3)->max(4)->circle()->color('success');
 */
final class Progress extends Component
{
    private string $label = '';
    private bool $showLabel = true;
    private ?float $value = null;
    private float $max = 100;
    private bool $showValue = false;
    private bool $circle = false;
    private string $color = 'primary';
    private string $size = 'md';

    public static function name(): string
    {
        return 'progress';
    }

    public function label(string $label, bool $visible = true): self
    {
        $this->label = $label;
        $this->showLabel = $visible;

        return $this;
    }

    /**
     * The value, from 0 to max; null for an indeterminate progress.
     */
    public function value(int|float|null $value): self
    {
        $this->value = $value === null ? null : (float) $value;

        return $this;
    }

    public function max(int|float $max): self
    {
        if ($max <= 0) {
            throw new InvalidArgumentException('A progress maximum is above 0.');
        }
        $this->max = (float) $max;

        return $this;
    }

    /**
     * Shows the percentage.
     */
    public function showValue(bool $show = true): self
    {
        $this->showValue = $show;

        return $this;
    }

    public function circle(bool $circle = true): self
    {
        $this->circle = $circle;

        return $this;
    }

    public function color(string $color): self
    {
        if (!in_array($color, ['primary', 'secondary', 'success', 'danger', 'warning', 'info'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown progress color "%s".', $color));
        }
        $this->color = $color;

        return $this;
    }

    /**
     * sm, md or lg: the bar's thickness, the circle's diameter.
     */
    public function size(string $size): self
    {
        if (!in_array($size, ['sm', 'md', 'lg'], true)) {
            throw new InvalidArgumentException('A progress is sm, md or lg.');
        }
        $this->size = $size;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if (trim($this->label) === '') {
            throw new LogicException('A progress needs a label: what is progressing.');
        }
        $id = $this->id;
        $value = $this->value === null ? null : max(0.0, min($this->max, $this->value));
        $percent = $value === null ? null : (int) round($value / $this->max * 100);

        return $this->circle
            ? $this->renderCircle($id, $value, $percent)
            : $this->renderBar($id, $value, $percent);
    }

    private function renderBar(string $id, ?float $value, ?int $percent): string
    {
        $label = $this->showLabel
            ? $this->labelTag($id)
            : '';
        $shown = $this->showValue && $percent !== null
            ? Html::tag('span', ['class' => 'celerik-progress-value', 'aria-hidden' => 'true'], $percent . '%')
            : '';
        $bar = Html::tag('progress', [
            'class' => 'celerik-progress-bar',
            'id' => $id . '-bar',
            'max' => self::number($this->max),
            'value' => $value === null ? null : self::number($value),
            'aria-labelledby' => $this->showLabel ? $id . '-label' : null,
            'aria-label' => $this->showLabel ? null : $this->label,
        ], $percent === null ? '' : $percent . '%');

        return Html::tag('div', array_merge($this->attributes, [
            'class' => Html::classes([
                'celerik-progress',
                'celerik-progress-' . $this->color,
                'celerik-progress-' . $this->size,
                ...$this->classes,
            ]),
            'id' => $id,
        ]), ($label . $shown === '' ? '' : Html::tag('div', ['class' => 'celerik-progress-text'], $label . $shown))
            . $bar);
    }

    private function renderCircle(string $id, ?float $value, ?int $percent): string
    {
        // A radius of 15.9155 makes the circumference 100: the arc's length is the percentage.
        $circle = static fn (string $class, array $more = []): string => Html::tag('circle', array_merge([
            'class' => $class,
            'cx' => '18',
            'cy' => '18',
            'r' => '15.9155',
            'fill' => 'none',
        ], $more), '');
        $svg = Html::tag(
            'svg',
            ['viewBox' => '0 0 36 36', 'aria-hidden' => 'true', 'focusable' => 'false'],
            $circle('celerik-progress-track')
                . $circle('celerik-progress-arc', [
                    'stroke-dasharray' => ($percent ?? 25) . ' 100',
                    'transform' => 'rotate(-90 18 18)',
                ]),
        );
        $shown = $this->showValue && $percent !== null
            ? Html::tag('span', ['class' => 'celerik-progress-value', 'aria-hidden' => 'true'], $percent . '%')
            : '';
        $label = $this->showLabel
            ? $this->labelTag($id)
            : '';

        return Html::tag('div', array_merge($this->attributes, [
            'class' => Html::classes([
                'celerik-progress-circle',
                'celerik-progress-' . $this->color,
                'celerik-progress-' . $this->size,
                $value === null ? 'is-indeterminate' : null,
                ...$this->classes,
            ]),
            'id' => $id,
        ]), Html::tag('div', [
            'class' => 'celerik-progress-dial',
            'role' => 'progressbar',
            'aria-valuemin' => $value === null ? null : '0',
            'aria-valuemax' => $value === null ? null : self::number($this->max),
            'aria-valuenow' => $value === null ? null : self::number($value),
            'aria-valuetext' => $percent === null ? null : $percent . '%',
            'aria-labelledby' => $this->showLabel ? $id . '-label' : null,
            'aria-label' => $this->showLabel ? null : $this->label,
        ], $svg . $shown) . $label);
    }

    private function labelTag(string $id): string
    {
        return Html::tag(
            'span',
            ['class' => 'celerik-progress-label', 'id' => $id . '-label'],
            Html::escape($this->label),
        );
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
