<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GridSelectionTest extends TestCase
{
    private function grid(): Grid
    {
        return Grid::make('members')
            ->column('id', 'ID', static fn (Column $c) => $c->hidden())
            ->column('name', 'Name')
            ->selectable()
            ->rows([['id' => 7, 'name' => 'Ada <L>'], ['id' => 8, 'name' => 'Alan']]);
    }

    public function testACheckboxPerRowNamedByTheRowAndABoxForThePage(): void
    {
        $html = $this->grid()->render();

        self::assertStringContainsString(
            '<th scope="col" class="celerik-grid-select"><input type="checkbox" class="form-check-input"'
            . ' aria-label="Select all rows on this page" data-celerik-select-all></th>',
            $html,
        );
        self::assertStringContainsString(
            '<td class="celerik-grid-select"><input type="checkbox" class="form-check-input" name="ids[]" value="7"'
            . ' aria-label="Select Ada &lt;L&gt;" data-celerik-select></td><td>7</td>',
            $html,
        );
        // Without bulk actions there is no bulk form.
        self::assertStringNotContainsString('<form', $html);
        self::assertSame(
            ['key' => 'id', 'name' => 'ids', 'labels' => [
                'select' => 'Select',
                'selectAll' => 'Select all rows on this page',
                'selected' => '{count} selected',
                'noneSelected' => 'No rows selected',
            ]],
            $this->grid()->toArray()['selection'],
        );
    }

    public function testBulkActionsAreAFormTheCheckboxesBelongTo(): void
    {
        $grid = $this->grid()
            ->selectable('id', 'members')
            ->labels(['bulkActions' => 'Lignes choisies', 'noneSelected' => 'Aucune ligne'])
            ->bulkActions(
                Action::request('delete', 'Delete selected', '/members/bulk-delete')
                    ->method('DELETE')
                    ->variant('danger')
                    ->confirm('Delete {count} members?')
                    ->csrf('t0ken'),
                Action::request('export', 'E-mail them', '/members/mail'),
            );
        $html = $grid->render();

        self::assertStringStartsWith(
            '<form id="members-bulk" method="post" class="celerik-action celerik-grid-bulk d-flex flex-wrap align-items-center gap-2 mb-3"'
            . ' role="group" aria-label="Lignes choisies" data-celerik-bulk="members">'
            . '<input type="hidden" name="_token" value="t0ken">'
            . '<span class="celerik-grid-selected" role="status" data-celerik-selected>Aucune ligne</span>'
            . '<button type="submit" formaction="/members/bulk-delete" name="_method" value="DELETE"'
            . ' class="btn celerik-action-button btn-outline-danger btn-sm" data-celerik-action="delete" data-method="DELETE"'
            . ' data-confirm="Delete {count} members?" data-csrf-header="X-CSRF-TOKEN">Delete selected</button>'
            . '<button type="submit" formaction="/members/mail" class="btn celerik-action-button btn-outline-secondary btn-sm"'
            . ' data-celerik-action="export" data-method="POST">E-mail them</button></form><table',
            $html,
        );
        self::assertStringContainsString('name="members[]" value="7" form="members-bulk"', $html);
        self::assertSame('members-bulk', $grid->toArray()['selection']['form']);
    }

    public function testMisconfigurationsAreExplained(): void
    {
        $cases = [
            'Bulk action "open" must be a request' => static fn () => Grid::make()->bulkActions(Action::link('open', 'Open', '/')),
            'call selectable() too' => static fn () => Grid::make('g')->column('id')
                ->bulkActions(Action::request('a', 'A', '/'))->render(),
            'The row key "id" of grid "g" is not a column' => static fn () => Grid::make('g')->column('name')->selectable()->render(),
            'Invalid selection name' => static fn () => Grid::make()->selectable('id', 'ids[]'),
        ];

        foreach ($cases as $message => $build) {
            try {
                $build();
                self::fail("Expected: $message");
            } catch (InvalidArgumentException | LogicException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }
}
