<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Support\Arr;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A table whose rows nest like a tree: tasks and subtasks, an organisation, a budget with
 * its lines, folders with their sizes.
 *
 * PHP writes a table that reads without JavaScript: every row, in tree order, its first
 * cell indented by its level. With JavaScript it becomes a WAI-ARIA treegrid: rows open and
 * close, one tab stop, arrow keys between rows and cells.
 *
 *     TreeList::make('tasks')->caption('Tasks')
 *         ->column('name', 'Task')->column('owner', 'Owner')->column('hours', 'Hours')
 *         ->rows($tasks, id: 'id', parent: 'parent_id');
 */
final class TreeList extends Component
{
    /** Indentation classes go this deep; deeper rows keep the last one. */
    public const MAX_INDENT = 12;

    public const LABELS = [
        'expandAll' => 'Expand all',
        'collapseAll' => 'Collapse all',
        'actions' => 'Actions',
    ];

    /** @var list<Column> */
    private array $columns = [];

    private ?string $caption = null;
    private bool $captionHidden = false;

    /** @var list<array{row: array<string, mixed>, level: int, id: string, parent: ?string, size: int, position: int, children: int}> */
    private array $flat = [];

    /** @var list<string> */
    private array $expanded = [];
    private bool $expandAll = false;

    /** @var list<Action> */
    private array $actions = [];

    private ?string $rowTitle = null;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'treelist';
    }

    /**
     * The first column holds the tree: its cells are indented and show the open and close
     * marks. Columns take a Grid column's settings (title, className(), width(), hidden()).
     *
     * @param (callable(Column): mixed)|null $configure
     */
    public function column(string $field, ?string $title = null, ?callable $configure = null): self
    {
        $column = Column::make($field, $title);
        if ($configure !== null) {
            $configure($column);
        }
        $this->columns[] = $column;

        return $this;
    }

    public function caption(string $caption, bool $visuallyHidden = false): self
    {
        $this->caption = $caption;
        $this->captionHidden = $visuallyHidden;

        return $this;
    }

    /**
     * Rows with a parent field (`$parent`), or with their children under `children`. A row
     * whose parent is not among the rows is a top-level row; rows in a cycle are left out.
     *
     * @param iterable<array<string, mixed>> $rows
     */
    public function rows(iterable $rows, string $id = 'id', string $parent = 'parent_id'): self
    {
        $list = [];
        $flatten = static function (iterable $rows, ?string $parentKey) use (&$flatten, &$list, $id, $parent): void {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Each tree list row is an array.');
                }
                $children = $row['children'] ?? null;
                unset($row['children']);
                $key = Arr::toText(Arr::get($row, $id));
                if ($key === '') {
                    throw new InvalidArgumentException(sprintf('A tree list row has no "%s".', $id));
                }
                $list[] = [$row, $key, $parentKey ?? Arr::toText(Arr::get($row, $parent))];
                if (is_iterable($children)) {
                    $flatten($children, $key);
                }
            }
        };
        $flatten($rows, null);

        $known = [];
        foreach ($list as [, $key]) {
            if (isset($known[$key])) {
                throw new InvalidArgumentException(sprintf('Two tree list rows have the id "%s".', $key));
            }
            $known[$key] = true;
        }
        $children = [];
        $roots = [];
        foreach ($list as $index => [, $key, $parentKey]) {
            if ($parentKey !== '' && $parentKey !== $key && isset($known[$parentKey])) {
                $children[$parentKey][] = $index;
            } else {
                $roots[] = $index;
            }
        }

        $this->flat = [];
        // Depth first; a row already placed (a cycle) is not placed again.
        $place = function (
            array $indexes,
            int $level,
            ?string $parentKey,
            array $seen,
        ) use (
            &$place,
            $list,
            $children,
        ): void {
            $size = count($indexes);
            foreach (array_values($indexes) as $position => $index) {
                [$row, $key] = $list[$index];
                if (isset($seen[$key])) {
                    continue;
                }
                $this->flat[] = [
                    'row' => $row,
                    'level' => $level,
                    'id' => $key,
                    'parent' => $parentKey,
                    'size' => $size,
                    'position' => $position + 1,
                    'children' => count($children[$key] ?? []),
                ];
                $place($children[$key] ?? [], $level + 1, $key, $seen + [$key => true]);
            }
        };
        $place($roots, 1, null, []);

        return $this;
    }

    /**
     * Rows open at first, by id. Others start closed (with JavaScript).
     */
    public function expanded(string|int ...$ids): self
    {
        $this->expanded = array_map('strval', $ids);

        return $this;
    }

    public function expandAll(bool $expandAll = true): self
    {
        $this->expandAll = $expandAll;

        return $this;
    }

    /**
     * Buttons for each row, in a last column (Action::link(), Action::request(), with a form
     * or not), named with the row's title.
     */
    public function actions(Action ...$actions): self
    {
        array_push($this->actions, ...$actions);

        return $this;
    }

    /**
     * The field that names a row in its buttons ("Delete Design"): by default, the first column.
     */
    public function rowTitle(string $field): self
    {
        $this->rowTitle = $field;

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
                    'Unknown tree list label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'expandAll' => $this->expandAll ?: null,
            'labels' => $this->labels === self::LABELS ? null : $this->labels,
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function render(): string
    {
        $columns = array_values(array_filter($this->columns, static fn (Column $column): bool => $column->isVisible()));
        if ($columns === []) {
            throw new LogicException(sprintf('Tree list "%s" has no visible columns.', $this->id));
        }

        $caption = $this->caption === null ? '' : Html::tag(
            'caption',
            ['class' => $this->captionHidden ? 'visually-hidden' : null],
            Html::escape($this->caption),
        );
        $headers = '';
        foreach ($columns as $column) {
            $header = ['scope' => 'col', 'class' => $column->toArray()['className'] ?? null];
            $headers .= Html::tag('th', $header, Html::escape($column->getTitle()));
        }
        if ($this->actions !== []) {
            $header = ['scope' => 'col', 'class' => 'caelrik-treelist-actions'];
            $headers .= Html::tag('th', $header, Html::escape($this->labels['actions']));
        }

        $titleField = $this->rowTitle ?? $columns[0]->getField();
        $body = '';
        foreach ($this->flat as $entry) {
            $row = $entry['row'];
            $cells = '';
            foreach ($columns as $index => $column) {
                $text = Html::escape(Arr::toText(Arr::get($row, $column->getField())));
                $class = $column->toArray()['className'] ?? null;
                if ($index === 0) {
                    $level = min($entry['level'], self::MAX_INDENT);
                    $cells .= Html::tag('td', [
                        'class' => Html::classes(['caelrik-treelist-tree', 'caelrik-treelist-level-' . $level, $class]),
                    ], Html::tag('span', ['class' => 'caelrik-treelist-text'], $text));
                    continue;
                }
                $cells .= Html::tag('td', ['class' => $class], $text);
            }
            if ($this->actions !== []) {
                $title = Arr::toText(Arr::get($row, $titleField));
                $buttons = '';
                foreach ($this->actions as $action) {
                    $name = $action->hasAriaLabel() ? null : trim($action->getLabel() . ' ' . $title);
                    $buttons .= $action->render($row, small: true, ariaLabel: $name, modal: false);
                }
                $cells .= Html::tag('td', ['class' => 'caelrik-treelist-actions'], Html::tag(
                    'div',
                    ['class' => 'caelrik-grid-action-list'],
                    $buttons,
                ));
            }
            $open = $this->expandAll || in_array($entry['id'], $this->expanded, true);
            $body .= Html::tag('tr', [
                'data-id' => $entry['id'],
                'data-parent' => $entry['parent'],
                'data-level' => (string) $entry['level'],
                'data-size' => (string) $entry['size'],
                'data-position' => (string) $entry['position'],
                'data-expanded' => $entry['children'] > 0 ? ($open ? 'true' : 'false') : null,
            ], $cells);
        }

        $modals = '';
        foreach ($this->actions as $action) {
            $modals .= $action->renderModal();
        }

        return Html::tag(
            'table',
            $this->rootAttributes(['table', 'table-hover', 'w-100', 'caelrik-treelist']),
            $caption . Html::tag('thead', [], Html::tag('tr', [], $headers)) . Html::tag('tbody', [], $body),
        ) . $modals;
    }
}
