<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A map (Leaflet) with markers, and the same places written as a list under it.
 * - The list is the text alternative: each place's name, description and link, and a "Show on
 *   map" button that brings its marker into view, opens its popup and moves focus to it.
 * - The map is a named region: arrow keys move it, plus and minus zoom (said to screen
 *   readers); markers are buttons named after their place. Wheel zoom is off unless
 *   scrollZoom(), so the page scrolls past the map; animations stop for reduced motion.
 * - Tiles from OpenStreetMap by default; tiles() sets another server and its attribution.
 *
 *     Map::make('offices')->label('Our offices')
 *         ->marker(-1.6792, 29.2228, 'Goma', 'Avenue du Lac 12', url: '/offices/goma')
 *         ->marker(-2.5083, 28.8608, 'Bukavu', color: 'success');
 */
final class Map extends Component
{
    public const SIZES = ['sm', 'md', 'lg'];
    public const COLORS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];
    public const LABELS = [
        'map' => 'map',
        'places' => 'Places on the map',
        'show' => 'Show on map',
        'more' => 'More about {name}',
        'keys' => 'Arrow keys move the map, plus and minus zoom in and out.',
        'zoomIn' => 'Zoom in',
        'zoomOut' => 'Zoom out',
        'close' => 'Close',
        'noscript' => 'The map needs JavaScript. Its places are listed below.',
    ];

    private string $label = '';
    private ?float $lat = null;
    private ?float $lng = null;
    private ?int $zoom = null;
    private string $size = 'md';
    private bool $scrollZoom = false;
    private bool $places = true;
    private string $tiles = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
    private string $attribution = 'OpenStreetMap contributors';
    private ?string $attributionUrl = 'https://www.openstreetmap.org/copyright';
    private int $maxZoom = 19;

    /**
     * @var list<array{lat: float, lng: float, name: string, description: ?string, url: ?string, color: string}>
     */
    private array $markers = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'map';
    }

    /**
     * Names the map ("Our offices, map") and its list of places.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Where the map opens; by default it fits its markers.
     */
    public function center(float $lat, float $lng, ?int $zoom = null): self
    {
        $this->lat = self::latitude($lat);
        $this->lng = self::longitude($lng);
        if ($zoom !== null) {
            $this->zoom($zoom);
        }

        return $this;
    }

    public function zoom(int $zoom): self
    {
        if ($zoom < 0 || $zoom > 20) {
            throw new InvalidArgumentException('A map zoom is 0 to 20.');
        }
        $this->zoom = $zoom;

        return $this;
    }

    /**
     * A place: a marker named $name, with a description and a link in its popup and in the list.
     */
    public function marker(
        float $lat,
        float $lng,
        string $name,
        ?string $description = null,
        ?string $url = null,
        string $color = 'primary',
    ): self {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A map marker needs a name.');
        }
        if (!in_array($color, self::COLORS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown map color "%s".', $color));
        }
        $this->markers[] = [
            'lat' => self::latitude($lat),
            'lng' => self::longitude($lng),
            'name' => $name,
            'description' => $description,
            'url' => $url === null ? null : Url::safe($url),
            'color' => $color,
        ];

        return $this;
    }

    /**
     * sm (15rem), md (22.5rem, default) or lg (30rem) high.
     */
    public function size(string $size): self
    {
        if (!in_array($size, self::SIZES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown map size "%s".', $size));
        }
        $this->size = $size;

        return $this;
    }

    /**
     * Zooms with the mouse wheel (off by default, so the page scrolls past the map).
     */
    public function scrollZoom(bool $scrollZoom = true): self
    {
        $this->scrollZoom = $scrollZoom;

        return $this;
    }

    /**
     * The list of places under the map (shown by default). Hide it only when the same
     * places are written elsewhere on the page.
     */
    public function places(bool $places = true): self
    {
        $this->places = $places;

        return $this;
    }

    /**
     * The tile server ("https://…/{z}/{x}/{y}.png") and the attribution its licence asks for.
     */
    public function tiles(string $url, string $attribution, ?string $attributionUrl = null, int $maxZoom = 19): self
    {
        if (Url::safe(strtr($url, ['{z}' => '0', '{x}' => '0', '{y}' => '0', '{s}' => 'a', '{r}' => ''])) === null) {
            throw new InvalidArgumentException('Map tiles come from a relative, http or https URL.');
        }
        $this->tiles = $url;
        $this->attribution = $attribution;
        $this->attributionUrl = $attributionUrl === null ? null : Url::safe($attributionUrl);
        $this->maxZoom = $maxZoom;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown map label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'center' => $this->lat === null ? null : [$this->lat, $this->lng],
            'zoom' => $this->zoom,
            'scrollZoom' => $this->scrollZoom,
            'tiles' => $this->tiles,
            'attribution' => $this->attribution,
            'attributionUrl' => $this->attributionUrl,
            'maxZoom' => $this->maxZoom,
            'markers' => array_map(static fn (array $marker): array => [
                'lat' => $marker['lat'],
                'lng' => $marker['lng'],
                'name' => $marker['name'],
                'description' => $marker['description'],
                'url' => $marker['url'],
                'color' => $marker['color'],
            ], $this->markers),
            'labels' => array_intersect_key($this->labels, array_flip(['map', 'more', 'zoomIn', 'zoomOut', 'close'])),
        ];
    }

    public function render(): string
    {
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Map "%s" needs a label.', $this->id));
        }
        if ($this->markers === [] && $this->lat === null) {
            throw new LogicException(sprintf('Map "%s" needs a center or a marker.', $this->id));
        }
        $keysId = $this->id . '-keys';
        $canvas = Html::tag('div', [
            'class' => 'celerik-map-canvas celerik-map-' . $this->size,
            'id' => $this->id . '-canvas',
            'aria-describedby' => $keysId,
        ], Html::tag('p', ['class' => 'celerik-map-noscript'], Html::escape($this->labels['noscript'])))
            . Html::tag('p', ['class' => 'visually-hidden', 'id' => $keysId], Html::escape($this->labels['keys']));

        return Html::tag(
            'div',
            $this->rootAttributes(['celerik-map']),
            $canvas . ($this->places && $this->markers !== [] ? $this->placeList() : ''),
        );
    }

    private function placeList(): string
    {
        $items = '';
        foreach ($this->markers as $index => $marker) {
            $body = Html::tag('p', ['class' => 'celerik-map-place-name'], Html::escape($marker['name']));
            if ($marker['description'] !== null && $marker['description'] !== '') {
                $body .= Html::tag(
                    'p',
                    ['class' => 'celerik-map-place-description'],
                    Html::escape($marker['description']),
                );
            }
            $actions = '';
            if ($marker['url'] !== null) {
                $actions .= Html::tag('a', [
                    'href' => $marker['url'],
                    'class' => 'celerik-map-place-link',
                ], Html::escape(strtr($this->labels['more'], ['{name}' => $marker['name']])));
            }
            $actions .= Html::tag('button', [
                'type' => 'button',
                'class' => 'btn btn-sm celerik-action-button btn-outline-secondary celerik-map-show',
                'data-marker' => (string) $index,
                // The visible text first (WCAG 2.5.3), then the place.
                'aria-label' => $this->labels['show'] . ': ' . $marker['name'],
            ], Html::escape($this->labels['show']));
            $items .= Html::tag('li', [
                'class' => 'celerik-map-place celerik-map-place-' . $marker['color'],
            ], $body . Html::tag('div', ['class' => 'celerik-map-place-actions'], $actions));
        }

        return Html::tag('ul', [
            'class' => 'celerik-map-places',
            'aria-label' => $this->label . ': ' . $this->labels['places'],
        ], $items);
    }

    private static function latitude(float $lat): float
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException('A latitude is -90 to 90.');
        }

        return $lat;
    }

    private static function longitude(float $lng): float
    {
        if ($lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('A longitude is -180 to 180.');
        }

        return $lng;
    }
}
