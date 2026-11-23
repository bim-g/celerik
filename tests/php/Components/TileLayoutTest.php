<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\TileLayout;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TileLayoutTest extends TestCase
{
    public function testTilesAreTitledRegionsWithSpansAsClasses(): void
    {
        $layout = TileLayout::make('dashboard')->columns(4)->headingLevel(3)
            ->tile('revenue', 'Revenue <net>', 'Up 12%.', cols: 2)
            ->tile('orders', 'Orders', Html::raw('<p>48</p>'), rows: 2);
        $html = $layout->render();

        self::assertStringContainsString(
            '<div id="dashboard" class="caelrik-tilelayout caelrik-tilelayout-cols-4" data-caelrik="tilelayout"',
            $html,
        );
        self::assertStringContainsString(
            '<section class="caelrik-tile card caelrik-tile-cols-2 caelrik-tile-rows-1" id="dashboard-tile-revenue" '
            . 'aria-labelledby="dashboard-tile-revenue-title" data-tile="revenue" data-cols="2" data-rows="1">'
            . '<div class="caelrik-tile-header card-header"><h3 class="caelrik-tile-title" '
            . 'id="dashboard-tile-revenue-title">Revenue &lt;net&gt;</h3></div>'
            . '<div class="caelrik-tile-body card-body">Up 12%.</div></section>',
            $html,
        );
        self::assertStringContainsString('caelrik-tile-rows-2', $html);
        self::assertStringContainsString('<p>48</p>', $html);
        // Not arrangeable: no tools, no help, no inline styles.
        self::assertStringNotContainsString('caelrik-tile-handle', $html);
        self::assertStringNotContainsString('dashboard-help', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertSame(['columns' => 4], $layout->toArray());
    }

    public function testArrangeableTilesHaveAnArrangeButtonAndTools(): void
    {
        $layout = TileLayout::make('board')
            ->tile('a', 'Sales', 'x')
            ->tile('b', 'Stock', 'y')
            ->reorderable()
            ->remember()
            ->labels(['arrange' => 'Ranger {tile}', 'earlier' => 'Avant']);
        $html = $layout->render();

        self::assertStringContainsString(
            '<p class="visually-hidden" id="board-help">The arrow keys move the tile.</p>',
            $html,
        );
        self::assertStringContainsString(
            '<button type="button" class="btn btn-sm caelrik-action-button caelrik-tile-handle" aria-expanded="false" '
            . 'aria-controls="board-tile-a-tools" aria-describedby="board-help" aria-label="Ranger Sales">',
            $html,
        );
        self::assertStringContainsString(
            '<div class="caelrik-tile-tools" id="board-tile-a-tools" role="group" '
            . 'aria-labelledby="board-tile-a-title" hidden>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-tile-command="earlier">Avant</button>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-tile-command="later">Move later</button>'
            . '</div>',
            $html,
        );
        self::assertStringNotContainsString('data-tile-command="wider"', $html);
        self::assertStringNotContainsString('caelrik-tile-grip', $html);
        self::assertSame(
            ['columns' => 3, 'reorderable' => true, 'remember' => true, 'labels' => [
                'moved' => TileLayout::LABELS['moved'],
                'resized' => TileLayout::LABELS['resized'],
            ]],
            $layout->toArray(),
        );

        $resizable = TileLayout::make('r')->tile('a', 'A', 'x')->resizable()->render();
        self::assertStringContainsString('>With Shift, they resize it.</p>', $resizable);
        self::assertStringContainsString('data-tile-command="taller"', $resizable);
        self::assertStringNotContainsString('data-tile-command="earlier"', $resizable);
        self::assertStringContainsString('<span class="caelrik-tile-grip" aria-hidden="true"></span>', $resizable);
    }

    public function testATileWiderThanTheLayoutIsRefused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Tile "wide" spans 4 columns; the layout has 3.');
        TileLayout::make('t')->tile('wide', 'Wide', 'x', cols: 4)->render();
    }

    public function testALayoutNeedsTiles(): void
    {
        $this->expectException(LogicException::class);
        TileLayout::make('t')->render();
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'no columns' => [static fn () => TileLayout::make('t')->columns(0)];
        yield 'too many columns' => [static fn () => TileLayout::make('t')->columns(13)];
        yield 'bad key' => [static fn () => TileLayout::make('t')->tile('a b', 'A', 'x')];
        yield 'same key' => [static fn () => TileLayout::make('t')->tile('a', 'A', 'x')->tile('a', 'B', 'y')];
        yield 'no title' => [static fn () => TileLayout::make('t')->tile('a', ' ', 'x')];
        yield 'too high' => [static fn () => TileLayout::make('t')->tile('a', 'A', 'x', rows: 7)];
        yield 'h1' => [static fn () => TileLayout::make('t')->headingLevel(1)];
        yield 'unknown label' => [static fn () => TileLayout::make('t')->labels(['nope' => 'x'])];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
