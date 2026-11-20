<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A timeline: an ordered list of events, each with a title, a time (a <time> element for a
 * date), a description, a colored marker and an optional link. The current step is marked
 * with aria-current="step" and a ring. On wide screens, alternate() puts the events on both
 * sides of the line. Plain HTML, no JavaScript.
 *
 *     Timeline::make('order')->label('Order history')
 *         ->event('Order placed', new DateTimeImmutable('2026-11-01 09:30'), 'Paid by card.', color: 'success')
 *         ->event('Shipped', 'Yesterday', current: true);
 */
final class Timeline extends Component
{
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    private ?string $label = null;
    private ?int $headingLevel = null;
    private string $dateFormat = 'M j, Y';
    private bool $alternate = false;

    /**
     * @var list<array{title: string, time: DateTimeInterface|string|null, content: string|Stringable|null,
     *     color: ?string, marker: ?string, url: ?string, current: bool}>
     */
    private array $events = [];

    public static function name(): string
    {
        return 'timeline';
    }

    /**
     * Names the list for screen readers ("Order history, list, 4 items").
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Event titles as headings (h2 to h6), to move from one event to the next; paragraphs by default.
     */
    public function headingLevel(?int $level): self
    {
        if ($level !== null && ($level < 2 || $level > 6)) {
            throw new InvalidArgumentException('A timeline heading is h2 to h6.');
        }
        $this->headingLevel = $level;

        return $this;
    }

    /**
     * The format of dates (PHP's date() letters), "M j, Y" by default.
     */
    public function dateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Events on both sides of the line on wide screens (one side on phones).
     */
    public function alternate(bool $alternate = true): self
    {
        $this->alternate = $alternate;

        return $this;
    }

    /**
     * Adds an event. A date is shown in the date format inside a <time> element; a string
     * ("Yesterday") is shown as it is. The marker is a short decorative text ("✓", "3").
     */
    public function event(
        string $title,
        DateTimeInterface|string|null $time = null,
        string|Stringable|null $content = null,
        ?string $color = null,
        ?string $marker = null,
        ?string $url = null,
        bool $current = false,
    ): self {
        if ($color !== null && !in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown timeline color "%s".', $color));
        }
        if ($current) {
            foreach ($this->events as &$event) {
                $event['current'] = false;
            }
            unset($event);
        }
        $this->events[] = [
            'title' => $title,
            'time' => $time,
            'content' => $content,
            'color' => $color,
            'marker' => $marker,
            'url' => $url === null ? null : Url::safe($url),
            'current' => $current,
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->events === []) {
            throw new LogicException(sprintf('Timeline "%s" has no event.', $this->id));
        }
        $items = '';
        foreach ($this->events as $event) {
            $items .= $this->renderEvent($event);
        }
        $attributes = $this->rootAttributes([
            'celerik-timeline',
            $this->alternate ? 'celerik-timeline-alternate' : null,
        ]);
        $attributes['aria-label'] = $this->label;

        return Html::tag('ol', $attributes, $items);
    }

    /**
     * @param array{title: string, time: DateTimeInterface|string|null, content: string|Stringable|null,
     *     color: ?string, marker: ?string, url: ?string, current: bool} $event
     */
    private function renderEvent(array $event): string
    {
        $title = Html::escape($event['title']);
        if ($event['url'] !== null) {
            $title = Html::tag('a', ['href' => $event['url']], $title);
        }
        $body = Html::tag(
            $this->headingLevel === null ? 'p' : 'h' . $this->headingLevel,
            ['class' => 'celerik-timeline-title'],
            $title,
        );

        $time = $event['time'];
        if ($time instanceof DateTimeInterface) {
            $body .= Html::tag(
                'time',
                ['class' => 'celerik-timeline-time', 'datetime' => $time->format(DateTimeInterface::ATOM)],
                Html::escape($time->format($this->dateFormat)),
            );
        } elseif ($time !== null && $time !== '') {
            $body .= Html::tag('p', ['class' => 'celerik-timeline-time'], Html::escape($time));
        }
        if ($event['content'] !== null) {
            $body .= Html::tag('div', ['class' => 'celerik-timeline-content'], Html::content($event['content']));
        }

        $marker = Html::tag('span', [
            'class' => Html::classes([
                'celerik-timeline-marker',
                $event['color'] !== null ? 'celerik-timeline-marker-' . $event['color'] : null,
                $event['marker'] !== null ? 'has-text' : null,
            ]),
            'aria-hidden' => 'true',
        ], Html::escape($event['marker'] ?? ''));

        return Html::tag('li', [
            'class' => Html::classes(['celerik-timeline-event', $event['current'] ? 'is-current' : null]),
            'aria-current' => $event['current'] ? 'step' : null,
        ], $marker . Html::tag('div', ['class' => 'celerik-timeline-body'], $body));
    }
}
