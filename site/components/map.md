# Map

A map with markers, and the same places written as a list under it. With [Leaflet](https://leafletjs.com) and OpenStreetMap tiles.

<Demo name="map" :min-height="620" />

<<< @/demos/map.php

## Usage

```php
use Caelrik\Ui\Components\Map;

Map::make('offices')
    ->label('Our offices')
    ->marker(-1.6792, 29.2228, 'Goma', 'Avenue du Lac 12', url: '/offices/goma')
    ->marker(-2.5083, 28.8608, 'Bukavu', color: 'success');
```

- **`marker($lat, $lng, $name, $description, $url, $color)`**: a place. Its popup and its list item show the name, the description and a "More about…" link (relative, http or https).
- **The view** fits every marker; or **`center($lat, $lng, $zoom)`** and **`zoom()`** choose it.
- **`size()`**: `sm` (15rem), `md` (22.5rem, the default) or `lg` (30rem) high.
- **`scrollZoom()`** lets the mouse wheel zoom. It is off by default, so the page scrolls past the map instead of getting stuck in it.
- **`places(false)`** hides the list, only when the same places are written elsewhere on the page.
- **`tiles($url, $attribution, $attributionUrl, $maxZoom)`**: another tile server, with the attribution its licence asks for. OpenStreetMap's tiles are the default; read its [tile usage policy](https://operations.osmfoundation.org/policies/tiles/) before heavy use, or use your own server or a provider.
- **`labels()`** translates the texts (`map`, `places`, `show`, `more`, `keys`, `zoomIn`, `zoomOut`, `close`, `noscript`).

## Accessibility

- **The list of places is the text alternative**: every name, description and link is written under the map, and works without JavaScript. Its "Show on map" buttons bring the marker into view, open its popup and move focus to the marker.
- **A named region** ("Our offices, map"), reached with Tab. A hidden text says how to use it: the arrow keys move the map, plus and minus zoom.
- **Markers are buttons** named after their place. Enter opens their popup, and Escape closes it with focus staying on the marker. The zoom and close buttons answer Enter and Space.
- **Popups are built from text**, never from HTML, and themed like the page in light and dark mode. In dark mode the tiles are darkened too.
- **Reduced motion**: no zoom, fade or pan animations.
- **No inline styles from PHP**. Leaflet moves the map with the CSSOM, which a strict Content Security Policy allows; allow the tile server in `img-src`.
