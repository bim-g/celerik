<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A tooltip: a short text describing a control, shown while it is hovered or focused
 * (the WAI-ARIA tooltip pattern). It meets WCAG 1.4.13: the pointer can move onto it,
 * Escape hides it without moving focus, and it stays until the pointer or focus leaves.
 * The control describes itself with it (aria-describedby) and keeps its own name: a
 * tooltip is extra, never the only name of an icon button. It needs JavaScript; without
 * it, nothing is shown.
 *
 *     Tooltip::make()->text('Saves a draft you can finish later')->trigger($saveButton);
 */
final class Tooltip extends Component
{
    private string $text = '';
    private string|Stringable $trigger = '';
    private string $placement = 'top';

    public static function name(): string
    {
        return 'tooltip';
    }

    public function text(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * The control it describes: a component, Html::raw(), or escaped text (then focusable).
     */
    public function trigger(string|Stringable $trigger): self
    {
        $this->trigger = $trigger;

        return $this;
    }

    /**
     * 'top' (the default) or 'bottom'; it flips when there is no room.
     */
    public function placement(string $placement): self
    {
        if (!in_array($placement, ['top', 'bottom'], true)) {
            throw new InvalidArgumentException('A tooltip is placed at the top or bottom.');
        }
        $this->placement = $placement;

        return $this;
    }

    public function toArray(): array
    {
        return $this->placement === 'top' ? [] : ['placement' => $this->placement];
    }

    public function render(): string
    {
        if (trim($this->text) === '') {
            throw new LogicException('A tooltip needs a text.');
        }
        // Plain text is made focusable, so keyboard users reach its tooltip too.
        $trigger = $this->trigger instanceof Stringable
            ? (string) $this->trigger
            : Html::tag('span', ['class' => 'caelrik-tooltip-text', 'tabindex' => '0'], Html::escape($this->trigger));

        return Html::tag(
            'span',
            $this->rootAttributes(['caelrik-tooltip-wrap']),
            $trigger . Html::tag('span', [
                'class' => 'caelrik-tooltip',
                'id' => $this->id . '-tip',
                'role' => 'tooltip',
                'hidden' => true,
            ], Html::escape($this->text)),
        );
    }
}
