<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Adapters\Laravel;

use Caelrik\Ui\Adapters\Laravel\EloquentDataSource;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\Grid\Filter;
use Caelrik\Ui\Data\DataRequest;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\TestCase;

final class EloquentDataSourceTest extends TestCase
{
    protected function setUp(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('people', static function ($table): void {
            $table->increments('id');
            $table->string('name');
            $table->string('role');
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->dateTime('joined')->nullable();
        });

        foreach ([['Ada', 'admin'], ['Alan', 'user'], ['Grace', 'admin'], ['Linus', 'user']] as [$name, $role]) {
            Person::query()->create(['name' => $name, 'role' => $role, 'password' => 'hash']);
        }
    }

    public function testPaginatesSortsAndCounts(): void
    {
        $result = (new EloquentDataSource(Person::query()))->fetch(new DataRequest(
            page: 1,
            perPage: 2,
            sort: [['field' => 'name', 'direction' => 'desc']],
        ));

        self::assertSame(['Linus', 'Grace'], array_column($result->rows, 'name'));
        self::assertSame(4, $result->total);
        self::assertSame(4, $result->filtered);
        self::assertArrayNotHasKey('password', $result->rows[0], 'Hidden model attributes must not be exposed.');
    }

    public function testSearchesAndFilters(): void
    {
        $result = (new EloquentDataSource(Person::query(), ['name']))->fetch(new DataRequest(
            search: 'a',
            filters: ['role' => 'admin'],
        ));

        self::assertSame(['Ada', 'Grace'], array_column($result->rows, 'name'));
        self::assertSame(4, $result->total);
        self::assertSame(2, $result->filtered);
    }

    public function testSearchTreatsWildcardsLiterally(): void
    {
        Person::query()->create(['name' => '100% Ada', 'role' => 'user', 'password' => 'hash']);
        Person::query()->create(['name' => 'snake_case', 'role' => 'user', 'password' => 'hash']);
        Person::query()->create(['name' => 'Bang! Bob', 'role' => 'user', 'password' => 'hash']);
        $source = new EloquentDataSource(Person::query(), ['name']);

        self::assertSame(['100% Ada'], array_column($source->fetch(new DataRequest(search: '%'))->rows, 'name'));
        self::assertSame(['snake_case'], array_column($source->fetch(new DataRequest(search: '_'))->rows, 'name'));
        self::assertSame(['Bang! Bob'], array_column($source->fetch(new DataRequest(search: '!'))->rows, 'name'));
    }

    public function testWorksThroughGridRespond(): void
    {
        $grid = Grid::make()->column('id')->column('name');

        $result = $grid->respond(new EloquentDataSource(Person::query()), [
            'draw' => '1',
            'start' => '1',
            'length' => '1',
            'columns' => [['data' => 'id'], ['data' => 'name']],
            'order' => [['column' => '1', 'dir' => 'asc']],
            'search' => ['value' => 'n'],
        ]);

        self::assertSame([['id' => 4, 'name' => 'Linus']], $result->rows);
        self::assertSame(2, $result->filtered);
    }

    public function testColumnFiltersThroughGridRespond(): void
    {
        Person::query()->where('name', 'Ada')->update(['joined' => '2026-01-31 18:30:00']);
        Person::query()->where('name', 'Alan')->update(['joined' => '2026-02-01 00:00:00', 'active' => false]);
        Person::query()->where('name', 'Grace')->update(['joined' => '2026-01-05 09:00:00']);
        Person::query()->create(['name' => '50% Off', 'role' => 'user', 'password' => 'hash']);

        $grid = Grid::make()
            ->column('name', null, static fn (Column $c) => $c->filter(Filter::text()))
            ->column('role', null, static fn (Column $c) => $c->filter(Filter::select(['admin' => 'Admin', 'user' => 'User'])))
            ->column('active', null, static fn (Column $c) => $c->filter(Filter::boolean()))
            ->column('joined', null, static fn (Column $c) => $c->filter(Filter::dateRange()));
        $source = new EloquentDataSource(Person::query());
        $names = static fn (array $filter): array => array_column($grid->respond($source, ['filter' => $filter, 'sort' => 'name'])->rows, 'name');

        self::assertSame(['Ada', 'Grace'], $names(['joined' => ['from' => '2026-01-01', 'to' => '2026-01-31']]));
        self::assertSame(['Alan'], $names(['active' => '0']));
        self::assertSame(['Ada', 'Grace'], $names(['role' => 'admin', 'name' => 'A']));
        self::assertSame(['50% Off'], $names(['name' => '%']));
    }

    public function testTotalsAndGroupsTheFilteredRows(): void
    {
        $grid = Grid::make('people')
            ->column('name', 'Name', static fn (Column $c) => $c->total('count'))
            ->column('role')
            ->column('id', 'ID', static fn (Column $c) => $c->total('max'))
            ->groupBy('role');

        $result = $grid->respond(new EloquentDataSource(Person::query()), [
            'per_page' => '3',
            'sort' => 'name',
            'search' => 'a',
        ]);

        self::assertSame(['Ada', 'Grace', 'Alan'], array_column($result->rows, 'name'));
        self::assertSame(['name' => 3, 'id' => 3], $result->totals);
        self::assertSame([
            ['value' => 'admin', 'count' => 2, 'totals' => ['name' => 2, 'id' => 3]],
            ['value' => 'user', 'count' => 1, 'totals' => ['name' => 1, 'id' => 2]],
        ], $result->groups);
    }
}
