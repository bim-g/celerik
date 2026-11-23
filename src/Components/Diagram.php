<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A flowchart drawn by PHP as an SVG (no JavaScript, no inline styles), and written as text.
 * - Steps are laid out in ranks, downwards or to the right; an arrow that goes back (a loop)
 *   is drawn around the side. Each rank is ordered to cross fewer arrows.
 * - The SVG is an image named by the caption; under it, "Steps as text" lists every step and
 *   where each arrow leads ("Yes: Account created"), with links between the steps.
 *
 *     Diagram::make('signup')->label('Sign-up')
 *         ->node('form', 'Fill in the form')
 *         ->node('valid', 'Is it valid?', shape: 'decision')
 *         ->node('done', 'Account created', shape: 'terminal', color: 'success')
 *         ->edge('form', 'valid')->edge('valid', 'done', 'Yes')->edge('valid', 'form', 'No');
 */
final class Diagram extends Component
{
    public const SHAPES = ['box', 'terminal', 'decision'];
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    public const DIRECTIONS = ['down', 'right'];
    public const LABELS = [
        'image' => '{label}. The steps are written as text below.',
        'text' => 'Steps as text',
        'next' => 'Next',
        'start' => 'Start',
        'end' => 'End',
    ];

    // Text: about 7 px a character at 13 px, 16 px a line; lines wrap at 22 characters.
    private const CHAR = 7.2;
    private const LINE = 16;
    private const WRAP = 22;
    private const PAD = 16;
    // Waypoints' ids start with a character no step id can hold in HTML.
    private const WAYPOINT = "\0";

    private string $label = '';
    private string $direction = 'down';

    /** @var array<string, array{text: string, shape: string, color: ?string}> */
    private array $nodes = [];

    /** @var list<array{from: string, to: string, label: ?string}> */
    private array $edges = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'diagram';
    }

    /**
     * The caption, and the image's name.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * down (default) or right.
     */
    public function direction(string $direction): self
    {
        if (!in_array($direction, self::DIRECTIONS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown diagram direction "%s".', $direction));
        }
        $this->direction = $direction;

        return $this;
    }

    /**
     * A step: a box (default), a terminal (start or end, rounded) or a decision (a diamond).
     */
    public function node(string $id, string $text, string $shape = 'box', ?string $color = null): self
    {
        if (isset($this->nodes[$id])) {
            throw new InvalidArgumentException(sprintf('There is already a step "%s".', $id));
        }
        if (trim($text) === '') {
            throw new InvalidArgumentException('A diagram step needs a text.');
        }
        if (!in_array($shape, self::SHAPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown diagram shape "%s".', $shape));
        }
        if ($color !== null && !in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown diagram color "%s".', $color));
        }
        $this->nodes[$id] = ['text' => $text, 'shape' => $shape, 'color' => $color];

        return $this;
    }

    /**
     * An arrow from one step to another, with an optional label ("Yes").
     */
    public function edge(string $from, string $to, ?string $label = null): self
    {
        if ($from === $to) {
            throw new InvalidArgumentException(sprintf('Step "%s" cannot lead to itself.', $from));
        }
        $this->edges[] = ['from' => $from, 'to' => $to, 'label' => $label === '' ? null : $label];

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown diagram label "%s".', $key));
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
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Diagram "%s" needs a label.', $this->id));
        }
        if ($this->nodes === []) {
            throw new LogicException(sprintf('Diagram "%s" has no step.', $this->id));
        }
        foreach ($this->edges as $edge) {
            foreach ([$edge['from'], $edge['to']] as $end) {
                if (!isset($this->nodes[$end])) {
                    throw new LogicException(sprintf(
                        'An arrow of diagram "%s" joins an unknown step "%s".',
                        $this->id,
                        $end,
                    ));
                }
            }
        }

        [$ranks, $back] = $this->ranks();
        // An arrow across several ranks passes through a waypoint in each rank between, so
        // the ordering gives it a lane of its own instead of drawing it through a step.
        $chains = [];
        $segments = [];
        foreach ($this->edges as $index => $edge) {
            if (isset($back[$index])) {
                continue;
            }
            $points = [$edge['from']];
            for ($r = $ranks[$edge['from']] + 1; $r < $ranks[$edge['to']]; $r++) {
                $waypoint = self::WAYPOINT . $index . ':' . $r;
                $ranks[$waypoint] = $r;
                $points[] = $waypoint;
            }
            $points[] = $edge['to'];
            $chains[$index] = $points;
            for ($i = 1; $i < count($points); $i++) {
                $segments[] = [$points[$i - 1], $points[$i]];
            }
        }
        $order = $this->order($ranks, $segments);
        [$boxes, $width, $height] = $this->place($order);

        return Html::tag('figure', $this->rootAttributes(['caelrik-diagram']), Html::tag(
            'div',
            ['class' => 'caelrik-diagram-scroll'],
            $this->svg($boxes, $back, $chains, $width, $height),
        ) . Html::tag('figcaption', ['class' => 'caelrik-diagram-caption'], Html::escape($this->label))
            . $this->text($order));
    }

    /**
     * Each step's rank: the longest way to it from a step nothing leads to. Arrows that go
     * back to a step on the current way (loops) are left out of the ranking.
     *
     * @return array{array<string, int>, array<int, true>}
     */
    private function ranks(): array
    {
        $out = [];
        foreach ($this->edges as $index => $edge) {
            $out[$edge['from']][] = $index;
        }
        // Loops: depth-first from each step in order, an arrow to a step still on the way.
        $back = [];
        $state = [];
        $visit = function (string $id) use (&$visit, &$state, &$back, $out): void {
            $state[$id] = 1;
            foreach ($out[$id] ?? [] as $index) {
                $to = $this->edges[$index]['to'];
                if (($state[$to] ?? 0) === 1) {
                    $back[$index] = true;
                } elseif (!isset($state[$to])) {
                    $visit($to);
                }
            }
            $state[$id] = 2;
        };
        $incoming = [];
        foreach ($this->edges as $edge) {
            $incoming[$edge['to']] = true;
        }
        foreach (array_keys($this->nodes) as $id) {
            if (!isset($incoming[$id]) && !isset($state[$id])) {
                $visit((string) $id);
            }
        }
        foreach (array_keys($this->nodes) as $id) {
            if (!isset($state[$id])) {
                $visit((string) $id);
            }
        }

        $rank = array_fill_keys(array_map('strval', array_keys($this->nodes)), 0);
        // Longest path: relax the forward arrows as many times as there are steps.
        for ($pass = 0; $pass < count($this->nodes); $pass++) {
            $changed = false;
            foreach ($this->edges as $index => $edge) {
                if (!isset($back[$index]) && $rank[$edge['to']] < $rank[$edge['from']] + 1) {
                    $rank[$edge['to']] = $rank[$edge['from']] + 1;
                    $changed = true;
                }
            }
            if (!$changed) {
                break;
            }
        }

        return [$rank, $back];
    }

    /**
     * The steps (and waypoints) of each rank, ordered by the mean place of what leads to them.
     *
     * @param array<string, int> $ranks
     * @param list<array{string, string}> $segments
     *
     * @return list<list<string>>
     */
    private function order(array $ranks, array $segments): array
    {
        $order = [];
        foreach ($ranks as $id => $rank) {
            $order[$rank][] = (string) $id;
        }
        ksort($order);
        $order = array_values($order);
        for ($sweep = 0; $sweep < 2; $sweep++) {
            $place = [];
            foreach ($order as $rank) {
                foreach ($rank as $index => $id) {
                    $place[$id] = $index;
                }
            }
            for ($r = 1; $r < count($order); $r++) {
                $keys = [];
                foreach ($order[$r] as $index => $id) {
                    $sources = [];
                    foreach ($segments as [$from, $to]) {
                        if ($to === $id) {
                            $sources[] = $place[$from];
                        }
                    }
                    $keys[$id] = [$sources === [] ? $index : array_sum($sources) / count($sources), $index];
                }
                usort($order[$r], static fn (string $a, string $b): int => $keys[$a] <=> $keys[$b]);
                foreach ($order[$r] as $index => $id) {
                    $place[$id] = $index;
                }
            }
        }

        return $order;
    }

    /**
     * The center and size of each step, and the drawing's size.
     *
     * @param list<list<string>> $order
     *
     * @return array{array<string, array{x: float, y: float, w: float, h: float, lines: list<string>}>, float, float}
     */
    private function place(array $order): array
    {
        $down = $this->direction === 'down';
        $sizes = [];
        foreach ($order as $rank) {
            foreach ($rank as $id) {
                if (str_starts_with($id, self::WAYPOINT)) {
                    // A lane for an arrow: narrow along the rank, no depth.
                    $sizes[$id] = ['w' => $down ? 16.0 : 0.0, 'h' => $down ? 0.0 : 16.0, 'lines' => []];
                    continue;
                }
                $lines = explode("\n", wordwrap($this->nodes[$id]['text'], self::WRAP, "\n", true));
                $w = max(96.0, max(array_map('mb_strlen', $lines)) * self::CHAR + 2 * self::PAD);
                $h = count($lines) * self::LINE + 20.0;
                if ($this->nodes[$id]['shape'] === 'decision') {
                    $w *= 1.5;
                    $h = $h * 1.6 + 8;
                }
                $sizes[$id] = ['w' => $w, 'h' => $h, 'lines' => $lines];
            }
        }
        // Along a rank: the steps side by side; across ranks: room for arrows and their labels.
        $gapIn = $down ? 40.0 : 28.0;
        $gapAcross = $down ? 56.0 : 80.0;
        $spans = [];
        $depths = [];
        foreach ($order as $r => $rank) {
            $spans[$r] = -$gapIn;
            $depths[$r] = 0.0;
            foreach ($rank as $id) {
                $spans[$r] += ($down ? $sizes[$id]['w'] : $sizes[$id]['h']) + $gapIn;
                $depths[$r] = max($depths[$r], $down ? $sizes[$id]['h'] : $sizes[$id]['w']);
            }
        }
        $span = max($spans);
        $boxes = [];
        $across = 0.0;
        foreach ($order as $r => $rank) {
            $along = ($span - $spans[$r]) / 2;
            foreach ($rank as $id) {
                $size = $sizes[$id];
                $length = $down ? $size['w'] : $size['h'];
                $center = [$along + $length / 2, $across + $depths[$r] / 2];
                $boxes[$id] = [
                    'x' => $down ? $center[0] : $center[1],
                    'y' => $down ? $center[1] : $center[0],
                    'w' => $size['w'],
                    'h' => $size['h'],
                    'lines' => $size['lines'],
                ];
                $along += $length + $gapIn;
            }
            $across += $depths[$r] + $gapAcross;
        }
        $across -= $gapAcross;

        return [$boxes, $down ? $span : $across, $down ? $across : $span];
    }

    /**
     * @param array<string, array{x: float, y: float, w: float, h: float, lines: list<string>}> $boxes
     * @param array<int, true> $back
     * @param array<int, list<string>> $chains the steps and waypoints of each forward arrow
     */
    private function svg(array $boxes, array $back, array $chains, float $width, float $height): string
    {
        $margin = 12.0;
        // Loops go around the side: room for them.
        $side = $back === [] ? 0.0 : 64.0;
        $down = $this->direction === 'down';
        $dx = $margin;
        $dy = $margin;
        $arrowId = $this->id . '-arrow';

        $edges = '';
        $labels = '';
        foreach ($this->edges as $index => $edge) {
            $a = $boxes[$edge['from']];
            $b = $boxes[$edge['to']];
            if (isset($back[$index])) {
                // Out of the side of the step, around, into the side of the earlier step.
                if ($down) {
                    [$x1, $y1] = [$a['x'] + $a['w'] / 2, $a['y']];
                    [$x2, $y2] = [$b['x'] + $b['w'] / 2, $b['y']];
                    $bend = max($x1, $x2) + 48;
                    $points = [$x1, $y1, $bend, $y1, $bend, $y2, $x2, $y2];
                    $path = sprintf('M%s %sC%s %s %s %s %s %s', ...self::ns($points, $dx, $dy));
                    $at = [$bend - 12, ($y1 + $y2) / 2];
                } else {
                    [$x1, $y1] = [$a['x'], $a['y'] + $a['h'] / 2];
                    [$x2, $y2] = [$b['x'], $b['y'] + $b['h'] / 2];
                    $bend = max($y1, $y2) + 48;
                    $points = [$x1, $y1, $x1, $bend, $x2, $bend, $x2, $y2];
                    $path = sprintf('M%s %sC%s %s %s %s %s %s', ...self::ns($points, $dx, $dy));
                    $at = [($x1 + $x2) / 2, $bend - 12];
                }
            } else {
                // Out of the step, through its waypoints, into the next step.
                $points = [$down ? [$a['x'], $a['y'] + $a['h'] / 2] : [$a['x'] + $a['w'] / 2, $a['y']]];
                foreach (array_slice($chains[$index], 1, -1) as $waypoint) {
                    $points[] = [$boxes[$waypoint]['x'], $boxes[$waypoint]['y']];
                }
                $points[] = $down ? [$b['x'], $b['y'] - $b['h'] / 2] : [$b['x'] - $b['w'] / 2, $b['y']];
                $path = sprintf('M%s %s', ...self::ns($points[0], $dx, $dy));
                for ($i = 1; $i < count($points); $i++) {
                    [$x1, $y1] = $points[$i - 1];
                    [$x2, $y2] = $points[$i];
                    $curve = $down
                        ? [$x1, ($y1 + $y2) / 2, $x2, ($y1 + $y2) / 2, $x2, $y2]
                        : [($x1 + $x2) / 2, $y1, ($x1 + $x2) / 2, $y2, $x2, $y2];
                    $path .= sprintf('C%s %s %s %s %s %s', ...self::ns($curve, $dx, $dy));
                }
                // The label on the first part of the arrow.
                $at = [($points[0][0] + $points[1][0]) / 2, ($points[0][1] + $points[1][1]) / 2];
            }
            $edges .= Html::tag('path', [
                'class' => 'caelrik-diagram-edge' . (isset($back[$index]) ? ' is-loop' : ''),
                'd' => $path,
                'marker-end' => 'url(#' . $arrowId . ')',
            ], '');
            if ($edge['label'] !== null) {
                $w = mb_strlen($edge['label']) * 6.6 + 12;
                $labels .= Html::tag('g', ['class' => 'caelrik-diagram-edge-label'], Html::tag('rect', [
                    'x' => self::n($at[0] - $w / 2 + $dx),
                    'y' => self::n($at[1] - 9 + $dy),
                    'width' => self::n($w),
                    'height' => '18',
                    'rx' => '4',
                ], '') . Html::tag('text', [
                    'x' => self::n($at[0] + $dx),
                    'y' => self::n($at[1] + $dy),
                    'text-anchor' => 'middle',
                    'dominant-baseline' => 'central',
                ], Html::escape($edge['label'])));
            }
        }

        $nodes = '';
        foreach ($boxes as $id => $box) {
            if (str_starts_with($id, self::WAYPOINT)) {
                continue;
            }
            $node = $this->nodes[$id];
            $x = $box['x'] + $dx;
            $y = $box['y'] + $dy;
            $class = 'caelrik-diagram-shape caelrik-diagram-' . ($node['color'] ?? 'default');
            [$halfW, $halfH] = [$box['w'] / 2, $box['h'] / 2];
            $shape = match ($node['shape']) {
                'decision' => Html::tag('polygon', ['class' => $class, 'points' => sprintf(
                    '%s,%s %s,%s %s,%s %s,%s',
                    ...self::ns([$x, $y - $halfH, $x + $halfW, $y, $x, $y + $halfH, $x - $halfW, $y], 0, 0),
                )], ''),
                default => Html::tag('rect', [
                    'class' => $class,
                    'x' => self::n($x - $box['w'] / 2),
                    'y' => self::n($y - $box['h'] / 2),
                    'width' => self::n($box['w']),
                    'height' => self::n($box['h']),
                    'rx' => $node['shape'] === 'terminal' ? self::n($box['h'] / 2) : '6',
                ], ''),
            };
            $first = $y - (count($box['lines']) - 1) * self::LINE / 2;
            $tspans = '';
            foreach ($box['lines'] as $line => $text) {
                $tspans .= Html::tag('tspan', [
                    'x' => self::n($x),
                    'y' => self::n($first + $line * self::LINE),
                ], Html::escape($text));
            }
            $text = Html::tag('text', [
                'class' => 'caelrik-diagram-text',
                'text-anchor' => 'middle',
                'dominant-baseline' => 'central',
            ], $tspans);
            $nodes .= Html::tag('g', ['class' => 'caelrik-diagram-node', 'data-step' => $id], $shape . $text);
        }

        $w = $width + 2 * $margin + ($down ? $side : 0);
        $h = $height + 2 * $margin + ($down ? 0 : $side);
        $marker = Html::tag('marker', [
            'id' => $arrowId,
            'viewBox' => '0 0 10 10',
            'refX' => '9',
            'refY' => '5',
            'markerWidth' => '7',
            'markerHeight' => '7',
            'orient' => 'auto-start-reverse',
        ], '<path class="caelrik-diagram-arrowhead" d="M0 0L10 5L0 10z"></path>');

        return Html::tag('svg', [
            'class' => 'caelrik-diagram-svg',
            'width' => self::n($w),
            'height' => self::n($h),
            'viewBox' => '0 0 ' . self::n($w) . ' ' . self::n($h),
            'role' => 'img',
            'aria-label' => strtr($this->labels['image'], ['{label}' => $this->label]),
        ], Html::tag('defs', [], $marker) . $edges . $nodes . $labels);
    }

    /**
     * The steps in rank order, each with where its arrows lead.
     *
     * @param list<list<string>> $order
     */
    private function text(array $order): string
    {
        $incoming = [];
        foreach ($this->edges as $edge) {
            $incoming[$edge['to']] = true;
        }
        $items = '';
        foreach ($order as $rank) {
            foreach ($rank as $id) {
                if (str_starts_with($id, self::WAYPOINT)) {
                    continue;
                }
                $links = [];
                foreach ($this->edges as $edge) {
                    if ($edge['from'] === $id) {
                        $links[] = Html::escape(($edge['label'] ?? $this->labels['next']) . ': ')
                            . Html::tag('a', ['href' => '#' . $this->stepId($edge['to'])], Html::escape(
                                $this->nodes[$edge['to']]['text'],
                            ));
                    }
                }
                $tags = [];
                if (!isset($incoming[$id])) {
                    $tags[] = $this->labels['start'];
                }
                if ($links === []) {
                    $tags[] = $this->labels['end'];
                }
                $items .= Html::tag('li', ['id' => $this->stepId($id)], Html::tag(
                    'span',
                    ['class' => 'caelrik-diagram-step'],
                    Html::escape($this->nodes[$id]['text']),
                ) . ($tags === [] ? '' : ' ' . Html::tag('span', ['class' => 'caelrik-diagram-tag'], Html::escape(
                    '(' . implode(', ', $tags) . ')',
                ))) . ($links === []
                    ? ''
                    : Html::tag('span', ['class' => 'caelrik-diagram-links'], implode(' · ', $links))));
            }
        }

        return Html::tag('details', ['class' => 'caelrik-diagram-details'], Html::tag(
            'summary',
            [],
            Html::escape($this->labels['text']),
        ) . Html::tag('ol', ['class' => 'caelrik-diagram-steps'], $items));
    }

    private function stepId(string $id): string
    {
        $part = preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1 ? $id : 'h' . substr(md5($id), 0, 10);

        return $this->id . '-step-' . $part;
    }

    /**
     * @param list<float> $values x, y pairs
     *
     * @return list<string>
     */
    private static function ns(array $values, float $dx, float $dy): array
    {
        $out = [];
        foreach ($values as $index => $value) {
            $out[] = self::n($value + ($index % 2 === 0 ? $dx : $dy));
        }

        return $out;
    }

    private static function n(float $value): string
    {
        $text = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
