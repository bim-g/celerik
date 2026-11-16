# Changelog

All notable changes to Celerik UI are listed here, newest first. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/). Before 1.0, a minor version (0.x.0) may include breaking changes; they are listed under **Changed** or **Removed**.

The same version is published on Packagist (`celerik/ui`) and npm (`@celerikui/ui`).

## [Unreleased]

### Changed

- **A flat design for every component** (`resources/scss/_flat.scss`): 1px borders instead of drop shadows, and no bevels or gradients.
  - Toasts, searchable-select dropdowns and the calendar's popovers and event dialog have borders instead of shadows.
  - The calendar toolbar has outline buttons; the current view and the New event button are filled with the primary color. Weekday names and day numbers are plain text, underlined on hover and focus.
  - The file manager has a light, flat toolbar, sidebar and status bar, and borderless toolbar buttons.
  - The upload area is a plain dashed panel that follows dark mode.
  - Charts use a solid palette (`--celerik-chart-1` to `6`, with 3:1 and more on the page in light and dark mode), and take their text, grid lines and font from Bootstrap. Colors set in PHP still win.
- Field borders have a 3:1 contrast on the page (WCAG 1.4.11) without a custom theme: `--celerik-input-border` (#6c757d light, #8c959f dark) on inputs, selects, checkboxes, searchable selects, the rich text editor and one-time code boxes. Before, Bootstrap's #dee2e6 border (1.3:1) was used unless a custom theme set one.
- A theme's stylesheet no longer fills the calendar's buttons: the calendar follows `--bs-primary` and `--celerik-on-primary` through celerik.css.

## [0.2.0] - 2026-11-16

### Removed

- **The Material and Tailwind looks.** Celerik UI is now Bootstrap only, which keeps it simple to maintain: `celerik-material.css`, `celerik-tailwind.css`, their presets and their SCSS are gone, along with the ThemeBuilder's "Base look" and "Start from a preset" controls and the documentation's look picker. Your own colors, radius, font and shadow still work through a custom theme (`Theme::toCss()`, the ThemeBuilder, `celerik-theme`). To upgrade:
  - Remove `theme: 'material'` / `'tailwind'` from `Assets::tags()`, `<x-celerik-assets>` and `celerik_assets()`; they now throw an exception that explains the change. The `theme` parameter is deprecated: `bootstrap` is its only value.
  - In a saved `theme.json`, remove `"base"` (or keep `"base": "bootstrap"`); a `material` or `tailwind` base is refused with the same explanation. Themes no longer write `base`.
  - `Theme::BASES`, `Theme::base()`, `Theme::getBase()` and `ThemeBuilder::BASE_NAMES` are removed; `Theme::PRESETS` lists the presets (`bootstrap`). `ThemeBuilder::assetsUrl()` is deprecated and has no effect, and `labels()` ignores the `base`, `preset` and `loadPreset` keys.
  - `celerik-theme preset` takes `bootstrap` only, and `build` prints `Wrote <file>.`

### Changed

- A theme's stylesheet starts with `/* Celerik UI theme "Acme". Load it after celerik.css. */` and always binds Bootstrap's components to the theme colors.
- The SCSS entry is `resources/scss/celerik.scss` (it was `resources/scss/themes/bootstrap.scss`).

## [0.1.6] - 2026-11-16

### Added

- Key-value list: `Field::keyValue('headers')` edits pairs (metadata, HTTP headers, settings), a row per pair with Add and Remove. Rows submit as `headers[0][key]` and `headers[0][value]`; `Field::keyValues()` turns them into a `key => value` array, and `value()` takes one. A value needs a key and a key is used once (checked in the browser, the message in its row); `keySuggestions()` and translatable `keyValueLabels()`.
- One-time code field: `Field::otp('code')` is one input shown as a box per character, so phones and browsers can fill the code they received (`autocomplete="one-time-code"`), a pasted `123 456` fills every box, and screen readers hear one text field. `codeLength()` (4 to 10), `alphanumeric()`, and a clear default message.
- Tags input: `Field::tags('skills', ['PHP', 'Laravel'])` takes free-text tags (Enter, a comma or paste) and offers suggestions, local or `remote()`. Submits `skills[]`; `minTags()`, `maxTags()` and a translatable `addText()`. Case-insensitive duplicates are not added, and Enter does not submit the form while a tag is being typed.
- Rating field: `Field::rating('score')` shows 3 to 10 stars (`stars()`), which are native radio buttons: arrow keys, validation and old input work as for a radio group, and no JavaScript is needed. An optional rating has a "No rating" choice; `ratingLabels()` translates the texts.
- Form repeater: `Repeater::make('lines')->schema(...)` repeats a group of fields, submitted as `lines[0][product]`. Users add, remove and (with `reorderable()`) move rows; `min()`, `max()`, `defaultItems()`, `value()` and translatable `labels()`. Row keys are kept, so old input, errors such as `lines.3.qty` and the error summary find their row ([#97](https://github.com/kivudesign/celerik/pull/97)).

### Development

- Releasing is running the Release workflow with the version: the entries under `[Unreleased]` become the version's section with the date and compare links, committed to `main` by the workflow. A section written beforehand is used as it is.

## [0.1.5] - 2026-11-13

### Added

- Form wizard: `Form::wizard()` shows the form's sections one at a time, with a step indicator and Back and Next buttons. Next checks the step's fields first, Enter means Next, and after a failed submit (browser or server) the form shows the first step with an error. Its texts can be translated ([#95](https://github.com/kivudesign/celerik/pull/95)).
- Form layout: `Section::make()` (a card with a heading, `collapsible()`) and `Section::fieldset()` group fields; `columns()` on a form or section lays them out in 1 to 6 columns from a breakpoint, and `columnSpan()` widens a field or section. A collapsed section opens by itself when a field inside it has an error ([#95](https://github.com/kivudesign/celerik/pull/95)).
- Date and time fields: `Field::time()`, `Field::datetime()`, and `Field::dateRange($name, $label, 'date'|'time'|'datetime-local')`, two linked native inputs (`name[start]`, `name[end]`) whose end must be on or after the start ([#94](https://github.com/kivudesign/celerik/pull/94)).
- Searchable selects ([Tom Select](https://tom-select.js.org)): `Field::select()->searchable()`, `->multiple()` (tags), and `->remote($url)` to load choices from an endpoint as the user types, answered by `$field->respond($source, $_GET)` with any data source ([#92](https://github.com/kivudesign/celerik/pull/92)).
- Form fields: `Field::radio()` and `Field::checkboxes()` (groups in a fieldset, `inline()`), `Field::switch()`, `Field::range()` (shows its value) and `Field::color()`; `step()` for number, range, date and time fields ([#91](https://github.com/kivudesign/celerik/pull/91)).

### Changed

- The roadmap aims at Telerik UI's breadth with Filament's depth for admin screens, in phases by family of components. Celerik UI stays free and open source, with no paid or premium editions ([#93](https://github.com/kivudesign/celerik/pull/93)).

### Fixed

- Client-side validation rejected every time and date-time field with a `min()` or `max()`: Parsley compared them as numbers. Bounds now compare numbers as numbers and ISO dates and times as text, and `17:00:00` equals `17:00` ([#94](https://github.com/kivudesign/celerik/pull/94)).
- Server errors reported on one item of a field that submits an array (Laravel: `tags.1`, `languages.0`, `photos.0`) were not shown; they now show under the field and in the error summary ([#94](https://github.com/kivudesign/celerik/pull/94)).

### Development

- Release notes are grouped by kind of change, pull requests are labeled from their title, and a release needs its CHANGELOG section ([#88](https://github.com/kivudesign/celerik/pull/88)).

## [0.1.3] - 2026-11-12

### Added

- jQuery 4 support: the peer dependency accepts `^3.7.1 || ^4.0.0`, and the tests run on jQuery 4 ([#83](https://github.com/kivudesign/celerik/pull/83)).

### Changed

- Docs and examples load `jquery@4.0.0` from the CDN ([#83](https://github.com/kivudesign/celerik/pull/83)).
- The roadmap details Phase 4, forms ([#81](https://github.com/kivudesign/celerik/pull/81)).

### Development

- Lockfiles are committed, and CI installs from them. A CI job also tests the lowest dependency versions `composer.json` allows ([#63](https://github.com/kivudesign/celerik/pull/63)).
- Dependabot opens weekly update pull requests for npm, Composer and GitHub Actions ([#63](https://github.com/kivudesign/celerik/pull/63)).
- Faster browser tests: they wait for animations to finish instead of fixed delays, and run on 4 workers on CI ([#82](https://github.com/kivudesign/celerik/pull/82)).

## [0.1.1] - 2026-11-11

### Fixed

- Scheduler: day numbers on today's cell were below 4.5:1 contrast. They now use the body text color ([#59](https://github.com/kivudesign/celerik/pull/59)).

### Development

- GitHub Actions run on Node.js 24 ([#61](https://github.com/kivudesign/celerik/pull/61)).

## [0.1.0] - 2026-11-11

The first release with the full component set, the prebuilt bundle and the npm package. Version 0.0.2 was published the same day with the same content.

### Added

- **Scheduler** (FullCalendar), with Eloquent and Doctrine event sources ([#19](https://github.com/kivudesign/celerik/pull/19)).
- **File uploads** (FilePond) and a **rich text editor** (Quill) for forms ([#20](https://github.com/kivudesign/celerik/pull/20)).
- **Modal**, **Tabs** and **Notification** components ([#21](https://github.com/kivudesign/celerik/pull/21)).
- **File Manager** (elFinder), with a locked-down configuration ([#22](https://github.com/kivudesign/celerik/pull/22)).
- Material and Tailwind-inspired themes, dark mode on every theme ([#23](https://github.com/kivudesign/celerik/pull/23)).
- Accessibility conformance statement and a screen-reader test plan ([#34](https://github.com/kivudesign/celerik/pull/34)).
- **ThemeBuilder**: theme tokens, `Theme` in PHP, the builder component and the `celerik-theme` CLI ([#45](https://github.com/kivudesign/celerik/pull/45)).
- Documentation site with live demos, guides and the API reference, on GitHub Pages ([#55](https://github.com/kivudesign/celerik/pull/55)).
- The bundle is built at release time and published to npm as `@celerikui/ui`, served by jsDelivr (`Assets::cdnUrl()`) ([#56](https://github.com/kivudesign/celerik/pull/56), [#57](https://github.com/kivudesign/celerik/pull/57)).

### Changed

- The bundle is split into chunks: each page loads only the code of the components it uses ([#18](https://github.com/kivudesign/celerik/pull/18)).

### Fixed

- `LIKE` wildcards (`%`, `_`) in Grid search terms are escaped ([#18](https://github.com/kivudesign/celerik/pull/18)).
- Contrast fixes in dark mode ([#23](https://github.com/kivudesign/celerik/pull/23)).

## [0.0.1] - 2026-11-04

The Phase 1 MVP ([#11](https://github.com/kivudesign/celerik/pull/11)).

### Added

- Core: the component contract, registry, assets helper and data layer (`DataRequest`, `DataResult`, `ArrayDataSource`).
- **Grid** (DataTables), **Chart** (Chart.js) and **Form** (Parsley.js and Inputmask) components.
- Laravel adapter (Blade components, Eloquent) and Symfony adapter (bundle, Twig, Doctrine).
- Bootstrap SCSS theme and JS wrappers.
- Examples for plain PHP, Laravel and Symfony.

[Unreleased]: https://github.com/kivudesign/celerik/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/kivudesign/celerik/compare/v0.1.6...v0.2.0
[0.1.6]: https://github.com/kivudesign/celerik/compare/v0.1.5...v0.1.6
[0.1.5]: https://github.com/kivudesign/celerik/compare/v0.1.3...v0.1.5
[0.1.3]: https://github.com/kivudesign/celerik/compare/v0.1.1...v0.1.3
[0.1.1]: https://github.com/kivudesign/celerik/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/kivudesign/celerik/compare/v0.0.1...v0.1.0
[0.0.1]: https://github.com/kivudesign/celerik/releases/tag/v0.0.1
