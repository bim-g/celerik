<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Gauge;
use Caelrik\Ui\Components\Sparkline;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GaugeSparklineTest extends TestCase
{
    public function testALineSparklineIsAnImageNamedByItsRange(): void
    {
        self::assertSame(
            '<svg id="revenue" class="caelrik-sparkline caelrik-sparkline-md caelrik-sparkline-primary" viewBox="0 0 100 25" '
            . 'role="img" '
            . 'aria-label="Revenue &lt;EUR&gt;, last 3 values: from 1,200 to 900.5, lowest 900.5, highest 1,500." '
            . 'focusable="false"><polyline class="caelrik-sparkline-line" points="0,12.51 50,2 100,23" fill="none">'
            . '</polyline></svg>',
            Sparkline::make('revenue')->label('Revenue <EUR>')->values([1200, 1500, 900.5])->render(),
        );
    }

    public function testSparklineTypesAndOptions(): void
    {
        $area = Sparkline::make()->label('A')->values([1, 2])->type('area')->markLast()->size('lg')->color('success')
            ->addClass('ms-2')->render();
        self::assertStringContainsString('class="caelrik-sparkline caelrik-sparkline-lg caelrik-sparkline-success ms-2"', $area);
        self::assertStringContainsString('<polygon class="caelrik-sparkline-area" points="0,25 0,23 100,2 100,25">', $area);
        self::assertStringContainsString('<circle class="caelrik-sparkline-dot" cx="100" cy="2" r="2.5">', $area);

        // Bars grow from zero: up for positive values, down (and outlined) for negative ones.
        $bars = Sparkline::make()->label('Profit')->values([3, -2, 0, 5])->type('bar')->render();
        self::assertStringContainsString('<rect class="caelrik-sparkline-bar" x="3.75" y="7.14" width="17.5" height="10.71">', $bars);
        self::assertStringContainsString(
            '<rect class="caelrik-sparkline-bar is-negative" x="28.75" y="17.86" width="17.5" height="7.14">',
            $bars,
        );
        self::assertStringContainsString('height="1"', $bars);

        $decorative = Sparkline::make()->values([1, 1])->decorative()->render();
        self::assertStringContainsString('aria-hidden="true"', $decorative);
        self::assertStringNotContainsString('role=', $decorative);
        // Equal values draw a line in the middle.
        self::assertStringContainsString('points="0,12.5 100,12.5"', $decorative);

        self::assertStringContainsString(
            'aria-label="Ventes : 2 valeurs"',
            Sparkline::make()->label('Ventes')->values([1, 2])->labels(['name' => '{label} : {count} valeurs'])->render(),
        );
    }

    public function testAnArcGaugeIsAMeterWithBandsAndATarget(): void
    {
        $html = Gauge::make('cpu')->label('CPU <load>')->value(72)->format('{value}%')
            ->band(0, 60, 'success', 'Normal')->band(60, 85, 'warning', 'High')->band(85, 100, 'danger', 'Critical')
            ->target(90)->render();

        self::assertStringStartsWith(
            '<div id="cpu" class="caelrik-gauge caelrik-gauge-arc caelrik-gauge-md">'
            . '<p class="caelrik-gauge-label" id="cpu-label">CPU &lt;load&gt;</p><div class="caelrik-gauge-body">'
            . '<div class="caelrik-gauge-meter" role="meter" aria-labelledby="cpu-label" aria-valuemin="0" '
            . 'aria-valuemax="100" aria-valuenow="72" aria-valuetext="72%, High, target 90%">'
            . '<svg class="caelrik-gauge-svg" viewBox="0 0 100 52" aria-hidden="true" focusable="false">'
            . '<path class="caelrik-gauge-track" d="M12 50A38 38 0 0 1 88 50"></path>'
            . '<path class="caelrik-gauge-range caelrik-gauge-success" d="M3 50A47 47 0 0 1 64.52 5.3"></path>',
            $html,
        );
        // The value takes its band's color, and the band's name is written.
        self::assertStringContainsString('<path class="caelrik-gauge-fill caelrik-gauge-warning" d="M12 50A38 38 0 0 1 74.22 20.72">', $html);
        self::assertStringContainsString('<line class="caelrik-gauge-target"', $html);
        self::assertStringContainsString(
            '<p class="caelrik-gauge-reading" aria-hidden="true"><span class="caelrik-gauge-value">72%</span> '
            . '<span class="caelrik-gauge-band">High</span></p></div>'
            . '<p class="caelrik-gauge-scale" aria-hidden="true"><span>0%</span><span>100%</span></p></div>',
            $html,
        );
        self::assertStringNotContainsString('style=', $html);
    }

    public function testALinearGaugeAndItsLimits(): void
    {
        $html = Gauge::make('quota')->label('Storage')->value(2500)->range(0, 2000)->format('{value} GB')
            ->linear()->scale(false)->color('info')->decimals(1)->labels(['target' => 'objectif {target}'])->target(1500)
            ->render();

        self::assertStringStartsWith(
            '<div id="quota" class="caelrik-gauge caelrik-gauge-linear caelrik-gauge-md"><div class="caelrik-gauge-head">'
            . '<p class="caelrik-gauge-label" id="quota-label">Storage</p>'
            . '<p class="caelrik-gauge-reading" aria-hidden="true"><span class="caelrik-gauge-value">2,500.0 GB</span></p></div>',
            $html,
        );
        // Above the maximum: the meter stays in its range, the text says the real value.
        self::assertStringContainsString('aria-valuenow="2000" aria-valuetext="2,500.0 GB, objectif 1,500.0 GB"', $html);
        self::assertStringContainsString('<rect class="caelrik-gauge-fill caelrik-gauge-info" x="0" y="4" width="100" height="6">', $html);
        self::assertStringContainsString('<line class="caelrik-gauge-target" x1="75" y1="0" x2="75" y2="12"', $html);
        self::assertStringNotContainsString('caelrik-gauge-scale', $html);

        // At the minimum there is no value arc.
        self::assertStringNotContainsString('caelrik-gauge-fill', Gauge::make()->label('Empty')->value(-5)->render());
    }

    public function testRequiredParts(): void
    {
        $renders = [
            static fn () => Sparkline::make()->label('A')->render(),
            static fn () => Sparkline::make()->values([1, 2])->render(),
            static fn () => Gauge::make()->value(1)->render(),
            static fn () => Gauge::make()->label('A')->render(),
        ];
        foreach ($renders as $render) {
            try {
                $render();
                self::fail('A required part was missing.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'sparkline one value' => [static fn () => Sparkline::make()->values([1])];
        yield 'sparkline text value' => [static fn () => Sparkline::make()->values([1, '2'])];
        yield 'sparkline type' => [static fn () => Sparkline::make()->type('pie')];
        yield 'sparkline size' => [static fn () => Sparkline::make()->size('xl')];
        yield 'sparkline color' => [static fn () => Sparkline::make()->color('light')];
        yield 'sparkline label' => [static fn () => Sparkline::make()->labels(['nope' => 'x'])];
        yield 'gauge range' => [static fn () => Gauge::make()->range(10, 10)];
        yield 'gauge format' => [static fn () => Gauge::make()->format('%')];
        yield 'gauge decimals' => [static fn () => Gauge::make()->decimals(5)];
        yield 'gauge band' => [static fn () => Gauge::make()->band(5, 1, 'success')];
        yield 'gauge band color' => [static fn () => Gauge::make()->band(0, 1, 'pink')];
        yield 'gauge shape' => [static fn () => Gauge::make()->shape('circle')];
        yield 'gauge size' => [static fn () => Gauge::make()->size('xl')];
        yield 'gauge label' => [static fn () => Gauge::make()->labels(['nope' => 'x'])];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
