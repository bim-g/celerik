# Symfony example

Drop-in files for a Symfony 7 web application, showing a server-side Grid (Doctrine), a Chart, a validated Form, an editable Scheduler, an article form with a rich text editor and uploads, and a page with Tabs, a Modal and Notifications.

## Setup

```bash
symfony new caelrik-demo --webapp && cd caelrik-demo
composer config repositories.caelrik path ../caelrik
(cd ../caelrik && npm install && npm run build)   # a checkout has no built bundle; releases do
composer require caelrik/ui:@dev
```

Register the bundle in `config/bundles.php`:

```php
Caelrik\Ui\Adapters\Symfony\CaelrikUiBundle::class => ['all' => true],
```

Install the assets. This copies the package's `public/` folder to `public/bundles/caelrik`:

```bash
php bin/console assets:install
```

Copy the example files into the app:

| From `examples/symfony/` | To the app |
|---|---|
| `src/Controller/CaelrikDemoController.php`, `CaelrikCalendarController.php`, `CaelrikArticleController.php`, `CaelrikUiController.php`, `CaelrikFilesController.php`, `CaelrikThemeController.php` | `src/Controller/` |
| `src/Twig/CaelrikThemeExtension.php` | `src/Twig/` |
| `src/Entity/Product.php`, `Meeting.php` and their repositories | `src/Entity/`, `src/Repository/` |
| `templates/caelrik/*.html.twig` | `templates/caelrik/` |

Create the table and some data, for example with SQLite (`DATABASE_URL="sqlite:///%kernel.project_dir%/var/data.db"`):

```bash
php bin/console doctrine:schema:create
php bin/console dbal:run-sql "INSERT INTO product (name, category, price) VALUES ('Desk', 'Furniture', 249.00), ('Chair', 'Furniture', 129.00), ('Lamp', 'Lighting', 39.90), ('Monitor', 'Electronics', 199.00)"
symfony serve
```

The article form also needs the sanitizer: `composer require symfony/html-sanitizer`.

The file manager also needs elFinder and its assets:

```bash
composer require studio-42/elfinder
mkdir -p public/elfinder && cp -r vendor/studio-42/elfinder/{css,img,js,sounds} public/elfinder/
```

Then open `/caelrik/grid`, `/caelrik/chart`, `/caelrik/form`, `/caelrik/scheduler`, `/caelrik/article`, `/caelrik/ui`, `/caelrik/files` and, in the dev environment, `/caelrik/theme`.

## What it shows

- **`{{ caelrik_assets(asset('bundles/caelrik')) }}`** prints the `<link>` and `<script>` tags for the bundle. Load jQuery and Bootstrap before it. Add `customTheme: '...'` to load your own theme (see [THEMING.md](../../THEMING.md)).
- **`{{ caelrik(grid) }}`** renders any Caelrik component. The output is already escaped, and the function is marked `is_safe`.
- **`Grid::respond()` + `DoctrineDataSource`**
  - Paging, sorting and search run in DQL.
  - Select scalar fields (`p.id, p.name…`) so rows serialize cleanly to JSON.
- **Form**
  - The CSRF token is checked with `isCsrfTokenValid()`.
  - The Validator component stays the source of truth; its violations are passed back with `errors()`.
- **Scheduler + `DoctrineEventSource`**
  - Only meetings overlapping the visible range are queried.
  - Changes are POSTed as JSON with the `X-CSRF-TOKEN` header, checked with `isCsrfTokenValid()`. `Event::fromArray()` validates them.
  - Dates are converted to the server time zone before saving, because Doctrine drops UTC offsets.
  - If the server refuses a change (validation, CSRF, 404), the calendar rolls it back.
- **Article form (editor + uploads)**
  - FilePond uploads each file to `/caelrik/article/upload/{field}` with the token in the `X-CSRF-TOKEN` header, checked with `isCsrfTokenValid()`.
  - On submit, the ids are claimed into `var/uploads`, outside `public/`. Without JavaScript the real files are stored instead.
  - The editor HTML is cleaned with `EditorHtml::sanitize()` before it is stored or displayed.
- **Tabs, Modal and Notification** (`/caelrik/ui`)
  - The newsletter form sits in a Modal and reopens it with the errors when validation fails (`open()`).
  - On success the controller adds a flash message, and `Notification::make()->fromFlash($session->getFlashBag()->all())` shows it as a toast.
- **File manager** (`/caelrik/files`)
  - `CaelrikFilesController::isAllowed()` only allows the dev environment: **replace it** with your security rules (e.g. `isGranted('ROLE_FILE_MANAGER')`).
  - Files live in `var/files`, outside `public/`. elFinder's CSRF token protects every command that changes files; uploads are checked by content like `UploadHandler`.
- **Theme builder** (`/caelrik/theme`, dev environment only)
  - `CaelrikThemeController` shows the ThemeBuilder with a preview of a form, a grid, tabs and a chart. Save posts the theme as JSON with a CSRF token checked by `isCsrfTokenValid()`.
  - Saving writes `config/caelrik-theme.json` (keep it in git) and `public/css/caelrik-theme.css`. `base.html.twig` loads the saved theme on every page through `caelrik_saved_theme()` (`src/Twig/CaelrikThemeExtension.php`).
  - In CI, rebuild the stylesheet from the JSON: `vendor/bin/caelrik-theme build config/caelrik-theme.json public/css/caelrik-theme.css`.
