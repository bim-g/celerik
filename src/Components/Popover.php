<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A popover: a button that shows a small panel of content next to it (help, details, a
 * few links). It is a native disclosure (<details>), so it opens without JavaScript; with
 * it, Escape closes it and returns focus to its button, and it closes when focus or a
 * click goes elsewhere. The panel follows the button in the reading and tab order.
 *
 *     Popover::make('pricing-help')->button('How is the price set?')->title('Pricing')
 *         ->content('Prices include taxes and are set per seat.');
 */
final class Popover extends Component
{
    private string $button = '';
    private ?string $buttonLabel = null;
    private string $variant = 'outline-secondary';
    private ?string $title = null;
    private string|Stringable $content = '';
    private string $align = 'start';

    public static function name(): string
    {
        return 'popover';
    }

    /**
     * The button's text; $label names it for screen readers when the text is a symbol ("?").
     */
    public function button(string $text, ?string $label = null): self
    {
        $this->button = $text;
        $this->buttonLabel = $label;

        return $this;
    }

    /**
     * Bootstrap's button variant: outline-secondary (the default), primary, link...
     */
    public function variant(string $variant): self
    {
        $colors = 'primary|secondary|success|danger|warning|info|light|dark';
        if (preg_match('/^(outline-)?(' . $colors . ')$|^link$/', $variant) !== 1) {
            throw new InvalidArgumentException(sprintf('Unknown button variant "%s".', $variant));
        }
        $this->variant = $variant;

        return $this;
    }

    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * The panel's content: a component or Html::raw(); a string is escaped text.
     */
    public function content(string|Stringable $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Aligns the panel with the button's start (the default) or end.
     */
    public function align(string $align): self
    {
        if (!in_array($align, ['start', 'end'], true)) {
            throw new InvalidArgumentException('A popover is aligned to the start or the end.');
        }
        $this->align = $align;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if (trim($this->button) === '') {
            throw new LogicException(sprintf('Popover "%s" needs a button.', $this->id));
        }
        $title = $this->title === null
            ? ''
            : Html::tag(
                'p',
                ['class' => 'caelrik-popover-title', 'id' => $this->id . '-title'],
                Html::escape($this->title),
            );
        $summary = Html::tag('summary', [
            // caelrik-action-button: outline variants keep an AA contrast in dark mode.
            'class' => 'btn btn-sm caelrik-action-button btn-' . $this->variant,
            'aria-label' => $this->buttonLabel,
        ], Html::escape($this->button));
        $panel = Html::tag('div', [
            'class' => 'caelrik-popover-panel',
            'role' => $this->title === null ? null : 'group',
            'aria-labelledby' => $this->title === null ? null : $this->id . '-title',
        ], $title . Html::tag('div', ['class' => 'caelrik-popover-body'], Html::content($this->content)));

        return Html::tag(
            'details',
            $this->rootAttributes(['caelrik-popover', 'caelrik-popover-' . $this->align]),
            $summary . $panel,
        );
    }
}
