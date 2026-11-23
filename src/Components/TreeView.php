<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Arr;
use Caelrik\Ui\Support\Html;
use Caelrik\Ui\Support\Url;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A tree of nodes: folders, categories, an outline, a site's pages.
 *
 * PHP writes nested lists that read and work without JavaScript: links are links, and a
 * selectable() or checkable() tree has real radio buttons or checkboxes that post with
 * the form around it. With JavaScript it becomes a WAI-ARIA tree: one tab stop, arrow keys
 * to move, open and close, Home and End, type-ahead, Space to select or check.
 *
 *     TreeView::make('folders')->label('Folders')->nodes([
 *         ['id' => 1, 'label' => 'Documents', 'expanded' => true, 'children' => [
 *             ['id' => 2, 'label' => 'Invoices', 'href' => '/folders/2'],
 *         ]],
 *     ]);
 *
 * A node: `label` (required), `id`, `href`, `children`, `expanded`, `checked` (or `selected`),
 * `disabled`, and `hasChildren` for children loaded from url() when the node opens.
 */
final class TreeView extends Component implements JsonSerializable
{
    public const LABELS = [
        'loading' => 'Loading…',
        'failed' => 'The items could not be loaded.',
    ];

    private ?string $label = null;

    /** @var list<array<string, mixed>> */
    private array $nodes = [];

    private ?string $mode = null;
    private ?string $inputName = null;
    private ?string $url = null;
    private bool $expandAll = false;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'treeview';
    }

    /**
     * The tree's accessible name, e.g. 'Folders'.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @param iterable<array<string, mixed>> $nodes
     */
    public function nodes(iterable $nodes): self
    {
        $this->nodes = self::normalize($nodes, 0);

        return $this;
    }

    /**
     * Builds the nodes from flat rows with a parent field, e.g. categories from a database.
     * A row whose parent is not among the rows is a root. `$href`'s `{field}` takes the row's
     * value, URL-encoded.
     *
     * @param iterable<array<string, mixed>> $rows
     */
    public function fromRows(
        iterable $rows,
        string $id = 'id',
        string $parent = 'parent_id',
        string $label = 'name',
        ?string $href = null,
    ): self {
        $byId = [];
        $order = [];
        foreach ($rows as $row) {
            $key = Arr::toText(Arr::get($row, $id));
            $byId[$key] = array_filter([
                'id' => Arr::get($row, $id),
                'label' => Arr::toText(Arr::get($row, $label)),
                'href' => $href === null ? null : self::fill($href, $row),
                'children' => [],
            ], static fn (mixed $value): bool => $value !== null);
            $order[] = [$key, Arr::toText(Arr::get($row, $parent))];
        }

        $children = [];
        $roots = [];
        foreach ($order as [$key, $parentKey]) {
            if ($parentKey !== '' && $parentKey !== $key && isset($byId[$parentKey])) {
                $children[$parentKey][] = $key;
            } else {
                $roots[] = $key;
            }
        }
        // Builds each subtree once; a cycle (a row that is its own ancestor) is cut.
        $build = static function (string $key, array $seen) use (&$build, $byId, $children): array {
            $node = $byId[$key];
            $seen[$key] = true;
            foreach ($children[$key] ?? [] as $child) {
                if (!isset($seen[$child])) {
                    $node['children'][] = $build($child, $seen);
                }
            }

            return $node;
        };

        return $this->nodes(array_map(static fn (string $key): array => $build($key, []), $roots));
    }

    /**
     * One node can be selected: a radio button named `$name`, which posts the node's id.
     */
    public function selectable(string $name): self
    {
        $this->mode = 'single';
        $this->inputName = self::inputName($name);

        return $this;
    }

    /**
     * Nodes can be checked: a checkbox per node, named `$name[]`, which posts the checked ids.
     * Checking a parent checks its children; a parent with some children checked is mixed.
     */
    public function checkable(string $name): self
    {
        $this->mode = 'multiple';
        $this->inputName = self::inputName($name);

        return $this;
    }

    /**
     * Where children of a `hasChildren` node come from: `{id}` takes the node's id. The
     * endpoint answers {"data": [nodes]} (see children()).
     */
    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Every node starts open, not only those marked `expanded`.
     */
    public function expandAll(bool $expandAll = true): self
    {
        $this->expandAll = $expandAll;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown tree label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    /**
     * An endpoint's answer for a node's children: {"data": [nodes]}, checked like nodes().
     *
     * @param iterable<array<string, mixed>> $nodes
     * @return array{data: list<array<string, mixed>>}
     */
    public static function children(iterable $nodes): array
    {
        return ['data' => self::normalize($nodes, 0)];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function toArray(): array
    {
        return array_filter([
            'mode' => $this->mode,
            'name' => $this->inputName,
            'url' => $this->url,
            'expandAll' => $this->expandAll ?: null,
            'labels' => $this->labels === self::LABELS ? null : $this->labels,
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return ['data' => $this->nodes];
    }

    public function render(): string
    {
        return Html::tag(
            'div',
            $this->rootAttributes(['caelrik-tree']),
            Html::tag(
                'ul',
                ['class' => 'caelrik-tree-root', 'aria-label' => $this->label],
                $this->renderNodes($this->nodes),
            ),
        );
    }

    /**
     * The same markup as tree.js writes for loaded children.
     *
     * @param list<array<string, mixed>> $nodes
     */
    private function renderNodes(array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= $this->renderNode($node);
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function renderNode(array $node): string
    {
        $label = Html::escape((string) $node['label']);
        $id = $node['id'] ?? null;
        $disabled = (bool) ($node['disabled'] ?? false);

        if ($this->mode !== null && $id !== null) {
            $control = Html::tag('input', [
                'type' => $this->mode === 'single' ? 'radio' : 'checkbox',
                'class' => 'form-check-input caelrik-tree-input',
                'name' => $this->mode === 'single' ? $this->inputName : $this->inputName . '[]',
                'value' => Arr::toText($id),
                'checked' => (bool) ($node['checked'] ?? false),
                'disabled' => $disabled,
            ]);
            $text = Html::tag('span', [], $label);
            $content = Html::tag('label', ['class' => 'caelrik-tree-label'], $control . $text);
        } elseif (isset($node['href'])) {
            $content = Html::tag('a', ['class' => 'caelrik-tree-label', 'href' => (string) $node['href']], $label);
        } else {
            $content = Html::tag('span', ['class' => 'caelrik-tree-label'], $label);
        }

        $children = $node['children'] ?? [];
        $group = $children === []
            ? ''
            : Html::tag('ul', ['class' => 'caelrik-tree-group'], $this->renderNodes($children));
        $expanded = $this->expandAll || (bool) ($node['expanded'] ?? false);
        $lazy = (bool) ($node['hasChildren'] ?? false) && $children === [];

        return Html::tag('li', [
            'class' => 'caelrik-tree-node',
            'data-id' => $id === null ? null : Arr::toText($id),
            'data-expanded' => $children !== [] || $lazy ? ($expanded ? 'true' : 'false') : null,
            'data-has-children' => $lazy ?: null,
            'data-disabled' => $disabled ?: null,
        ], Html::tag('div', ['class' => 'caelrik-tree-row'], $content) . $group);
    }

    /**
     * Checks each node: a label, scalar ids, http(s) or relative links, children as lists.
     *
     * @param iterable<mixed> $nodes
     * @return list<array<string, mixed>>
     */
    private static function normalize(iterable $nodes, int $depth): array
    {
        if ($depth > 64) {
            throw new InvalidArgumentException('A tree is at most 64 levels deep.');
        }
        $result = [];
        foreach ($nodes as $node) {
            if (!is_array($node) || !isset($node['label']) || !is_scalar($node['label'])) {
                throw new InvalidArgumentException('Each tree node needs a text "label".');
            }
            $id = $node['id'] ?? null;
            if ($id !== null && !is_scalar($id)) {
                throw new InvalidArgumentException(sprintf(
                    'The id of tree node "%s" is not a text or a number.',
                    $node['label'],
                ));
            }
            $href = isset($node['href']) ? Url::safe((string) $node['href']) : null;
            $children = $node['children'] ?? [];
            if (!is_iterable($children)) {
                throw new InvalidArgumentException(sprintf(
                    'The children of tree node "%s" are not a list.',
                    $node['label'],
                ));
            }
            $result[] = array_filter([
                'id' => $id,
                'label' => (string) $node['label'],
                'href' => $href,
                'expanded' => ($node['expanded'] ?? false) ? true : null,
                'checked' => ($node['checked'] ?? $node['selected'] ?? false) ? true : null,
                'disabled' => ($node['disabled'] ?? false) ? true : null,
                'hasChildren' => ($node['hasChildren'] ?? false) ? true : null,
                'children' => self::normalize($children, $depth + 1),
            ], static fn (mixed $value): bool => $value !== null && $value !== []);
        }

        return $result;
    }

    private static function inputName(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid input name "%s".', $name));
        }

        return $name;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fill(string $template, array $row): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\}/',
            static fn (array $match): string => rawurlencode(Arr::toText(Arr::get($row, $match[1]))),
            $template,
        );
    }
}
