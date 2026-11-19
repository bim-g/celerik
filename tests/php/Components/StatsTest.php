<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Stat;
use Celerik\Ui\Components\Stats;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    public function testAStatShowsItsLabelValueAndTrendInWords(): void
    {
        $html = Stat::make('revenue')->label('Revenue <net>')->value('$12,400')
            ->trend(12.5, 'vs last month')->description('Paid invoices.')->render();

        self::assertStringStartsWith(
            '<div id="revenue" class="celerik-stat card celerik-stat-primary" data-celerik="stat"',
            $html,
        );
        self::assertStringContainsString(
            '<p class="celerik-stat-label">Revenue &lt;net&gt;</p><p class="celerik-stat-value">$12,400</p>'
            . '<p class="celerik-stat-trend celerik-stat-trend-good"><span aria-hidden="true" class="celerik-stat-arrow">'
            . '▲</span> <span class="celerik-stat-change">Up 12.5%</span> '
            . '<span class="celerik-stat-period">vs last month</span></p>'
            . '<p class="celerik-stat-description">Paid invoices.</p>',
            $html,
        );
        self::assertStringNotContainsString('<svg', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testTheTrendsMoodFollowsWhichWayIsGood(): void
    {
        $down = Stat::make('churn')->label('Churn')->value('2%')->trend(-0.5, '', ' pts');
        self::assertStringContainsString('celerik-stat-trend-bad', $down->render());
        self::assertStringContainsString('>Down 0.5 pts</span>', $down->render());
        self::assertStringContainsString('celerik-stat-trend-good', $down->goodWhen('down')->render());

        $flat = Stat::make('f')->label('F')->value(0)->trend(0)->labels(['flat' => 'Stable'])->render();
        self::assertStringContainsString('celerik-stat-trend-flat', $flat);
        self::assertStringContainsString('>Stable</span>', $flat);
    }

    public function testNumbersAreFormatted(): void
    {
        self::assertStringContainsString('>1,204</p>', Stat::make('a')->label('A')->value(1204)->render());
        self::assertStringContainsString('>1,204.5</p>', Stat::make('a')->label('A')->value(1204.50)->render());
        self::assertStringContainsString('>Up 1,000%</span>', Stat::make('a')->label('A')->trend(1000)->render());
    }

    public function testTheSparklineIsANamedSvgScaledToItsValues(): void
    {
        $html = Stat::make('visits')->label('Visits')->value('900')->sparkline([10, 20, 15])->color('warning')->render();

        self::assertStringContainsString('celerik-stat-warning', $html);
        self::assertStringContainsString(
            '<svg class="celerik-stat-sparkline" viewBox="0 0 100 32" preserveAspectRatio="none" role="img" '
            . 'aria-label="Visits, last 3 values: from 10 to 15, lowest 10, highest 20." focusable="false">',
            $html,
        );
        // The lowest value at the bottom (y 30), the highest at the top (y 2).
        self::assertStringContainsString('<polyline class="celerik-stat-line" points="0,30 50,2 100,16"', $html);
        self::assertStringContainsString('points="0,32 0,30 50,2 100,16 100,32"', $html);

        $flat = Stat::make('f')->label('F')->sparkline([5, 5])->render();
        self::assertStringContainsString('points="0,16 100,16"', $flat);
    }

    public function testTheLinkCoversTheTileAndMustBeSafe(): void
    {
        self::assertStringContainsString(
            '<p class="celerik-stat-label"><a href="/reports" class="stretched-link">Sales</a></p>',
            Stat::make('s')->label('Sales')->link('/reports')->render(),
        );
        self::assertStringNotContainsString('<a', Stat::make('s')->label('Sales')->link('javascript:alert(1)')->render());
    }

    public function testStatsAreANamedList(): void
    {
        $html = Stats::make('kpis')->label('This month')->columns(3)
            ->add(Stat::make('a')->label('A'), Stat::make('b')->label('B'))
            ->render();

        self::assertStringStartsWith(
            '<ul id="kpis" class="celerik-stats celerik-stats-cols-3" data-celerik="stats" data-celerik-options="{}" '
            . 'aria-label="This month"><li><div id="a" class="celerik-stat card',
            $html,
        );
        self::assertSame(2, substr_count($html, '<li>'));
    }

    public function testAStatNeedsALabelAndStatsNeedAStat(): void
    {
        $this->expectException(LogicException::class);
        Stat::make('a')->value(1)->render();
    }

    public function testEmptyStatsAreRefused(): void
    {
        $this->expectException(LogicException::class);
        Stats::make('k')->render();
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'one value' => [static fn () => Stat::make('a')->sparkline([1])];
        yield 'not numbers' => [static fn () => Stat::make('a')->sparkline([1, '2'])];
        yield 'bad color' => [static fn () => Stat::make('a')->color('pink')];
        yield 'bad direction' => [static fn () => Stat::make('a')->goodWhen('sideways')];
        yield 'unknown label' => [static fn () => Stat::make('a')->labels(['nope' => 'x'])];
        yield 'no columns' => [static fn () => Stats::make('k')->columns(0)];
        yield 'seven columns' => [static fn () => Stats::make('k')->columns(7)];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
