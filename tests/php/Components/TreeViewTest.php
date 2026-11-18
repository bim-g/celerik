<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\TreeView;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TreeViewTest extends TestCase
{
    public function testRendersNestedListsThatWorkWithoutJavaScript(): void
    {
        $html = TreeView::make('site')->label('Site')->nodes([
            ['label' => 'Guide <now>', 'href' => '/guide', 'expanded' => true, 'children' => [
                ['label' => 'Install', 'href' => '/guide/install?a=1&b=2'],
            ]],
            ['label' => 'Bad', 'href' => 'javascript:alert(1)'],
            ['label' => 'Tabbed', 'href' => "java\tscript:alert(1)"],
        ])->render();

        self::assertStringContainsString('<div id="site" class="celerik-tree" data-celerik="treeview"', $html);
        self::assertStringContainsString('<ul class="celerik-tree-root" aria-label="Site">', $html);
        self::assertStringContainsString(
            '<li class="celerik-tree-node" data-expanded="true"><div class="celerik-tree-row">'
            . '<a class="celerik-tree-label" href="/guide">Guide &lt;now&gt;</a></div><ul class="celerik-tree-group">',
            $html,
        );
        self::assertStringContainsString('href="/guide/install?a=1&amp;b=2"', $html);
        self::assertStringContainsString('<span class="celerik-tree-label">Bad</span>', $html);
        self::assertStringNotContainsString('script:', $html);
    }

    public function testCheckableAndSelectableTreesHaveRealFormControls(): void
    {
        $nodes = [
            ['id' => 1, 'label' => 'Documents', 'children' => [['id' => 2, 'label' => 'Invoices', 'checked' => true]]],
            ['id' => 3, 'label' => 'Archive', 'disabled' => true],
            ['label' => 'No id'],
        ];

        $checkable = TreeView::make('f')->checkable('folders')->nodes($nodes)->render();
        self::assertStringContainsString(
            '<input type="checkbox" class="form-check-input celerik-tree-input" name="folders[]" value="2" checked>',
            $checkable,
        );
        self::assertStringContainsString('name="folders[]" value="3" disabled>', $checkable);
        self::assertStringContainsString('data-disabled', $checkable);
        self::assertStringContainsString('<span class="celerik-tree-label">No id</span>', $checkable);

        $selectable = TreeView::make('p')->selectable('folder')->nodes([['id' => 'a', 'label' => 'A', 'selected' => true]]);
        self::assertStringContainsString('<input type="radio" class="form-check-input celerik-tree-input" name="folder" value="a" checked>', $selectable->render());
        self::assertSame(['mode' => 'single', 'name' => 'folder'], $selectable->toArray());
    }

    public function testLazyNodesAndOptions(): void
    {
        $tree = TreeView::make('f')->url('/folders/{id}/children')->expandAll()
            ->labels(['loading' => 'Chargement…'])
            ->nodes([['id' => 6, 'label' => 'Pictures', 'hasChildren' => true]]);

        self::assertStringContainsString('data-id="6" data-expanded="true" data-has-children', $tree->render());
        self::assertSame([
            'url' => '/folders/{id}/children',
            'expandAll' => true,
            'labels' => ['loading' => 'Chargement…', 'failed' => 'The items could not be loaded.'],
        ], $tree->toArray());
        self::assertSame(
            ['data' => [['id' => 61, 'label' => 'Holidays', 'hasChildren' => true]]],
            TreeView::children([['id' => 61, 'label' => 'Holidays', 'hasChildren' => true, 'extra' => 'dropped']]),
        );
    }

    public function testFromRowsBuildsTheTreeAndCutsCycles(): void
    {
        $tree = TreeView::make('c')->fromRows([
            ['id' => 3, 'parent_id' => 1, 'name' => 'Phones'],
            ['id' => 1, 'parent_id' => null, 'name' => 'Electronics'],
            ['id' => 4, 'parent_id' => 99, 'name' => 'Orphan'],
            ['id' => 5, 'parent_id' => 6, 'name' => 'Loop A'],
            ['id' => 6, 'parent_id' => 5, 'name' => 'Loop B'],
            ['id' => 7, 'parent_id' => 7, 'name' => 'Self'],
        ], href: '/categories/{id}');

        $nodes = $tree->getNodes();
        self::assertSame(['Electronics', 'Orphan', 'Self'], array_column($nodes, 'label'));
        self::assertSame('Phones', $nodes[0]['children'][0]['label']);
        self::assertSame('/categories/3', $nodes[0]['children'][0]['href']);
        // Rows in a cycle have no root: they are left out rather than looping.
        self::assertStringNotContainsString('Loop', $tree->render());
    }

    public function testRejectsBadNodes(): void
    {
        $cases = [
            static fn () => TreeView::make('t')->nodes([['id' => 1]]),
            static fn () => TreeView::make('t')->nodes([['label' => ['x']]]),
            static fn () => TreeView::make('t')->nodes([['label' => 'A', 'id' => ['x']]]),
            static fn () => TreeView::make('t')->nodes([['label' => 'A', 'children' => 'B']]),
            static fn () => TreeView::make('t')->checkable('bad name'),
            static fn () => TreeView::make('t')->labels(['nope' => 'x']),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
