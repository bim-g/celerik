<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;

/**
 * A badge: a short status or count, in a Bootstrap color with an AA contrast (text-bg-*).
 * A hidden text completes it for screen readers: "3" reads "3 unread messages".
 * Plain HTML, no JavaScript.
 *
 *     Badge::make()->text('3')->color('danger')->pill()->hiddenText('unread messages');
 */
final class Badge extends Component
{
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    private string $text = '';
    private string $color = 'secondary';
    private bool $pill = false;
    private ?string $hiddenText = null;

    public static function name(): string
    {
        return 'badge';
    }

    public function text(string|int $text): self
    {
        $this->text = (string) $text;

        return $this;
    }

    public function color(string $color): self
    {
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown badge color "%s".', $color));
        }
        $this->color = $color;

        return $this;
    }

    public function pill(bool $pill = true): self
    {
        $this->pill = $pill;

        return $this;
    }

    /**
     * Read after the text by screen readers only: what the badge counts or means.
     */
    public function hiddenText(?string $text): self
    {
        $this->hiddenText = $text;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $hidden = $this->hiddenText === null || $this->hiddenText === ''
            ? ''
            : Html::tag('span', ['class' => 'visually-hidden'], ' ' . Html::escape($this->hiddenText));

        return Html::tag('span', array_merge($this->attributes, [
            'class' => Html::classes([
                'badge',
                'text-bg-' . $this->color,
                $this->pill ? 'rounded-pill' : null,
                ...$this->classes,
            ]),
        ]), Html::escape($this->text) . $hidden);
    }
}
