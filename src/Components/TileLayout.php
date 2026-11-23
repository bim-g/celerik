<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A dashboard of tiles on a grid of columns: each tile a titled region, as wide and as high as
 * its spans. The user can rearrange them (reorderable(), resizable()):
 * - each tile's Arrange button shows its tools (Move earlier, Move later, Narrower, Wider,
 *   Shorter, Taller), buttons that work with one click or the keyboard (WCAG 2.5.7);
 * - on that button, the arrow keys move the tile, and with Shift resize it;
 * - the button can be dragged to another tile's place, and the tile's corner to a new size.
 * Tiles move in the page, so the reading and focus order follow what is seen. Without
 * JavaScript the tiles are shown as laid out by PHP; on small screens, in one column.
 *
 *     TileLayout::make('dashboard')->columns(4)
 *         ->tile('revenue', 'Revenue', $chart, cols: 2)
 *         ->tile('orders', 'Orders', $list, rows: 2)
 *         ->reorderable()->resizable()->remember();
 */
final class TileLayout extends Component
{
    public const MAX_COLUMNS = 12;
    public const MAX_ROWS = 6;

    public const LABELS = [
        'arrange' => 'Arrange {tile}',
        'helpMove' => 'The arrow keys move the tile.',
        'helpResize' => 'With Shift, they resize it.',
        'earlier' => 'Move earlier',
        'later' => 'Move later',
        'narrower' => 'Narrower',
        'wider' => 'Wider',
        'shorter' => 'Shorter',
        'taller' => 'Taller',
        'moved' => '{tile}: position {position} of {count}.',
        'resized' => '{tile}: width {cols} of {columns}, height {rows} of {maxRows}.',
    ];

    private int $columns = 3;
    private int $headingLevel = 2;
    private bool $reorderable = false;
    private bool $resizable = false;
    private bool $remember = false;

    /** @var array<string, array{title: string, content: string|Stringable, cols: int, rows: int}> */
    private array $tiles = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'tilelayout';
    }

    /**
     * The grid's columns, 1 to 12 (3 by default). Small screens show one.
     */
    public function columns(int $columns): self
    {
        if ($columns < 1 || $columns > self::MAX_COLUMNS) {
            throw new InvalidArgumentException('A tile layout has 1 to 12 columns.');
        }
        $this->columns = $columns;

        return $this;
    }

    /**
     * A tile: its key (for ids and saved layouts), its title, its content (a component or
     * Html::raw(); a string is escaped text), and how many columns and rows it spans.
     */
    public function tile(string $key, string $title, string|Stringable $content, int $cols = 1, int $rows = 1): self
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid tile key "%s": letters, digits, - and _.', $key));
        }
        if (isset($this->tiles[$key])) {
            throw new InvalidArgumentException(sprintf('Tile "%s" is already in the layout.', $key));
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('A tile needs a title.');
        }
        if ($cols < 1 || $cols > self::MAX_COLUMNS || $rows < 1 || $rows > self::MAX_ROWS) {
            throw new InvalidArgumentException('A tile spans 1 to 12 columns and 1 to 6 rows.');
        }
        $this->tiles[$key] = ['title' => $title, 'content' => $content, 'cols' => $cols, 'rows' => $rows];

        return $this;
    }

    /**
     * The tiles' headings, h2 (the default) to h6.
     */
    public function headingLevel(int $level): self
    {
        if ($level < 2 || $level > 6) {
            throw new InvalidArgumentException('A tile heading is h2 to h6.');
        }
        $this->headingLevel = $level;

        return $this;
    }

    public function reorderable(bool $reorderable = true): self
    {
        $this->reorderable = $reorderable;

        return $this;
    }

    public function resizable(bool $resizable = true): self
    {
        $this->resizable = $resizable;

        return $this;
    }

    /**
     * Keeps the user's layout in the browser (localStorage), per layout id. To keep it on the
     * server, listen to the caelrik:change event.
     */
    public function remember(bool $remember = true): self
    {
        $this->remember = $remember;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown tile layout label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'columns' => $this->columns,
            'reorderable' => $this->reorderable ?: null,
            'resizable' => $this->resizable ?: null,
            'remember' => $this->remember ?: null,
            'labels' => $this->reorderable || $this->resizable
                ? ['moved' => $this->labels['moved'], 'resized' => $this->labels['resized']]
                : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function render(): string
    {
        if ($this->tiles === []) {
            throw new LogicException(sprintf('Tile layout "%s" has no tiles.', $this->id));
        }
        $arrange = $this->reorderable || $this->resizable;
        $help = '';
        if ($arrange) {
            $help = Html::tag(
                'p',
                ['class' => 'visually-hidden', 'id' => $this->id . '-help'],
                Html::escape(implode(' ', array_filter([
                    $this->reorderable ? $this->labels['helpMove'] : null,
                    $this->resizable ? $this->labels['helpResize'] : null,
                ]))),
            );
        }

        $html = '';
        foreach ($this->tiles as $key => $tile) {
            if ($tile['cols'] > $this->columns) {
                throw new LogicException(sprintf(
                    'Tile "%s" spans %d columns; the layout has %d.',
                    $key,
                    $tile['cols'],
                    $this->columns,
                ));
            }
            $id = $this->id . '-tile-' . $key;
            $header = Html::tag(
                'h' . $this->headingLevel,
                ['class' => 'caelrik-tile-title', 'id' => $id . '-title'],
                Html::escape($tile['title']),
            );
            $tools = '';
            if ($arrange) {
                $header .= Html::tag('button', [
                    'type' => 'button',
                    'class' => 'btn btn-sm caelrik-action-button caelrik-tile-handle',
                    'aria-expanded' => 'false',
                    'aria-controls' => $id . '-tools',
                    'aria-describedby' => $this->id . '-help',
                    'aria-label' => strtr($this->labels['arrange'], ['{tile}' => $tile['title']]),
                ], '<span aria-hidden="true">⠿</span>');
                $tools = Html::tag('div', [
                    'class' => 'caelrik-tile-tools',
                    'id' => $id . '-tools',
                    'role' => 'group',
                    'aria-labelledby' => $id . '-title',
                    'hidden' => true,
                ], $this->tools());
            }
            $html .= Html::tag('section', [
                'class' => Html::classes([
                    'caelrik-tile',
                    'card',
                    'caelrik-tile-cols-' . $tile['cols'],
                    'caelrik-tile-rows-' . $tile['rows'],
                ]),
                'id' => $id,
                'aria-labelledby' => $id . '-title',
                'data-tile' => $key,
                'data-cols' => (string) $tile['cols'],
                'data-rows' => (string) $tile['rows'],
            ], Html::tag('div', ['class' => 'caelrik-tile-header card-header'], $header)
                . $tools
                . Html::tag('div', ['class' => 'caelrik-tile-body card-body'], Html::content($tile['content']))
                . ($this->resizable ? '<span class="caelrik-tile-grip" aria-hidden="true"></span>' : ''));
        }

        return Html::tag(
            'div',
            $this->rootAttributes(['caelrik-tilelayout', 'caelrik-tilelayout-cols-' . $this->columns]),
            $help . $html,
        );
    }

    private function tools(): string
    {
        $commands = [
            ...($this->reorderable ? ['earlier', 'later'] : []),
            ...($this->resizable ? ['narrower', 'wider', 'shorter', 'taller'] : []),
        ];
        $html = '';
        foreach ($commands as $command) {
            $html .= Html::tag('button', [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary',
                'data-tile-command' => $command,
            ], Html::escape($this->labels[$command]));
        }

        return $html;
    }
}
