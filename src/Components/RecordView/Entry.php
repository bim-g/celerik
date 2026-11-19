<?php

declare(strict_types=1);

namespace Celerik\Ui\Components\RecordView;

use Celerik\Ui\Support\Arr;
use Celerik\Ui\Support\Dates;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use Closure;
use DateTimeInterface;
use InvalidArgumentException;
use Stringable;

/**
 * One field of a record view: its label and its value, shown as text, a link, a date, a
 * number, a yes or no, a badge, a list or a picture.
 *
 *     Entry::text('name', 'Name');
 *     Entry::date('joined_at', 'Member since', 'j F Y');
 *     Entry::badge('status', 'Status', ['active' => 'success', 'banned' => 'danger']);
 */
final class Entry
{
    private const BADGE_COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    private ?Closure $format = null;
    private ?string $placeholder = null;
    private ?string $help = null;
    private int $span = 1;

    /**
     * @param array<string, mixed> $options
     */
    private function __construct(
        private readonly string $type,
        private readonly string $field,
        private readonly string $label,
        private readonly array $options = [],
    ) {
        if (trim($label) === '') {
            throw new InvalidArgumentException(sprintf('Entry "%s" needs a label.', $field));
        }
    }

    public static function text(string $field, string $label): self
    {
        return new self('text', $field, $label);
    }

    public static function email(string $field, string $label): self
    {
        return new self('email', $field, $label);
    }

    public static function phone(string $field, string $label): self
    {
        return new self('phone', $field, $label);
    }

    /**
     * A link to the value (relative, http or https; other URLs are shown as text), or to
     * $text when given.
     */
    public static function url(string $field, string $label, ?string $text = null): self
    {
        return new self('url', $field, $label, ['text' => $text]);
    }

    /**
     * A date (a DateTimeInterface or an ISO 8601 string), in a <time> element.
     */
    public static function date(string $field, string $label, string $format = 'Y-m-d'): self
    {
        return new self('date', $field, $label, ['format' => $format]);
    }

    public static function dateTime(string $field, string $label, string $format = 'Y-m-d H:i'): self
    {
        return new self('date', $field, $label, ['format' => $format]);
    }

    public static function number(
        string $field,
        string $label,
        int $decimals = 0,
        string $prefix = '',
        string $suffix = '',
        string $decimalSeparator = '.',
        string $thousandsSeparator = ',',
    ): self {
        return new self('number', $field, $label, [
            'decimals' => $decimals,
            'prefix' => $prefix,
            'suffix' => $suffix,
            'point' => $decimalSeparator,
            'thousands' => $thousandsSeparator,
        ]);
    }

    /**
     * Yes or No (the record view's labels), with a mark.
     */
    public static function boolean(string $field, string $label): self
    {
        return new self('boolean', $field, $label);
    }

    /**
     * The value in a badge, colored by value: ['active' => 'success']; others are secondary.
     *
     * @param array<string, string> $colors
     */
    public static function badge(string $field, string $label, array $colors = []): self
    {
        foreach ($colors as $color) {
            if (!in_array($color, self::BADGE_COLORS, true)) {
                throw new InvalidArgumentException(sprintf('Unknown badge color "%s".', $color));
            }
        }

        return new self('badge', $field, $label, ['colors' => $colors]);
    }

    /**
     * A list of values (an array), one per line, or as badges.
     */
    public static function list(string $field, string $label, bool $badges = false): self
    {
        return new self('list', $field, $label, ['badges' => $badges]);
    }

    /**
     * A picture from the value's URL (relative, http or https). Without $alt, it is decorative.
     */
    public static function image(string $field, string $label, string $alt = ''): self
    {
        return new self('image', $field, $label, ['alt' => $alt]);
    }

    /**
     * Shows what the callback returns: a string is escaped text, a Stringable is HTML.
     *
     * @param callable(mixed $value, mixed $record): (string|Stringable) $format
     */
    public function format(callable $format): self
    {
        $this->format = Closure::fromCallable($format);

        return $this;
    }

    /**
     * Shown when the value is empty (null, '' or []), instead of the record view's.
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * A short text under the value.
     */
    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    /**
     * The columns the entry spans in its section (1 by default).
     */
    public function span(int $span): self
    {
        if ($span < 1 || $span > 4) {
            throw new InvalidArgumentException('An entry spans 1 to 4 columns.');
        }
        $this->span = $span;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSpan(): int
    {
        return $this->span;
    }

    public function getHelp(): ?string
    {
        return $this->help;
    }

    /**
     * The value's HTML.
     *
     * @param array<string, string> $labels the record view's labels: empty, yes, no
     */
    public function renderValue(mixed $value, mixed $record, array $labels): string
    {
        if ($value === null || $value === '' || $value === []) {
            if ($this->placeholder !== null) {
                return Html::tag('span', ['class' => 'text-body-secondary'], Html::escape($this->placeholder));
            }

            return '<span class="text-body-secondary" aria-hidden="true">—</span>'
                . Html::tag('span', ['class' => 'visually-hidden'], Html::escape($labels['empty']));
        }
        if ($this->format !== null) {
            return Html::content(($this->format)($value, $record));
        }

        return match ($this->type) {
            'email' => $this->link('mailto:' . Arr::toText($value), Arr::toText($value)),
            'phone' => $this->link('tel:' . preg_replace('/[^0-9+]/', '', Arr::toText($value)), Arr::toText($value)),
            'url' => $this->renderUrl(Arr::toText($value)),
            'date' => $this->renderDate($value),
            'number' => $this->renderNumber($value),
            'boolean' => $this->renderBoolean($value, $labels),
            'badge' => $this->renderBadge(Arr::toText($value)),
            'list' => $this->renderList($value),
            'image' => $this->renderImage(Arr::toText($value)),
            default => Html::escape(Arr::toText($value)),
        };
    }

    private function link(string $href, string $text): string
    {
        return Html::tag('a', ['href' => $href], Html::escape($text));
    }

    private function renderUrl(string $url): string
    {
        $safe = Url::safe($url);
        $text = $this->options['text'] ?? null;
        if ($safe === null) {
            return Html::escape($url);
        }

        return $this->link($safe, is_string($text) ? $text : $url);
    }

    private function renderDate(mixed $value): string
    {
        try {
            $date = Dates::parse($value instanceof DateTimeInterface ? $value : Arr::toText($value));
        } catch (InvalidArgumentException) {
            return Html::escape(Arr::toText($value));
        }
        $machine = Dates::isDateOnly($value) ? $date->format('Y-m-d') : $date->format(DATE_ATOM);

        return Html::tag(
            'time',
            ['datetime' => $machine],
            Html::escape($date->format((string) $this->options['format'])),
        );
    }

    private function renderNumber(mixed $value): string
    {
        if (!is_numeric($value)) {
            return Html::escape(Arr::toText($value));
        }

        return Html::escape(
            $this->options['prefix']
            . number_format(
                (float) $value,
                $this->options['decimals'],
                $this->options['point'],
                $this->options['thousands'],
            )
            . $this->options['suffix'],
        );
    }

    /**
     * @param array<string, string> $labels
     */
    private function renderBoolean(mixed $value, array $labels): string
    {
        $yes = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? (bool) $value;

        return Html::tag('span', ['class' => 'celerik-recordview-' . ($yes ? 'yes' : 'no')], Html::tag(
            'span',
            ['class' => 'celerik-recordview-mark', 'aria-hidden' => 'true'],
            $yes ? '✓' : '✕',
        ) . ' ' . Html::escape($yes ? $labels['yes'] : $labels['no']));
    }

    private function renderBadge(string $text): string
    {
        /** @var array<string, string> $colors */
        $colors = $this->options['colors'];
        $color = $colors[$text] ?? 'secondary';

        return Html::tag('span', ['class' => 'badge text-bg-' . $color], Html::escape($text));
    }

    private function renderList(mixed $value): string
    {
        $items = is_iterable($value) ? $value : [$value];
        $html = '';
        foreach ($items as $item) {
            $text = Arr::toText($item);
            $html .= Html::tag('li', [], $this->options['badges']
                ? Html::tag('span', ['class' => 'badge text-bg-secondary'], Html::escape($text))
                : Html::escape($text));
        }

        return Html::tag(
            'ul',
            ['class' => $this->options['badges'] ? 'celerik-recordview-badges' : 'list-unstyled mb-0'],
            $html,
        );
    }

    private function renderImage(string $url): string
    {
        $safe = Url::safe($url);
        if ($safe === null) {
            return '';
        }

        return Html::tag('img', [
            'src' => $safe,
            'alt' => (string) $this->options['alt'],
            'class' => 'celerik-recordview-image img-fluid rounded',
            'loading' => 'lazy',
        ]);
    }
}
