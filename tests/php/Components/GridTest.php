<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Filter;
use Celerik\Ui\Data\ArrayDataSource;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    public function testRendersAccessibleTableWithEscapedRows(): void
    {
        $html = Grid::make('users')
            ->caption('Users', visuallyHidden: true)
            ->column('name')
            ->column('email', 'E-mail')
            ->rows([['name' => '<b>Ada</b>', 'email' => 'ada@example.com']])
            ->render();

        self::assertStringContainsString('<table id="users"', $html);
        self::assertStringContainsString('data-celerik="grid"', $html);
        self::assertStringContainsString('<caption class="visually-hidden">Users</caption>', $html);
        self::assertStringContainsString('<th scope="col">E-mail</th>', $html);
        self::assertStringContainsString('<td>&lt;b&gt;Ada&lt;/b&gt;</td>', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testServerSideModeRendersEmptyBodyAndPassesUrl(): void
    {
        $grid = Grid::make('users')->url('/users/data')->column('name')->export('csv', 'copy')->pageSize(25);

        self::assertStringContainsString('<tbody></tbody>', $grid->render());
        self::assertSame('/users/data', $grid->toArray()['url']);
        self::assertSame(['csv', 'copy'], $grid->toArray()['export']);
        self::assertSame(25, $grid->toArray()['pageSize']);
    }

    public function testRespondOnlyExposesDeclaredColumns(): void
    {
        $grid = Grid::make()
            ->column('name')
            ->column('team.name', 'Team')
            ->addColumn(Column::make('secret_note')->searchable(false)->sortable(false)->hidden());

        $source = new ArrayDataSource([
            ['name' => 'Ada', 'password' => 'hash', 'team' => ['name' => 'Math', 'budget' => 1], 'secret_note' => 'shh'],
            ['name' => 'Bob', 'password' => 'hash', 'team' => ['name' => 'Ops', 'budget' => 2], 'secret_note' => 'x'],
        ]);

        $result = $grid->respond($source, ['search' => 'shh', 'sort' => '-name']);
        self::assertSame([], $result->rows, 'Non-searchable columns must not be searched.');

        $result = $grid->respond($source, ['sort' => '-name']);
        self::assertSame(
            ['name' => 'Bob', 'team' => ['name' => 'Ops'], 'secret_note' => 'x'],
            $result->rows[0],
        );
    }

    public function testRespondIgnoresSortOnNonSortableColumns(): void
    {
        $grid = Grid::make()->column('name', null, static fn (Column $c) => $c->sortable(false));

        $result = $grid->respond(new ArrayDataSource([['name' => 'b'], ['name' => 'a']]), ['sort' => 'name']);

        self::assertSame([], $result->request->sort);
        self::assertSame('b', $result->rows[0]['name']);
    }

    public function testJsonResponseShape(): void
    {
        $grid = Grid::make()->column('name');
        $json = json_encode($grid->respond(new ArrayDataSource([['name' => 'a']]), ['draw' => '7', 'start' => '0', 'length' => '10']));

        self::assertSame(
            '{"data":[{"name":"a"}],"meta":{"page":1,"perPage":10,"total":1,"filtered":1,"draw":7,'
            . '"sort":[],"search":null,"filters":{},"conditions":[]}}',
            $json,
        );
    }

    public function testRequiresColumns(): void
    {
        $this->expectException(LogicException::class);

        Grid::make()->render();
    }

    public function testRejectsUnknownExportFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Grid::make()->export('pdf');
    }

    public function testRejectsInvalidColumnField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Column::make('name; drop');
    }

    public function testRendersALabelledFilterBarHiddenUntilJavaScriptRuns(): void
    {
        $grid = Grid::make('people')
            ->column('name', 'Name', static fn (Column $c) => $c->filter(Filter::text()))
            ->column('role', 'Role', static fn (Column $c) => $c->filter(Filter::select(['admin' => 'Admin', 'user' => '<User>'])))
            ->column('active', 'Active', static fn (Column $c) => $c->filter(Filter::boolean()))
            ->column('joined', 'Joined', static fn (Column $c) => $c->filter(Filter::dateRange()))
            ->column('city')
            ->rows([['name' => 'Ada', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-31', 'city' => 'London']]);
        $html = $grid->render();

        self::assertStringStartsWith('<div class="celerik-grid-filters row g-3 align-items-end mb-3" id="people-filters" role="search" aria-label="Filters" hidden>', $html);
        self::assertStringContainsString('<label class="form-label" for="people-filter-name">Name</label><input data-celerik-filter="name" type="search"', $html);
        self::assertStringContainsString('<option value="">Any</option><option value="admin">Admin</option><option value="user">&lt;User&gt;</option>', $html);
        self::assertStringContainsString('<option value="">Any</option><option value="1">Yes</option><option value="0">No</option>', $html);
        self::assertStringContainsString('<legend class="form-label fs-6 mb-2">Joined</legend>', $html);
        self::assertStringContainsString('<label class="visually-hidden" for="people-filter-joined-to">To</label>', $html);
        self::assertStringContainsString('data-celerik-filter-end="from"', $html);
        self::assertStringContainsString('<button type="button" class="btn btn-outline-secondary" data-celerik-filter-clear>Clear filters</button>', $html);

        // The values a select, yes/no or date filter compares, for client-side filtering.
        self::assertStringContainsString('<td>Ada</td><td data-value="admin">admin</td><td data-value="1">Yes</td><td data-value="2026-01-31">2026-01-31</td><td>London</td>', $html);
        self::assertSame(['type' => 'select', 'options' => [['value' => 'admin', 'label' => 'Admin'], ['value' => 'user', 'label' => '<User>']]], $grid->toArray()['columns'][1]['filter']);
        self::assertArrayNotHasKey('filter', $grid->toArray()['columns'][4]);
    }

    public function testNoFilterBarWithoutFiltersAndLabelsCanBeTranslated(): void
    {
        self::assertStringStartsWith('<table', Grid::make()->column('name')->render());

        $html = Grid::make('g')->column('name', null, static fn (Column $c) => $c->filter(Filter::boolean()))
            ->labels(['any' => 'Tous', 'yes' => 'Oui', 'no' => 'Non', 'clear' => 'Effacer', 'filters' => 'Filtres'])
            ->render();

        self::assertStringContainsString('aria-label="Filtres"', $html);
        self::assertStringContainsString('<option value="">Tous</option><option value="1">Oui</option><option value="0">Non</option>', $html);
        self::assertStringContainsString('>Effacer</button>', $html);

        $this->expectException(InvalidArgumentException::class);
        Grid::make()->labels(['reset' => 'x']);
    }

    public function testASelectFilterNeedsOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Filter::select([]);
    }

    public function testRespondAppliesEachFilterAsItsTypeSays(): void
    {
        $grid = Grid::make()
            ->column('name', null, static fn (Column $c) => $c->filter(Filter::text()))
            ->column('role', null, static fn (Column $c) => $c->searchable(false)->filter(Filter::select(['admin' => 'Admin', 'user' => 'User'])))
            ->column('active', null, static fn (Column $c) => $c->filter(Filter::boolean()))
            ->column('joined', null, static fn (Column $c) => $c->filter(Filter::dateRange()))
            ->column('city', null, static fn (Column $c) => $c->searchable(false));

        $source = new ArrayDataSource([
            ['name' => 'Ada Lovelace', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-31', 'city' => 'London'],
            ['name' => 'Alan Turing', 'role' => 'user', 'active' => true, 'joined' => '2026-01-15', 'city' => 'Manchester'],
            ['name' => 'Grace Hopper', 'role' => 'admin', 'active' => false, 'joined' => '2026-01-20', 'city' => 'Arlington'],
            ['name' => 'Katherine Johnson', 'role' => 'admin', 'active' => true, 'joined' => '2026-02-01', 'city' => 'Hampton'],
        ]);

        $names = static fn (array $query): array => array_column($grid->respond($source, $query)->rows, 'name');

        // A text filter matches part of the value; a select filter works on a column that is not searched.
        self::assertSame(['Ada Lovelace', 'Grace Hopper'], $names(['filter' => ['name' => 'CE', 'role' => '', 'joined' => ['to' => '2026-01-31']]]));
        self::assertSame(['Ada Lovelace', 'Katherine Johnson'], $names(['filter' => ['role' => 'admin', 'active' => '1']]));
        self::assertSame(['Grace Hopper', 'Ada Lovelace', 'Katherine Johnson'], $names(['filter' => ['joined' => ['from' => '2026-01-20']], 'sort' => 'joined']));
        // A column without a filter, and not searchable, cannot be filtered.
        self::assertCount(4, $names(['filter' => ['city' => 'London']]));
        // The DataTables protocol, as the grid sends it.
        self::assertSame(['Grace Hopper'], $names(['draw' => '2', 'start' => '0', 'length' => '10', 'filter' => ['active' => '0']]));
    }
}
