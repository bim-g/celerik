# PDF viewer

A PDF document shown in the page with [PDF.js](https://mozilla.github.io/pdf.js/): its pages, their text, a toolbar and a download link.

<Demo name="pdfviewer" :min-height="560" />

<<< @/demos/pdfviewer.php

## Usage

```php
use Caelrik\Ui\Components\PdfViewer;

PdfViewer::make('report')
    ->url('/files/report.pdf')
    ->label('Annual report 2026');
```

- **`url()`**: the document, a relative, http or https address. It is fetched by the browser: on the same origin, or served with CORS headers. Your own access checks apply as for any request.
- **`label()`**: the document's title. It names the viewer.
- **`page(3)`**: the page shown first. **`zoom()`**: `'fit'` (the width of the viewer, the default) or `50`, `75`, `100`, `125`, `150`, `200`, `300` percent.
- **`size()`**: the height of the viewer, `sm` (24rem), `md` (40rem, the default) or `lg` (60rem), never more than 85% of the screen.
- **`download(false)`** hides the download link of the header. The error and no-JavaScript messages keep theirs.
- **`labels()`** translates every text (`toolbar`, `previous`, `next`, `page`, `of`, `zoomOut`, `zoomIn`, `zoom`, `fit`, `pages`, `pageName`, `download`, `loading`, `error`, `noscript`).

The viewer fires `caelrik:pdf-loaded` (with the number of pages) and `caelrik:pdf-error`. PDF.js and its worker (1.3 MB) are loaded only by pages that have a viewer. Under a Content Security Policy, allow `worker-src 'self' blob:` (and `blob:` alone when the bundle comes from a CDN, since a worker cannot load from another origin).

## Accessibility

- **The text of every page is in the page**: a transparent text layer over each drawn page, which screen readers read, the browser's search (Ctrl+F) finds, and the mouse selects. The drawings are hidden from assistive technology.
- **The download link is always there**, the way to open the document in another reader: a screen reader's own PDF support, or the structure of a tagged PDF, which a viewer in a web page cannot give. It is also what the viewer shows without JavaScript, and when the document cannot be opened.
- **A region named by the title**, with each page a group named "Page 2 of 12". The pages scroll with the keyboard (the area is focusable), and the page number follows them.
- **Previous, Next and the page number** move to a page and announce it ("Page 3 of 12"); **zoom** by steps or by a select, and "Fit width" follows the width of the viewer. Every control has a name and a target of 24 pixels or more.
- **Pages stay white like paper** in dark mode, on a gray background; forced colors draw their borders. No inline styles: the pages are sized by the CSSOM.

A PDF made without accessibility (a scan, text drawn as shapes, no tags) has no text to read in any viewer: ask for an accessible PDF, or give the content as HTML too.
