# Laravel example

Drop-in files for a fresh Laravel 11+ application (verified on Laravel 13), showing a server-side Grid (Eloquent), a Chart, a validated Form, an editable Scheduler, an article form with a rich text editor and uploads, and a page with Tabs, a Modal and Notifications.

## Setup

```bash
laravel new caelrik-demo && cd caelrik-demo      # SQLite by default
composer config repositories.caelrik path ../caelrik
(cd ../caelrik && npm install && npm run build)   # a checkout has no built bundle; releases do
composer require caelrik/ui:@dev
php artisan vendor:publish --tag=caelrik-assets   # copies public/ to public/vendor/caelrik
```

The service provider is auto-discovered, so no `config/app.php` change is needed.

Copy the example files into the app:

| From `examples/laravel/` | To the app |
|---|---|
| `app/Http/Controllers/CaelrikDemoController.php`, `CaelrikCalendarController.php`, `CaelrikArticleController.php`, `CaelrikUiController.php`, `CaelrikFilesController.php`, `CaelrikThemeController.php` | `app/Http/Controllers/` |
| `app/Models/Meeting.php` | `app/Models/` |
| `database/migrations/*_create_meetings_table.php` | `database/migrations/` (then run `php artisan migrate`) |
| `routes/caelrik.php` | `routes/` (then add `require __DIR__ . '/caelrik.php';` to `routes/web.php`) |
| `resources/views/caelrik/*.blade.php` | `resources/views/caelrik/` |

Seed some users and run the app:

```bash
php artisan tinker --execute="App\Models\User::factory(120)->create()"
php artisan serve
```

The article form also needs the sanitizer: `composer require symfony/html-sanitizer`.

The file manager also needs elFinder, its assets and an authorization gate:

```bash
composer require studio-42/elfinder
php artisan vendor:publish --tag=caelrik-elfinder   # copies elFinder's css, img, js, sounds to public/vendor/elfinder
```

```php
// app/Providers/AppServiceProvider.php, in boot(). Replace with your own rule, e.g. $user->is_admin.
Gate::define('manage-files', fn (?User $user) => app()->isLocal());
```

Then open `/caelrik/grid`, `/caelrik/chart`, `/caelrik/form`, `/caelrik/scheduler`, `/caelrik/article`, `/caelrik/ui`, `/caelrik/files` and, with `APP_ENV=local`, `/caelrik/theme`.

> **Windows:** `php artisan serve` starts PHP without the `TEMP`/`TMP` environment variables, so every upload fails with "unable to create a temporary file". Use `cd public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` instead, or set `upload_tmp_dir` in `php.ini`.

## What it shows

- **`<x-caelrik-assets />`** prints the `<link>` and `<script>` tags for the bundle. Load jQuery and Bootstrap before it. Add `custom-theme="..."` to load your own theme (see [THEMING.md](../../THEMING.md)).
- **`<x-caelrik :component="$grid" />`** renders any Caelrik component.
- **`Grid::respond()` + `EloquentDataSource`**
  - Paging, sorting and search run in SQL.
  - Only declared columns are returned, and `$hidden` attributes (such as `password`) are never exposed.
- **Form**
  - Client-side validation uses Parsley.
  - Laravel's `$request->validate()` stays the source of truth. Its errors and old input are passed back with `errors()` and `values()`.
- **Scheduler + `EloquentEventSource`**
  - Only meetings overlapping the visible range are queried.
  - Changes are POSTed as JSON with the `X-CSRF-TOKEN` header, which Laravel's CSRF middleware accepts natively. `Event::fromArray()` validates them.
  - Dates are converted to the app time zone before saving, because Eloquent drops UTC offsets.
  - If the server refuses a change (validation, CSRF, 404), the calendar rolls it back.
- **Article form (editor + uploads)**
  - FilePond uploads each file to `/caelrik/article/upload/{field}` with the CSRF header; `UploadHandler` validates it and answers an id.
  - On submit, the ids are claimed into `storage/app/private`, outside `public/`. Without JavaScript the real files are stored instead.
  - The editor HTML is cleaned with `EditorHtml::sanitize()` before it is stored or displayed.
- **Tabs, Modal and Notification** (`/caelrik/ui`)
  - The newsletter form sits in a Modal. When `$request->validate()` fails, `open($errors !== [])` reopens the modal with the errors.
  - On success the controller redirects `with('success', ...)`, and `Notification::make()->fromFlash(session()->only([...]))` shows it as a toast.
- **File manager** (`/caelrik/files`)
  - Both the page and the connector check the `manage-files` gate. Files live in `storage/app/private/files`, outside `public/`.
  - Requests carry Laravel's CSRF token (`FileManager::csrf()`) and elFinder's own token; uploads are checked by content like `UploadHandler`.
- **Theme builder** (`/caelrik/theme`, only when `APP_ENV=local`)
  - `CaelrikThemeController` shows the ThemeBuilder with a preview of a form, a grid, tabs and a chart. Save posts the theme as JSON; Laravel's CSRF middleware checks the `X-CSRF-TOKEN` header.
  - Saving writes `storage/app/caelrik-theme.json` (keep it in git) and `public/css/caelrik-theme.css`. The layout loads the saved theme on every page (`<x-caelrik-assets :custom-theme="..." />`).
  - In CI, rebuild the stylesheet from the JSON: `vendor/bin/caelrik-theme build storage/app/caelrik-theme.json public/css/caelrik-theme.css`.
