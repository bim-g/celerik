<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;

/**
 * A skeleton: grey shapes standing for content that is loading (Bootstrap's placeholders).
 * Screen readers hear its status ("Loading profile"), not the shapes; the glow stops for
 * reduced motion. Plain HTML, no JavaScript.
 *
 *     Skeleton::make()->label('Loading profile')->avatar()->heading()->lines(3);
 */
final class Skeleton extends Component
{
    // Line widths, in twelfths, so that lines look like text.
    private const WIDTHS = [12, 10, 11, 8, 9, 7];

    private string $label = 'Loading…';
    private bool $avatar = false;
    private bool $heading = false;
    private bool $image = false;
    private int $lines = 3;
    private bool $glow = true;

    public static function name(): string
    {
        return 'skeleton';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function avatar(bool $avatar = true): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function heading(bool $heading = true): self
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * A picture's place, above the text.
     */
    public function image(bool $image = true): self
    {
        $this->image = $image;

        return $this;
    }

    public function lines(int $lines): self
    {
        if ($lines < 0 || $lines > 20) {
            throw new InvalidArgumentException('A skeleton has 0 to 20 lines.');
        }
        $this->lines = $lines;

        return $this;
    }

    /**
     * Still shapes, without the glow.
     */
    public function still(bool $still = true): self
    {
        $this->glow = !$still;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $shapes = '';
        if ($this->image) {
            $shapes .= '<span class="placeholder celerik-skeleton-image"></span>';
        }
        if ($this->avatar || $this->heading) {
            $shapes .= '<span class="celerik-skeleton-header">'
                . ($this->avatar ? '<span class="placeholder celerik-skeleton-avatar"></span>' : '')
                . ($this->heading ? '<span class="placeholder placeholder-lg col-6"></span>' : '')
                . '</span>';
        }
        for ($line = 0; $line < $this->lines; $line++) {
            // The last line of a paragraph is short.
            $width = $line === $this->lines - 1 && $this->lines > 1 ? 5 : self::WIDTHS[$line % count(self::WIDTHS)];
            $shapes .= '<span class="placeholder col-' . $width . '"></span>';
        }

        return Html::tag('div', array_merge($this->attributes, [
            'class' => Html::classes([
                'celerik-skeleton',
                $this->glow ? 'placeholder-glow' : null,
                ...$this->classes,
            ]),
            'role' => 'status',
        ]), Html::tag('span', ['class' => 'visually-hidden'], Html::escape($this->label))
            . Html::tag('span', ['class' => 'celerik-skeleton-shapes', 'aria-hidden' => 'true'], $shapes));
    }
}
