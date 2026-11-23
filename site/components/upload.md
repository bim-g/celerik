# File upload

File fields, plain or with [FilePond 4](https://pqina.nl/filepond/): previews, progress, and each file uploaded before the form is submitted. Files are checked by their content, not by their name.

Uploads need a server, so there is no live demo of them on this site: see `examples/plain/article.php` in the repository. The [image editor](#editing-a-picture) has one: it works in the page.

## A plain file input

```php
use Caelrik\Ui\Upload\UploadRules;

Field::file('cv', 'CV')->rules(UploadRules::documents()->maxSize('5M'))->required();
```

The form switches to `multipart/form-data`. Store the file with the same rules:

```php
use Caelrik\Ui\Upload\{UploadedFile, UploadException, UploadHandler};

$handler = new UploadHandler(UploadRules::documents()->maxSize('5M'), $temporaryDirectory);

try {
    $stored = $handler->store(UploadedFile::fromGlobals($_FILES['cv'])[0], $privateDirectory);
} catch (UploadException $e) {
    $errors['cv'] = $e->getMessage();
}
```

## Editing a picture

<Demo name="form-image" :min-height="640" />

<<< @/demos/form-image.php

`imageEditor()` lets people crop, rotate and flip a picture before the form sends it: a profile picture, a product photo. Once a picture is chosen, the editor opens under the field, and the edited picture replaces the chosen file in the input, so the server receives it as any upload (store it with an `UploadHandler`, as above).

```php
Field::file('picture', 'Profile picture')->imageEditor(['1:1'], 512);
```

- **`$ratios`**: the shapes offered, `'free'` and `'width:height'` such as `'1:1'` or `'16:9'`; the first one is chosen first (`['free', '1:1', '4:3', '16:9']` by default). With one shape, there is no choice to make.
- **`$maxSize`**: the longest side of the picture sent, in pixels (none by default).
- **`imageEditorLabels([...])`** translates the texts (see `Field::IMAGE_EDITOR_LABELS`).

The picture keeps its type (JPEG, PNG, WebP; others become PNG) and its name, and the camera's orientation is applied first. It works with one picture sent with the form: not with `upload()` (FilePond) or several files. Nothing is loaded from elsewhere, and no Content Security Policy change is needed (the picture is read with `createImageBitmap`, not a `blob:` URL). Without JavaScript the file is sent as chosen.

## Asynchronous uploads (FilePond)

```php
Field::file('cover', 'Cover image')->rules(UploadRules::images()->maxSize('2M'))->upload('/upload/cover');
Field::file('attachments')->rules(UploadRules::documents()->maxFiles(3))->upload('/upload/attachments');
```

Each file is posted to the endpoint first, with the form's CSRF token in the `X-CSRF-TOKEN` header. The form then submits the ids of the uploaded files, which you claim:

```php
$handler = new UploadHandler(UploadRules::images()->maxSize('2M'), $temporaryDirectory);

// POST /upload/cover: one file, answer its id (text/plain)
echo $handler->process(UploadedFile::fromGlobals(current($_FILES))[0]);

// DELETE /upload/cover: the body is the id (the user removed the file)
$handler->revert(trim(file_get_contents('php://input')));

// On form submit: move the files to their final directory (one id per file)
$cover = $handler->claim($_POST['cover'], $privateDirectory);
$attachments = array_map(
    static fn (string $id) => $attachmentHandler->claim($id, $privateDirectory),
    (array) ($_POST['attachments'] ?? []),
);
```

Run `$handler->purge(86400)` from a cron job to delete uploads never claimed.

## Rules

| Method | Use |
|---|---|
| `UploadRules::images()` | JPEG, PNG, GIF and WebP. |
| `UploadRules::documents()` | PDF, Word, Excel, OpenDocument text and plain text. |
| `UploadRules::types('image/png', 'application/pdf')` | Known MIME types. |
| `withType('image/svg+xml', ['svg'])` | Adds a type and its extensions. |
| `maxSize('5M')`, `maxFiles(3)` | Size per file (bytes, or K, M, G), and the number of files. |

The same rules set the input's `accept` attribute and the help text ("PNG, JPG. Up to 2 MB per file.").

## Security

- The type is detected from the content (`finfo`), and must match the extension: a PHP file named `photo.png`, or `shell.php.gif`, is refused.
- Files get random names and are stored where you choose: keep them outside the web root, and serve them through a controller.
- Every claimed file is checked again; ids can only be claimed once.

## Accessibility

FilePond's browse button is labelled by the field label and its help text, works from the keyboard, and announces progress and errors. Without FilePond support, a plain file input is used.

The image editor is a group named "Edit the picture", with named buttons (rotate, flip, undo) and a labelled shape select. The **crop area** is moved with the mouse or a finger (drag it, or a corner to resize it), or with the keyboard: it is a focus stop where the arrow keys move it and Shift with the arrow keys resizes it, as the text under it says. Its **position and size are also number fields** (left, top, width, height), the way for screen readers, where Enter applies the number. A status line says the size of the picture sent. The crop area's edges are drawn dark, light and dark, seen on any picture, with a thicker edge when focused; its corners are 24 pixels.
