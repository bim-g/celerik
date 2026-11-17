<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use PHPUnit\Framework\TestCase;

final class GridColumnToggleTest extends TestCase
{
    public function testColumnsSayWhetherTheUserMayToggleThemAndWhetherTheyStartShown(): void
    {
        $columns = Grid::make()
            ->column('id', null, static fn (Column $c) => $c->hidden())
            ->column('name', null, static fn (Column $c) => $c->toggleable(false))
            ->column('email')
            ->column('phone', null, static fn (Column $c) => $c->initiallyHidden())
            ->toArray()['columns'];

        $state = array_map(
            static fn (array $column): array => [$column['visible'], $column['toggleable'] ?? true],
            array_column($columns, null, 'field'),
        );

        self::assertSame([
            'id' => [false, false],      // data only: never in the menu
            'name' => [true, false],     // always shown
            'email' => [true, true],
            'phone' => [false, true],    // shown from the menu
        ], $state);
    }

    public function testTheMenuIsAnOptionWithTranslatableLabels(): void
    {
        self::assertArrayNotHasKey('columnToggle', Grid::make()->column('name')->toArray());

        $options = Grid::make()->column('name')
            ->columnToggle(remember: false)
            ->labels(['columns' => 'Colonnes', 'showColumns' => 'Afficher les colonnes'])
            ->toArray();

        self::assertSame(
            ['remember' => false, 'labels' => ['columns' => 'Colonnes', 'showColumns' => 'Afficher les colonnes']],
            $options['columnToggle'],
        );
    }

    public function testAnInitiallyHiddenColumnIsStillRenderedForTheBrowserToShow(): void
    {
        $html = Grid::make()->column('name')->column('phone', null, static fn (Column $c) => $c->initiallyHidden())
            ->rows([['name' => 'Ada', 'phone' => '555']])
            ->render();

        self::assertStringContainsString('<td>Ada</td><td>555</td>', $html);
    }
}
