# Theming

Celerik UI is built on **Bootstrap 5.3**, and Bootstrap is its only look. A Celerik theme changes that look with **design tokens**:
- ten colors for light mode and ten for dark mode;
- a corner radius;
- a font;
- a container shadow.

From the tokens Celerik writes a small stylesheet, loaded after `celerik.css`. Everything else (spacing, the shape of buttons and dialogs, table style) is Bootstrap's.

There are three ways to make one, and they all produce the same file:

| Tool | For | Output |
|---|---|---|
| [ThemeBuilder](#themebuilder) | Designing a theme with a live preview of real components and a contrast report | `theme.json`, `theme.css`, a PHP snippet |
| [`Theme` in PHP](#theme-in-php) | Themes in code or configuration, per tenant or per user | `Theme::toCss()` |
| [`celerik-theme` command](#command-line) | Keeping `theme.json` in git and building the CSS in CI | `theme.css` |

> **Upgrading from 0.1.x:** the Material and Tailwind looks (`celerik-material.css`, `celerik-tailwind.css`) were removed in 0.2.0 to keep Celerik simple to maintain.
> - Remove `theme: 'material'` or `theme: 'tailwind'` from `Assets::tags()`, `<x-celerik-assets>` or `celerik_assets()`.
> - In a saved `theme.json`, remove `"base"`, or set it to `"bootstrap"`.
>
> Your colors, radius, font and shadow keep working on Bootstrap.

## Tokens

The tokens are defined in [`theme/theme.schema.json`](theme/theme.schema.json). The Bootstrap preset, Bootstrap 5.3's own values, is in [`theme/presets/bootstrap.json`](theme/presets/bootstrap.json).

| Token | Used for | Contrast needed |
|---|---|---|
| `primary` | Buttons, links, active tabs, the current page, calendar events | 4.5:1 on `bodyBg` |
| `primaryHover` | Hovered and pressed buttons, hovered links | 4.5:1 on `bodyBg` |
| `onPrimary` | Text and icons on `primary` and `primaryHover` | 4.5:1 on both |
| `bodyBg` | Page background | – |
| `bodyColor` | Body text | 4.5:1 on `bodyBg` and `surface` |
| `muted` | Help texts, captions, placeholders | 4.5:1 on `bodyBg` and `surface` |
| `surface` | Grid header, upload area, toolbars | – |
| `border` | Dividers and decorative borders | – |
| `inputBorder` | Borders of form fields | 3:1 on `bodyBg` (WCAG 1.4.11) |
| `danger` | Error messages and invalid fields | 4.5:1 on `bodyBg` |

The three other tokens are optional; leave them out to keep Bootstrap's values:
- `radius`: in rem, from 0 to 2.
- `fontFamily`: a font stack of family names, for example `Inter, system-ui, sans-serif`. It does not load fonts.
- `shadow`: `none`, `subtle`, `elevated` or `outlined`.

The contrast pairs are listed once, in [`theme/contrast.json`](theme/contrast.json), and checked by PHP, the ThemeBuilder and the tests. Thresholds are not rounded: 4.497:1 fails 4.5:1.

## Loading a theme

```php
Assets::tags('/vendor/celerik', customTheme: '/css/acme.css');
```

```blade
<x-celerik-assets custom-theme="/css/acme.css" />
```

```twig
{{ celerik_assets(customTheme: '/css/acme.css') }}
```

The custom stylesheet is loaded last, after `celerik.css`.

## ThemeBuilder

```php
use Celerik\Ui\Components\ThemeBuilder;
use Celerik\Ui\Theme\Theme;

echo ThemeBuilder::make('builder')
    ->theme(Theme::fromJson(file_get_contents('theme.json')))   // default: the Bootstrap preset
    ->preview($grid, $form, $chart, $tabs)     // your own components
    ->saveUrl('/admin/theme')                  // optional
    ->csrf($token);
```

- **Editing:** every token has a labelled hex field and a color picker.
- **Preview:** it uses the stylesheet the theme will produce, applied to the preview area only, so the builder's own controls stay readable whatever the colors. It can be switched between light and dark.
- **Contrast report:** it updates as you type. Without JavaScript it is computed in PHP.
- **Buttons:**
  - **Download CSS**, **Download JSON**, **Copy PHP** (a `Theme::fromJson()` snippet) and **Import JSON**;
  - **Undo**;
  - **Reset**, back to the theme the builder started with.
- **Saving:** **Save** posts `{"theme": {...}}` as JSON with the CSRF token in the `X-CSRF-TOKEN` header. Without JavaScript the form posts its fields. Read both the same way:

```php
// Check authorization and the CSRF token first.
try {
    $theme = ThemeBuilder::fromRequest($_POST, file_get_contents('php://input'));
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    exit($e->getMessage());
}
file_put_contents(__DIR__ . '/theme.json', $theme->toJson());
file_put_contents(__DIR__ . '/public/css/theme.css', $theme->toCss());
```

The ThemeBuilder is a development tool: put it behind authorization or a local-only route.
- **Examples:** they show it in plain PHP, Laravel (only with `APP_ENV=local`) and Symfony (dev environment only), saving the theme and loading it on every page.
- **Translation:** texts can be translated with `labels([...])`.

## Theme in PHP

```php
use Celerik\Ui\Theme\Contrast;
use Celerik\Ui\Theme\Theme;

$theme = Theme::preset('bootstrap')
    ->name('Acme')
    ->color('light', 'primary', '#3b82f6')
    ->color('light', 'primaryHover', '#2563eb')
    ->radius(0.25)
    ->fontFamily('Inter, system-ui, sans-serif');

foreach (Contrast::failures($theme) as $failure) {
    echo $failure, "\n";
}
// FAIL light: Links and outline buttons on #ffffff = 3.67:1 (needs 4.5:1, WCAG 1.4.3)
// FAIL light: Primary button label on #3b82f6 = 3.67:1 (needs 4.5:1, WCAG 1.4.3)

$css = $theme->toCss();
$json = $theme->toJson();          // Theme::fromJson($json) reads it back
```

Every value is validated against a strict format (hex colors, plain font names, numbers). So a theme from user input, for example a tenant's brand colors, cannot inject CSS into the stylesheet. Cache the generated CSS: it only changes when the theme does.

## Command line

```bash
vendor/bin/celerik-theme preset bootstrap > theme.json    # start from Bootstrap's values
vendor/bin/celerik-theme check theme.json                 # contrast report, exit 1 on a failure
vendor/bin/celerik-theme build theme.json public/css/acme.css
```

`build` refuses a theme that fails a contrast pair unless you pass `--allow-contrast-failures`. Exit codes:
- 0: done;
- 1: a contrast check failed;
- 2: invalid usage or theme.

## How far a theme goes

A theme changes the colors, the corner radius, the font and the container shadows, in light and dark mode. For anything else:

- **CSS custom properties.** Override `--bs-*` and `--celerik-*` properties in your own stylesheet, loaded after the theme. No build step.
- **SCSS.** The variables in `resources/scss/_variables.scss` are `!default`. For changes custom properties cannot make, such as Bootstrap's breakpoints or spacing scale, compile your own entry file; `resources/scss/celerik.scss` is the starting point.

## Tests

- The Bootstrap preset matches Bootstrap 5.3's own colors and radius (`tests/js/presets.test.js`).
- `Theme::toCss()` and the ThemeBuilder's JavaScript write the same text, byte for byte (`tests/js/theme-css.test.js`).
- In a browser, a custom theme restyles every component in light and dark mode with no axe-core violations. A theme exported from the ThemeBuilder passes the same check (`tests/browser/custom-theme.spec.mjs`, `theme-builder-a11y.spec.mjs`).
