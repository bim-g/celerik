<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;

/**
 * A loading indicator: Bootstrap's spinner in a status region that says what is loading
 * ("Loading orders"), hidden or shown. The spinner slows down for reduced motion.
 * Plain HTML, no JavaScript.
 *
 *     Loader::make()->label('Loading orders')->size('sm')->showLabel();
 */
final class Loader extends Component
{
    private string $label = 'Loading…';
    private bool $showLabel = false;
    private bool $small = false;
    private bool $grow = false;
    private string $color = 'primary';

    public static function name(): string
    {
        return 'loader';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Shows the label next to the spinner; otherwise only screen readers read it.
     */
    public function showLabel(bool $show = true): self
    {
        $this->showLabel = $show;

        return $this;
    }

    public function size(string $size): self
    {
        if (!in_array($size, ['sm', 'md'], true)) {
            throw new InvalidArgumentException('A loader is sm or md.');
        }
        $this->small = $size === 'sm';

        return $this;
    }

    /**
     * A growing dot instead of a turning ring.
     */
    public function grow(bool $grow = true): self
    {
        $this->grow = $grow;

        return $this;
    }

    public function color(string $color): self
    {
        if (!in_array($color, Badge::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown loader color "%s".', $color));
        }
        $this->color = $color;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $type = $this->grow ? 'spinner-grow' : 'spinner-border';
        $spinner = Html::tag('span', [
            'class' => Html::classes([$type, $this->small ? $type . '-sm' : null, 'text-' . $this->color]),
            'aria-hidden' => 'true',
        ], '');
        $label = Html::tag(
            'span',
            ['class' => $this->showLabel ? 'caelrik-loader-label' : 'visually-hidden'],
            Html::escape($this->label),
        );

        return Html::tag('div', array_merge($this->attributes, [
            'class' => Html::classes(['caelrik-loader', ...$this->classes]),
            'role' => 'status',
        ]), $spinner . $label);
    }
}
