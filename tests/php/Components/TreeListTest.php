<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\TreeList;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TreeListTest extends TestCase
{
    private const ROWS = [
        ['id' => 3, 'parent_id' => 2, 'name' => 'Logo', 'hours' => 2],
        ['id' => 1, 'parent_id' => null, 'name' => 'Design <b>', 'hours' => 12],
        ['id' => 2, 'parent_id' => 1, 'name' => 'Sketches', 'hours' => 5],
        ['id' => 4, 'parent_id' => 1, 'name' => 'Review', 'hours' => 7],
        ['id' => 5, 'parent_id' => 99, 'name' => 'Orphan', 'hours' => 1],
        ['id' => 6, 'parent_id' => 7, 'name' => 'Loop A', 'hours' => 0],
        ['id' => 7, 'parent_id' => 6, 'name' => 'Loop B', 'hours' => 0],
    ];

    private function list(): TreeList
    {
        return TreeList::make('tasks')
            ->caption('Tasks')
            ->column('name', 'Task')
            ->column('hours', 'Hours', static fn (Column $c) => $c->className('text-end'))
            ->column('secret', 'Secret', static fn (Column $c) => $c->hidden());
    }

    public function testRendersRowsInTreeOrderWithLevelsAndPositions(): void
    {
        $html = $this->list()->expanded(1)->rows(self::ROWS)->render();

        preg_match_all('/<span class="caelrik-treelist-text">([^<]*)<\/span>/', $html, $names);
        self::assertSame(['Design &lt;b&gt;', 'Sketches', 'Logo', 'Review', 'Orphan'], $names[1]);
        self::assertStringContainsString(
            '<tr data-id="1" data-level="1" data-size="2" data-position="1" data-expanded="true">'
            . '<td class="caelrik-treelist-tree caelrik-treelist-level-1"><span class="caelrik-treelist-text">Design &lt;b&gt;</span></td>'
            . '<td class="text-end">12</td></tr>',
            $html,
        );
        self::assertStringContainsString('data-id="2" data-parent="1" data-level="2" data-size="2" data-position="1" data-expanded="false"', $html);
        self::assertStringContainsString('<td class="caelrik-treelist-tree caelrik-treelist-level-3">', $html);
        self::assertStringContainsString('<th scope="col" class="text-end">Hours</th>', $html);
        self::assertStringNotContainsString('Secret', $html);
        // Rows in a cycle have no top-level row: left out rather than looping.
        self::assertStringNotContainsString('Loop', $html);
    }

    public function testNestedChildrenAndExpandAll(): void
    {
        $list = $this->list()->expandAll()->rows([
            ['id' => 'a', 'name' => 'A', 'children' => [['id' => 'b', 'name' => 'B']]],
        ]);
        $html = $list->render();

        self::assertStringContainsString('data-id="b" data-parent="a" data-level="2"', $html);
        self::assertStringContainsString('data-id="a" data-level="1" data-size="1" data-position="1" data-expanded="true"', $html);
        self::assertSame(['expandAll' => true], $list->toArray());
    }

    public function testDeepRowsKeepTheLastIndentation(): void
    {
        $rows = [];
        for ($i = 1; $i <= 15; $i++) {
            $rows[] = ['id' => $i, 'parent_id' => $i - 1, 'name' => 'Level ' . $i];
        }
        $html = TreeList::make('deep')->column('name')->rows($rows)->render();

        self::assertStringContainsString('caelrik-treelist-level-12"><span class="caelrik-treelist-text">Level 15', $html);
        self::assertStringContainsString('data-level="15"', $html);
    }

    public function testActionsAreNamedByTheRowTitle(): void
    {
        $html = $this->list()
            ->actions(Action::request('delete', 'Delete', '/tasks/{id}')->method('DELETE'))
            ->rows([['id' => 1, 'name' => 'Design']])
            ->render();

        self::assertStringContainsString('<th scope="col" class="caelrik-treelist-actions">Actions</th>', $html);
        self::assertStringContainsString('action="/tasks/1"', $html);
        self::assertStringContainsString('aria-label="Delete Design"', $html);
    }

    public function testRejectsBadRowsAndConfiguration(): void
    {
        $cases = [
            static fn () => TreeList::make('t')->rows([['name' => 'No id']]),
            static fn () => TreeList::make('t')->rows([['id' => 1], ['id' => 1]]),
            static fn () => TreeList::make('t')->rows(['not a row']),
            static fn () => TreeList::make('t')->labels(['nope' => 'x']),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(LogicException::class);
        TreeList::make('t')->rows([])->render();
    }
}
