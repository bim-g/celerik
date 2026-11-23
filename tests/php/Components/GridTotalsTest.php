<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Data\Aggregate;
use Caelrik\Ui\Data\ArrayDataSource;
use Caelrik\Ui\Data\DataRequest;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GridTotalsTest extends TestCase
{
    private const ROWS = [
        ['team' => 'Sales', 'item' => 'Flights', 'amount' => 1200.5, 'hours' => 3],
        ['team' => 'Design', 'item' => 'Fonts', 'amount' => 300, 'hours' => 2],
        ['team' => 'Sales', 'item' => 'Hotel', 'amount' => 800, 'hours' => null],
        ['team' => 'Design', 'item' => 'Laptop', 'amount' => '2500', 'hours' => 4],
        ['team' => null, 'item' => 'Headsets', 'amount' => 150, 'hours' => 1],
    ];

    private function grid(): Grid
    {
        return Grid::make('expenses')
            ->column('team')
            ->column('item')
            ->column('amount', 'Amount', static fn (Column $c) => $c->total())
            ->column('hours', 'Hours', static fn (Column $c) => $c->total('avg', 1))
            ->groupBy('team');
    }

    public function testAggregateTotalsAsSql(): void
    {
        $values = ['10', 2.5, '', null, 'n/a', true];

        self::assertSame(13.5, Aggregate::compute('sum', $values));
        self::assertSame(4, Aggregate::compute('count', $values));
        self::assertSame(1.5, Aggregate::compute('avg', ['1', '2']));
        self::assertSame(-1, Aggregate::compute('min', ['3', '-1']));
        self::assertSame(3, Aggregate::compute('max', ['3', '-1']));
        self::assertNull(Aggregate::compute('avg', []));
        self::assertSame(0, Aggregate::compute('sum', []));
        self::assertSame(1250, Aggregate::number('1250.00'));
        self::assertSame(1250.5, Aggregate::number('1250.50'));
        self::assertNull(Aggregate::number('n/a'));
    }

    public function testAggregateFormatsAsTheBrowserDoes(): void
    {
        self::assertSame('1,234,567', Aggregate::format(1234567, 'sum'));
        self::assertSame('1,234.50', Aggregate::format(1234.5, 'sum'));
        self::assertSame('3.00', Aggregate::format(3, 'avg'));
        self::assertSame('-1 234,6', Aggregate::format(-1234.567, 'sum', 1, ',', ' '));
        self::assertSame('', Aggregate::format(null, 'avg'));
        self::assertSame(Aggregate::groupKey(3), Aggregate::groupKey('3'));
        self::assertSame(Aggregate::groupKey(true), Aggregate::groupKey(1));
        self::assertNotSame(Aggregate::groupKey(null), Aggregate::groupKey(''));
    }

    public function testUnknownTotalsAndGroupsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Column::make('amount')->total('median');
    }

    public function testAGroupMustBeAColumn(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('grouped by "role"');
        Grid::make('people')->column('name')->groupBy('role')->render();
    }

    public function testRendersAFooterWithTheTotalsOfAllRowsAndRowsInGroupOrder(): void
    {
        $html = $this->grid()->rows(self::ROWS)->render();

        self::assertStringContainsString(
            '<td class="caelrik-grid-total" data-caelrik-total="amount"><span class="caelrik-grid-total-label">Total'
            . '</span> <span class="caelrik-grid-total-value">4,950.50</span></td>',
            $html,
        );
        self::assertStringContainsString('<span class="caelrik-grid-total-value">2.5</span>', $html);
        preg_match_all('/<tr><td>([^<]*)<\/td><td>(\w+)<\/td>/', $html, $rows);
        self::assertSame(['', 'Design', 'Design', 'Sales', 'Sales'], $rows[1]);
        self::assertStringContainsString('<td data-value="1200.5">1200.5</td>', $html);
    }

    public function testServerSideFootersWaitForTheEndpoint(): void
    {
        $html = $this->grid()->url('/expenses')->render();

        self::assertStringContainsString('<td class="caelrik-grid-total" data-caelrik-total="amount"></td>', $html);
    }

    public function testNoTotalsNoFooter(): void
    {
        $html = Grid::make('people')->column('name')->rows([['name' => 'Ada']])->render();

        self::assertStringNotContainsString('<tfoot>', $html);
        self::assertArrayNotHasKey('summary', Grid::make('people')->column('name')->toArray());
    }

    public function testSendsTheTotalsTheGroupAndTheirLabels(): void
    {
        $options = $this->grid()->labels(['sum' => 'Somme', 'decimal' => ','])->toArray();

        self::assertSame('team', $options['groupBy']);
        self::assertSame(['function' => 'sum'], $options['columns'][2]['total']);
        self::assertSame(['function' => 'avg', 'decimals' => 1], $options['columns'][3]['total']);
        self::assertSame('Somme', $options['summary']['sum']);
        self::assertSame(',', $options['summary']['decimal']);
        self::assertSame('{count} rows', $options['summary']['groupRows']);
    }

    public function testRespondTotalsTheFilteredRowsAndGroupsThePage(): void
    {
        $result = $this->grid()->respond(new ArrayDataSource(self::ROWS), [
            'per_page' => '3',
            'sort' => '-amount',
            'search' => 'o',
        ])->toArray();

        // "o" is in Fonts, Hotel and Laptop; rows are sorted by team first, then by amount.
        self::assertSame(['Laptop', 'Fonts', 'Hotel'], array_column($result['data'], 'item'));
        self::assertEquals((object) ['amount' => 3600, 'hours' => 3], $result['meta']['totals']);
        self::assertEquals([
            ['value' => 'Design', 'count' => 2, 'totals' => (object) ['amount' => 2800, 'hours' => 3]],
            ['value' => 'Sales', 'count' => 1, 'totals' => (object) ['amount' => 800, 'hours' => null]],
        ], $result['meta']['groups']);

        // A group of rows without a value; its count covers all its rows, not only the page.
        $groups = $this->grid()->respond(new ArrayDataSource(self::ROWS), ['per_page' => '2'])->groups;
        self::assertSame([[
            'value' => null, 'count' => 1, 'totals' => ['amount' => 150, 'hours' => 1],
        ], [
            'value' => 'Design', 'count' => 2, 'totals' => ['amount' => 2800, 'hours' => 3],
        ]], $groups);
    }

    public function testRequestsWithoutTotalsKeepTheirShape(): void
    {
        $meta = Grid::make('people')->column('name')
            ->respond(new ArrayDataSource([['name' => 'Ada']]), [])->toArray()['meta'];

        self::assertArrayNotHasKey('totals', $meta);
        self::assertArrayNotHasKey('groups', $meta);
    }

    public function testDataRequestSortsByTheGroupFirst(): void
    {
        $request = DataRequest::fromArray(['sort' => 'name,-team'], groupBy: 'team');
        self::assertSame([
            ['field' => 'team', 'direction' => 'asc'],
            ['field' => 'name', 'direction' => 'asc'],
        ], $request->sort);

        $request = DataRequest::fromArray(['sort' => '-team,name'], groupBy: 'team', totals: [
            'amount' => 'sum',
            'bad field' => 'sum',
            'hours' => 'median',
        ]);
        self::assertSame('desc', $request->sort[0]['direction']);
        self::assertSame(['amount' => 'sum'], $request->totals);
    }
}
