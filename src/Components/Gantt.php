<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;

/**
 * A Gantt chart: a table of tasks (name, start, end, progress, what each depends on) whose last
 * column is the timeline. Every row draws its own bar as an SVG sized by attributes (no inline
 * styles), so bars always line up with their rows; the table says everything the bars show.
 * - Milestones are diamonds; "Milestone" is written in their progress cell.
 * - The timeline has a scale (day, week or month), a line for today and, with JavaScript,
 *   arrows from each task to the tasks that depend on it. It scrolls sideways, task names stay.
 *
 *     Gantt::make('launch')->label('Product launch')->scale('week')
 *         ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100)
 *         ->task('build', 'Build', '2026-11-16', '2026-12-11', progress: 40, depends: ['design'])
 *         ->milestone('release', 'Release', '2026-12-14', depends: ['build']);
 */
final class Gantt extends Component
{
    public const SCALES = ['day', 'week', 'month'];
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    public const LABELS = [
        'task' => 'Task',
        'start' => 'Start',
        'end' => 'End',
        'progress' => 'Progress',
        'depends' => 'Depends on',
        'timeline' => 'Timeline',
        'milestone' => 'Milestone',
    ];

    // Pixels per day for each scale; the height of a row's drawing and of the scale.
    private const DAY_WIDTH = ['day' => 28, 'week' => 10, 'month' => 3];
    private const ROW = 28;
    private const HEAD = 40;

    private string $label = '';
    private string $scale = 'week';
    private string $dateFormat = 'M j, Y';
    private ?DateTimeImmutable $today = null;
    private bool $showToday = true;

    /**
     * @var array<string, array{name: string, start: DateTimeImmutable, end: DateTimeImmutable, progress: ?int,
     *     depends: list<string>, color: string, milestone: bool}>
     */
    private array $tasks = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'gantt';
    }

    /**
     * Names the chart: the table's caption and its scrolling region.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * day, week (default) or month: the width of a day and the scale's marks.
     */
    public function scale(string $scale): self
    {
        if (!in_array($scale, self::SCALES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Gantt scale "%s".', $scale));
        }
        $this->scale = $scale;

        return $this;
    }

    /**
     * The format of the start and end dates (PHP's date() letters), "M j, Y" by default.
     */
    public function dateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * The day of the "today" line (the current day by default); null hides it.
     */
    public function today(DateTimeInterface|string|null $today): self
    {
        $this->today = $today === null ? null : self::date($today);
        $this->showToday = $today !== null;

        return $this;
    }

    /**
     * A task from $start to $end, both days included ("Y-m-d" or a date).
     *
     * @param list<string> $depends ids of the tasks it waits for
     */
    public function task(
        string $id,
        string $name,
        DateTimeInterface|string $start,
        DateTimeInterface|string $end,
        ?int $progress = null,
        array $depends = [],
        string $color = 'primary',
    ): self {
        $start = self::date($start);
        $end = self::date($end);
        if ($end < $start) {
            throw new InvalidArgumentException(sprintf('Task "%s" ends before it starts.', $id));
        }
        if ($progress !== null && ($progress < 0 || $progress > 100)) {
            throw new InvalidArgumentException('A task\'s progress is 0 to 100.');
        }

        return $this->add($id, $name, $start, $end, $progress, $depends, $color, false);
    }

    /**
     * A milestone: one day, drawn as a diamond.
     *
     * @param list<string> $depends
     */
    public function milestone(
        string $id,
        string $name,
        DateTimeInterface|string $date,
        array $depends = [],
        string $color = 'primary',
    ): self {
        $date = self::date($date);

        return $this->add($id, $name, $date, $date, null, $depends, $color, true);
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown Gantt label "%s".', $key));
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
        if ($this->tasks === []) {
            throw new LogicException(sprintf('Gantt "%s" has no task.', $this->id));
        }
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Gantt "%s" needs a label.', $this->id));
        }
        foreach ($this->tasks as $id => $task) {
            foreach ($task['depends'] as $depend) {
                if (!isset($this->tasks[$depend])) {
                    throw new LogicException(sprintf('Task "%s" depends on an unknown task "%s".', $id, $depend));
                }
            }
        }
        [$from, $to] = $this->range();
        $days = self::days($from, $to) + 1;
        $width = $days * self::DAY_WIDTH[$this->scale];
        $grid = $this->gridLines($from, $to);

        $labelId = $this->id . '-label';
        $head = Html::tag('tr', [], Html::tag('th', ['scope' => 'col'], Html::escape($this->labels['task']))
            . Html::tag('th', ['scope' => 'col'], Html::escape($this->labels['start']))
            . Html::tag('th', ['scope' => 'col'], Html::escape($this->labels['end']))
            . Html::tag('th', ['scope' => 'col'], Html::escape($this->labels['progress']))
            . Html::tag('th', ['scope' => 'col'], Html::escape($this->labels['depends']))
            . Html::tag(
                'th',
                ['scope' => 'col', 'class' => 'caelrik-gantt-timeline'],
                Html::tag('span', ['class' => 'visually-hidden'], Html::escape($this->labels['timeline']))
                . $this->scaleSvg($from, $to, $width),
            ));

        $rows = '';
        foreach ($this->tasks as $id => $task) {
            $rows .= $this->row($id, $task, $from, $width, $grid);
        }

        // The name stays above the scrolling region: it names the region and the table.
        $table = Html::tag(
            'table',
            ['class' => 'caelrik-gantt-table', 'aria-labelledby' => $labelId],
            Html::tag('thead', [], $head) . Html::tag('tbody', [], $rows),
        );

        return Html::tag('div', $this->rootAttributes([
            'caelrik-gantt',
            'caelrik-gantt-' . $this->scale,
        ]), Html::tag('p', ['id' => $labelId, 'class' => 'caelrik-gantt-label'], Html::escape($this->label))
            . Html::tag('div', [
                'class' => 'caelrik-gantt-scroll',
                'role' => 'region',
                'aria-labelledby' => $labelId,
                'tabindex' => '0',
            ], Html::tag('div', ['class' => 'caelrik-gantt-canvas'], $table)));
    }

    /**
     * @param list<string> $depends
     */
    private function add(
        string $id,
        string $name,
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        ?int $progress,
        array $depends,
        string $color,
        bool $milestone,
    ): self {
        if (isset($this->tasks[$id])) {
            throw new InvalidArgumentException(sprintf('There is already a task "%s".', $id));
        }
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown Gantt color "%s".', $color));
        }
        $this->tasks[$id] = [
            'name' => $name,
            'start' => $start,
            'end' => $end,
            'progress' => $progress,
            'depends' => array_values($depends),
            'color' => $color,
            'milestone' => $milestone,
        ];

        return $this;
    }

    /**
     * @param array{name: string, start: DateTimeImmutable, end: DateTimeImmutable, progress: ?int,
     *     depends: list<string>, color: string, milestone: bool} $task
     */
    private function row(string $id, array $task, DateTimeImmutable $from, int $width, string $grid): string
    {
        $dayWidth = self::DAY_WIDTH[$this->scale];
        $x = self::days($from, $task['start']) * $dayWidth;
        $length = (self::days($task['start'], $task['end']) + 1) * $dayWidth;
        $class = 'caelrik-gantt-' . $task['color'];

        if ($task['milestone']) {
            $cx = $x + $dayWidth / 2;
            $mid = self::ROW / 2;
            $shape = Html::tag('polygon', [
                'class' => 'caelrik-gantt-milestone ' . $class,
                'points' => sprintf(
                    '%s,%d %s,%d %s,%d %s,%d',
                    self::n($cx),
                    $mid - 8,
                    self::n($cx + 8),
                    $mid,
                    self::n($cx),
                    $mid + 8,
                    self::n($cx - 8),
                    $mid,
                ),
                'data-task' => $id,
            ], '');
        } else {
            $shape = Html::tag('rect', [
                'class' => 'caelrik-gantt-bar ' . $class,
                'x' => self::n($x + 1),
                'y' => '6',
                'width' => self::n(max(2, $length - 2)),
                'height' => (string) (self::ROW - 12),
                'rx' => '3',
                'data-task' => $id,
            ], '');
            if (($task['progress'] ?? 0) > 0) {
                $shape .= Html::tag('rect', [
                    'class' => 'caelrik-gantt-done ' . $class,
                    'x' => self::n($x + 1),
                    'y' => '6',
                    'width' => self::n(max(2, $length - 2) * $task['progress'] / 100),
                    'height' => (string) (self::ROW - 12),
                    'rx' => '3',
                ], '');
            }
        }

        $names = array_map(fn (string $depend): string => $this->tasks[$depend]['name'], $task['depends']);
        $progress = $task['milestone']
            ? Html::escape($this->labels['milestone'])
            : ($task['progress'] === null ? '' : $task['progress'] . '%');

        return Html::tag('tr', [
            'class' => $task['milestone'] ? 'is-milestone' : null,
            'data-task' => $id,
            'data-depends' => $task['depends'] === [] ? null : implode(' ', $task['depends']),
        ], Html::tag('th', ['scope' => 'row', 'class' => 'caelrik-gantt-name'], Html::escape($task['name']))
            . Html::tag('td', [], $this->time($task['start']))
            . Html::tag('td', [], $this->time($task['end']))
            . Html::tag('td', [], $progress)
            . Html::tag('td', [], Html::escape(implode(', ', $names)))
            . Html::tag('td', ['class' => 'caelrik-gantt-timeline'], Html::tag('svg', [
                'class' => 'caelrik-gantt-row',
                'width' => (string) $width,
                'height' => (string) self::ROW,
                'viewBox' => '0 0 ' . $width . ' ' . self::ROW,
                'aria-hidden' => 'true',
                'focusable' => 'false',
            ], $grid . $this->todayLine($from, $width, self::ROW) . $shape)));
    }

    private function scaleSvg(DateTimeImmutable $from, DateTimeImmutable $to, int $width): string
    {
        $dayWidth = self::DAY_WIDTH[$this->scale];
        $top = '';
        $bottom = '';
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $x = self::days($from, $day) * $dayWidth;
            $first = $day == $from;
            // The upper row: months (years on the month scale).
            $topStart = $this->scale === 'month' ? $day->format('z') === '0' : $day->format('j') === '1';
            // A name that would be cut at the right end is left out.
            if (($topStart || $first) && ($first || $x + 60 <= $width)) {
                $top .= Html::tag('text', ['x' => (string) ($x + 4), 'y' => '15'], Html::escape(
                    $day->format($this->scale === 'month' ? 'Y' : 'M Y'),
                ));
            }
            // The lower row: days, weeks (from Monday) or months.
            $text = match ($this->scale) {
                'day' => $day->format('j'),
                'week' => $day->format('N') === '1' ? $day->format('M j') : null,
                'month' => $day->format('j') === '1' ? $day->format('M') : null,
            };
            if ($text !== null) {
                $bottom .= Html::tag('text', [
                    'x' => (string) ($this->scale === 'day' ? $x + $dayWidth / 2 : $x + 4),
                    'y' => '33',
                    'text-anchor' => $this->scale === 'day' ? 'middle' : null,
                ], Html::escape($text));
            }
        }

        return Html::tag('svg', [
            'class' => 'caelrik-gantt-scale',
            'width' => (string) $width,
            'height' => (string) self::HEAD,
            'viewBox' => '0 0 ' . $width . ' ' . self::HEAD,
            'aria-hidden' => 'true',
            'focusable' => 'false',
        ], $this->todayLine($from, $width, self::HEAD) . $top . $bottom);
    }

    /**
     * The lines between days, weeks or months, and the weekends on the day scale: the same in every row.
     */
    private function gridLines(DateTimeImmutable $from, DateTimeImmutable $to): string
    {
        $dayWidth = self::DAY_WIDTH[$this->scale];
        $lines = '';
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $x = self::days($from, $day) * $dayWidth;
            if ($this->scale === 'day' && (int) $day->format('N') >= 6) {
                $lines .= Html::tag('rect', [
                    'class' => 'caelrik-gantt-weekend',
                    'x' => (string) $x,
                    'y' => '0',
                    'width' => (string) $dayWidth,
                    'height' => (string) self::ROW,
                ], '');
            }
            $boundary = match ($this->scale) {
                'day' => true,
                'week' => $day->format('N') === '1',
                'month' => $day->format('j') === '1',
            };
            if ($boundary && $day != $from) {
                $lines .= Html::tag('line', [
                    'class' => 'caelrik-gantt-grid',
                    'x1' => (string) $x,
                    'y1' => '0',
                    'x2' => (string) $x,
                    'y2' => (string) self::ROW,
                ], '');
            }
        }

        return $lines;
    }

    private function todayLine(DateTimeImmutable $from, int $width, int $height): string
    {
        if (!$this->showToday) {
            return '';
        }
        $today = $this->today ?? new DateTimeImmutable('today');
        $x = (self::days($from, $today) + 0.5) * self::DAY_WIDTH[$this->scale];
        if ($x < 0 || $x > $width) {
            return '';
        }

        return Html::tag('line', [
            'class' => 'caelrik-gantt-today',
            'x1' => self::n($x),
            'y1' => '0',
            'x2' => self::n($x),
            'y2' => (string) $height,
        ], '');
    }

    /**
     * The first and last days shown: the tasks' days, widened to whole weeks or months.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function range(): array
    {
        $from = min(array_column($this->tasks, 'start'));
        $to = max(array_column($this->tasks, 'end'));

        return match ($this->scale) {
            'day' => [$from->modify('-1 day'), $to->modify('+1 day')],
            'week' => [$from->modify('monday this week'), $to->modify('sunday this week')],
            'month' => [$from->modify('first day of this month'), $to->modify('last day of this month')],
        };
    }

    private function time(DateTimeImmutable $date): string
    {
        return Html::tag(
            'time',
            ['datetime' => $date->format('Y-m-d')],
            Html::escape($date->format($this->dateFormat)),
        );
    }

    private static function date(DateTimeInterface|string $date): DateTimeImmutable
    {
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        }
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException(sprintf('"%s" is not a Y-m-d date.', $date));
        }

        return $parsed;
    }

    private static function days(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $from->diff($to)->format('%r%a');
    }

    private static function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
