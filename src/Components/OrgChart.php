<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * An organization chart: people in nested lists, drawn as a tree by CSS (connectors are
 * borders, no inline styles). Screen readers hear lists and their levels; it needs no
 * JavaScript. In a narrow container it becomes an indented list; otherwise it scrolls sideways.
 * - collapsible(): each manager gets a button that shows or hides their team ("Ada Lovelace's
 *   team, 3 people"), with aria-expanded; expandLevels() sets how deep it opens.
 *
 *     OrgChart::make('team')->label('Engineering')
 *         ->node('ada', 'Ada Lovelace', 'CTO')
 *         ->node('alan', 'Alan Turing', 'Lead engineer', parent: 'ada');
 */
final class OrgChart extends Component
{
    public const LABELS = [
        'team' => "{name}'s team, {count} people",
        'teamOne' => "{name}'s team, 1 person",
    ];

    private string $label = '';
    private bool $collapsible = false;
    private ?int $expandLevels = null;

    /**
     * @var array<string, array{name: string, title: ?string, parent: ?string, image: ?string, url: ?string}>
     */
    private array $nodes = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'orgchart';
    }

    /**
     * Names the chart: its region and its top list.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A person: their job title, the id of their manager, a picture and a link.
     */
    public function node(
        string $id,
        string $name,
        ?string $title = null,
        ?string $parent = null,
        ?string $image = null,
        ?string $url = null,
    ): self {
        if (isset($this->nodes[$id])) {
            throw new InvalidArgumentException(sprintf('There is already a person "%s".', $id));
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('A person in an org chart needs a name.');
        }
        $this->nodes[$id] = [
            'name' => $name,
            'title' => $title,
            'parent' => $parent,
            'image' => $image,
            'url' => $url === null ? null : Url::safe($url),
        ];

        return $this;
    }

    /**
     * People from rows (arrays, or objects with public properties), such as database rows.
     *
     * @param iterable<array<string, mixed>|object> $rows
     */
    public function fromRows(
        iterable $rows,
        string $id = 'id',
        string $parent = 'parent_id',
        string $name = 'name',
        string $title = 'title',
        ?string $image = null,
        ?string $url = null,
    ): self {
        foreach ($rows as $row) {
            $value = static function (?string $key) use ($row): ?string {
                if ($key === null) {
                    return null;
                }
                $field = is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);

                return $field === null || $field === '' ? null : (string) $field;
            };
            $this->node(
                (string) $value($id),
                (string) $value($name),
                $value($title),
                $value($parent),
                $value($image),
                $value($url),
            );
        }

        return $this;
    }

    /**
     * A button on each manager shows or hides their team (needs JavaScript).
     */
    public function collapsible(bool $collapsible = true): self
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * How many levels are open at first (1: the top people only); all by default.
     */
    public function expandLevels(?int $levels): self
    {
        if ($levels !== null && $levels < 1) {
            throw new InvalidArgumentException('An org chart opens 1 level or more.');
        }
        $this->expandLevels = $levels;
        $this->collapsible = $this->collapsible || $levels !== null;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown org chart label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->expandLevels === null ? [] : ['expandLevels' => $this->expandLevels];
    }

    public function render(): string
    {
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Org chart "%s" needs a label.', $this->id));
        }
        if ($this->nodes === []) {
            throw new LogicException(sprintf('Org chart "%s" has nobody.', $this->id));
        }
        $children = [];
        $roots = [];
        foreach ($this->nodes as $id => $node) {
            if ($node['parent'] === null) {
                $roots[] = (string) $id;
            } elseif (!isset($this->nodes[$node['parent']])) {
                throw new LogicException(sprintf('"%s" reports to an unknown person "%s".', $id, $node['parent']));
            } else {
                $children[$node['parent']][] = (string) $id;
            }
        }
        $seen = [];
        $labelId = $this->id . '-label';
        $tree = $this->list($roots, $children, $seen, 1, [
            'class' => 'celerik-orgchart-tree',
            'aria-labelledby' => $labelId,
        ]);
        if (count($seen) !== count($this->nodes)) {
            $loop = array_diff(array_map('strval', array_keys($this->nodes)), array_keys($seen));
            throw new LogicException(sprintf('Org chart "%s" has a loop: %s.', $this->id, implode(', ', $loop)));
        }

        return Html::tag('div', $this->rootAttributes([
            'celerik-orgchart',
            $this->collapsible ? 'is-collapsible' : null,
        ]), Html::tag('p', ['id' => $labelId, 'class' => 'celerik-orgchart-label'], Html::escape($this->label))
            . Html::tag('div', [
                'class' => 'celerik-orgchart-scroll',
                'role' => 'region',
                'aria-labelledby' => $labelId,
                'tabindex' => '0',
            ], $tree));
    }

    /**
     * @param list<string> $ids
     * @param array<string, list<string>> $children
     * @param array<string, true> $seen
     * @param array<string, string> $attributes
     */
    private function list(array $ids, array $children, array &$seen, int $level, array $attributes): string
    {
        $items = '';
        foreach ($ids as $id) {
            $seen[$id] = true;
            $node = $this->nodes[$id];
            $team = $children[$id] ?? [];
            $listId = $this->id . '-team-' . self::slug($id);

            $name = Html::escape($node['name']);
            if ($node['url'] !== null) {
                $name = Html::tag('a', ['href' => $node['url']], $name);
            }
            $card = Avatar::make()->person($node['name'])->image($node['image'])->decorative()->render()
                . Html::tag('div', ['class' => 'celerik-orgchart-text'], Html::tag(
                    'p',
                    ['class' => 'celerik-orgchart-name'],
                    $name,
                ) . ($node['title'] === null || $node['title'] === ''
                    ? ''
                    : Html::tag('p', ['class' => 'celerik-orgchart-title'], Html::escape($node['title']))));
            if ($this->collapsible && $team !== []) {
                $count = $this->count($id, $children);
                $card .= Html::tag('button', [
                    'type' => 'button',
                    'class' => 'celerik-orgchart-toggle',
                    'aria-expanded' => 'true',
                    'aria-controls' => $listId,
                    'aria-label' => strtr($this->labels[$count === 1 ? 'teamOne' : 'team'], [
                        '{name}' => $node['name'],
                        '{count}' => (string) $count,
                    ]),
                ], Html::tag('span', ['class' => 'celerik-orgchart-count'], (string) $count)
                    . '<span class="celerik-orgchart-chevron" aria-hidden="true"></span>');
            }

            $items .= Html::tag('li', [
                'class' => 'celerik-orgchart-item',
                'data-level' => (string) $level,
            ], Html::tag('div', ['class' => 'celerik-orgchart-node'], $card)
                . ($team === []
                    ? ''
                    : $this->list($team, $children, $seen, $level + 1, [
                    'class' => 'celerik-orgchart-team',
                    'id' => $listId,
                ])));
        }

        return Html::tag('ul', $attributes, $items);
    }

    /**
     * Everyone under a person, at every level.
     *
     * @param array<string, list<string>> $children
     */
    private function count(string $id, array $children): int
    {
        $count = 0;
        foreach ($children[$id] ?? [] as $child) {
            $count += 1 + $this->count($child, $children);
        }

        return $count;
    }

    /**
     * An id as a part of an HTML id: as it is when it can be, a hash of it otherwise.
     */
    private static function slug(string $id): string
    {
        return preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1 ? $id : 'h' . substr(md5($id), 0, 10);
    }
}
