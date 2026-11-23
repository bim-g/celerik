# Getting started

Caelrik UI is a set of PHP components that render accessible Bootstrap 5 markup. A small JavaScript bundle then enhances them with proven open-source libraries (DataTables, Chart.js, Parsley.js, FullCalendar, Quill, FilePond, Notyf, Leaflet, elFinder), each loaded only on the pages that use it.

You build a component in PHP, print it in your template, and, for server-side data, answer its requests with the same definition.

## Requirements

- PHP 8.2 or later, with `ext-fileinfo` for uploads
- Bootstrap 5.3 (CSS, and `bootstrap.bundle.js` for Modal and Tabs) and jQuery 3.7 or 4 on the page
- Optional: Laravel 11+ or Symfony 7 for the adapters

## Install

```bash
composer require caelrik/ui
```

Then make the bundle (the package's `public/` folder) available to the browser:

::: code-group

```bash [Laravel]
php artisan vendor:publish --tag=caelrik-assets   # copies it to public/vendor/caelrik
```

```bash [Symfony]
# Register Caelrik\Ui\Adapters\Symfony\CaelrikUiBundle in config/bundles.php, then:
php bin/console assets:install                    # copies it to public/bundles/caelrik
```

```bash [Plain PHP]
cp -r vendor/caelrik/ui/public public/vendor/caelrik
```

:::

Publish the assets again after every Caelrik update. Releases include the built bundle, so no Node.js is needed.

### Or use the CDN or npm

Instead of publishing the assets, load the bundle of the installed version from jsDelivr:

```php
<?= Caelrik\Ui\Assets::tags(Caelrik\Ui\Assets::cdnUrl()) ?>
```

```blade
<x-caelrik-assets :base-url="Caelrik\Ui\Assets::cdnUrl()" />
```

Allow `https://cdn.jsdelivr.net` in `script-src` and `style-src` if you use a [Content Security Policy](./csp). With a JavaScript bundler (Vite, webpack), install it from npm instead:

```bash
npm install @caelrik/ui
```

```js
import '@caelrik/ui';                       // registers the components and starts them
import '@caelrik/ui/caelrik.css';
```

The PHP version and the npm version must match: the components and the bundle change together.

## Load the assets

Load Bootstrap and jQuery first, then the Caelrik tags in the `<head>`:

::: code-group

```blade [Blade]
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<x-caelrik-assets />
```

```twig [Twig]
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
{{ caelrik_assets() }}
```

```php [Plain PHP]
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/jquery@4.0.0/dist/jquery.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<?= Caelrik\Ui\Assets::tags('/vendor/caelrik') ?>
```

:::

The tags are a stylesheet and one ES module (`caelrik.js`). Module scripts are deferred, so they run after jQuery and Bootstrap.

## Your first component

<Demo name="grid" :min-height="420" />

<<< @/demos/grid.php

Print it in your template:

::: code-group

```blade [Blade]
<x-caelrik :component="$grid" />
```

```twig [Twig]
{{ caelrik(grid) }}
```

```php [Plain PHP]
<?= $grid ?>
```

:::

The grid works without JavaScript too: PHP renders a real `<table>` with its caption and header scopes, and the bundle turns it into a sortable, searchable, paged grid.

## Next steps

- [How it works](./how-it-works): rendering, options and the JavaScript API.
- [Data endpoints](./data): server-side grids and calendars with `respond()`.
- Set up [plain PHP](./plain-php), [Laravel](./laravel) or [Symfony](./symfony).
- Browse the [components](../components/).
