<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Map;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MapTest extends TestCase
{
    public function testAMapAndTheListOfItsPlaces(): void
    {
        $html = Map::make('offices')->label('Our <offices>')
            ->marker(-1.6792, 29.2228, 'Goma <HQ>', 'Avenue du Lac 12', url: '/offices/goma')
            ->marker(-2.5083, 28.8608, 'Bukavu', url: 'javascript:alert(1)', color: 'success')
            ->render();

        self::assertStringStartsWith('<div id="offices" class="caelrik-map" data-caelrik="map" data-caelrik-options="', $html);
        self::assertStringContainsString(
            '<div class="caelrik-map-canvas caelrik-map-md" id="offices-canvas" aria-describedby="offices-keys">'
            . '<p class="caelrik-map-noscript">The map needs JavaScript. Its places are listed below.</p></div>'
            . '<p class="visually-hidden" id="offices-keys">Arrow keys move the map, plus and minus zoom in and out.</p>',
            $html,
        );
        self::assertStringContainsString(
            '<ul class="caelrik-map-places" aria-label="Our &lt;offices&gt;: Places on the map">'
            . '<li class="caelrik-map-place caelrik-map-place-primary"><p class="caelrik-map-place-name">Goma &lt;HQ&gt;</p>'
            . '<p class="caelrik-map-place-description">Avenue du Lac 12</p><div class="caelrik-map-place-actions">'
            . '<a href="/offices/goma" class="caelrik-map-place-link">More about Goma &lt;HQ&gt;</a>'
            . '<button type="button" class="btn btn-sm caelrik-action-button btn-outline-secondary caelrik-map-show" '
            . 'data-marker="0" aria-label="Show on map: Goma &lt;HQ&gt;">Show on map</button></div></li>',
            $html,
        );
        // An unsafe link is dropped.
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testItsOptionsForJavaScript(): void
    {
        $map = Map::make('m')->label('Shop')->center(-1.5, 29.5, 12)->scrollZoom()->size('lg')
            ->tiles('/tiles/{z}/{x}/{y}.png', 'Local', '/licence', 17)
            ->labels(['zoomIn' => 'Zoomer', 'show' => 'Voir'])
            ->marker(-1.6792, 29.2228, 'Shop', 'Open 9 to 5', color: 'danger');

        self::assertSame([
            'label' => 'Shop',
            'center' => [-1.5, 29.5],
            'zoom' => 12,
            'scrollZoom' => true,
            'tiles' => '/tiles/{z}/{x}/{y}.png',
            'attribution' => 'Local',
            'attributionUrl' => '/licence',
            'maxZoom' => 17,
            'markers' => [[
                'lat' => -1.6792,
                'lng' => 29.2228,
                'name' => 'Shop',
                'description' => 'Open 9 to 5',
                'url' => null,
                'color' => 'danger',
            ]],
            'labels' => [
                'map' => 'map',
                'more' => 'More about {name}',
                'zoomIn' => 'Zoomer',
                'zoomOut' => 'Zoom out',
                'close' => 'Close',
            ],
        ], $map->toArray());
        $html = $map->render();
        self::assertStringContainsString('caelrik-map-canvas caelrik-map-lg', $html);
        self::assertStringContainsString('aria-label="Voir: Shop">Voir</button>', $html);

        // Without the list, and with a center only.
        self::assertStringNotContainsString('caelrik-map-places', Map::make()->label('A')->center(0, 0)->render());
        self::assertStringNotContainsString(
            'caelrik-map-places',
            Map::make()->label('A')->marker(1, 2, 'B')->places(false)->render(),
        );
    }

    public function testRequiredParts(): void
    {
        $renders = [
            static fn () => Map::make()->marker(1, 2, 'A')->render(),
            static fn () => Map::make()->label('A')->render(),
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
        yield 'latitude' => [static fn () => Map::make()->marker(91, 0, 'A')];
        yield 'longitude' => [static fn () => Map::make()->center(0, -181)];
        yield 'zoom' => [static fn () => Map::make()->zoom(21)];
        yield 'name' => [static fn () => Map::make()->marker(0, 0, ' ')];
        yield 'color' => [static fn () => Map::make()->marker(0, 0, 'A', color: 'pink')];
        yield 'size' => [static fn () => Map::make()->size('xl')];
        yield 'tiles' => [static fn () => Map::make()->tiles('javascript:{z}', 'X')];
        yield 'label' => [static fn () => Map::make()->labels(['nope' => 'x'])];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
