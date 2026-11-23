<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use Caelrik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A person's picture, or their initials on a color picked from their name (AA contrast).
 * It is an image named after the person ("Ada Lovelace, online"), or decorative() next to
 * their written name. A status is a dot whose shape, not only its color, tells it apart.
 * Plain HTML, no JavaScript.
 *
 *     Avatar::make()->person('Ada Lovelace')->image('/avatars/ada.jpg')->size('lg')->status('online');
 */
final class Avatar extends Component
{
    public const SIZES = ['sm', 'md', 'lg', 'xl'];
    public const STATUSES = ['online', 'away', 'busy', 'offline'];
    public const LABELS = [
        'online' => '{name}, online',
        'away' => '{name}, away',
        'busy' => '{name}, busy',
        'offline' => '{name}, offline',
    ];

    // Bootstrap's text-bg-* pairs keep an AA contrast for the initials; dark and light are
    // left out, as one of them is the page's own color.
    private const COLORS = ['primary', 'success', 'danger', 'info', 'warning', 'secondary'];

    private string $person = '';
    private ?string $image = null;
    private string $size = 'md';
    private ?string $status = null;
    private bool $decorative = false;
    private bool $square = false;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'avatar';
    }

    /**
     * The person's name: the image's name, and where the initials come from.
     */
    public function person(string $name): self
    {
        $this->person = $name;

        return $this;
    }

    /**
     * A picture (relative, http or https; otherwise the initials are shown).
     */
    public function image(?string $url): self
    {
        $this->image = $url === null ? null : Url::safe($url);

        return $this;
    }

    public function size(string $size): self
    {
        if (!in_array($size, self::SIZES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown avatar size "%s".', $size));
        }
        $this->size = $size;

        return $this;
    }

    public function status(?string $status): self
    {
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown avatar status "%s".', $status));
        }
        $this->status = $status;

        return $this;
    }

    /**
     * Hidden from screen readers, when the name is written next to it.
     */
    public function decorative(bool $decorative = true): self
    {
        $this->decorative = $decorative;

        return $this;
    }

    /**
     * Rounded corners instead of a circle (teams, companies).
     */
    public function square(bool $square = true): self
    {
        $this->square = $square;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown avatar label "%s".', $key));
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
        if (trim($this->person) === '') {
            throw new LogicException('An avatar needs the name of its person.');
        }
        $color = self::COLORS[crc32($this->person) % count(self::COLORS)];
        $inner = $this->image !== null
            ? Html::tag('img', [
                'src' => $this->image,
                'alt' => '',
                'class' => 'caelrik-avatar-image',
                'loading' => 'lazy',
            ])
            : Html::tag(
                'span',
                ['class' => 'caelrik-avatar-initials', 'aria-hidden' => 'true'],
                Html::escape(self::initials($this->person)),
            );
        if ($this->status !== null) {
            $inner .= Html::tag('span', [
                'class' => 'caelrik-avatar-status caelrik-avatar-status-' . $this->status,
                'aria-hidden' => 'true',
            ], '');
        }
        $name = $this->status === null
            ? $this->person
            : strtr($this->labels[$this->status], ['{name}' => $this->person]);

        return Html::tag('span', array_merge($this->attributes, [
            'class' => Html::classes([
                'caelrik-avatar',
                'caelrik-avatar-' . $this->size,
                $this->image === null ? 'text-bg-' . $color : null,
                $this->square ? 'caelrik-avatar-square' : null,
                ...$this->classes,
            ]),
            'role' => $this->decorative ? null : 'img',
            'aria-label' => $this->decorative ? null : $name,
            'aria-hidden' => $this->decorative ? 'true' : null,
        ]), $inner);
    }

    /**
     * "Ada Lovelace" gives "AL", "Plato" gives "P".
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = $words[0] ?? '';
        $last = count($words) > 1 ? $words[count($words) - 1] : '';

        return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }
}
