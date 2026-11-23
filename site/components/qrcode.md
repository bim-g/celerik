# QR code and barcode

QR codes and barcodes drawn by PHP as SVG, with their value written under them as text.

<Demo name="qrcode" :min-height="460" />

<<< @/demos/qrcode.php

## QR code

```php
use Caelrik\Ui\Components\QrCode;

QrCode::make('site')->value('https://example.com')->label('Our website');
```

- **`value()`**: the text the code holds: a web address, a phone number (`tel:`), a Wi-Fi setup (`WIFI:T:WPA;S:…;P:…;;`), any text in UTF-8.
- **`label()`**: what the code is for, written above the value. It names the image.
- **`level()`**: the error correction. `L` (7% of the code can be damaged), `M` (15%, the default), `Q` (25%) or `H` (30%). A higher level makes a denser code that still scans when it is worn, printed small or partly covered.
- **`size()`**: the width and height in pixels (160 by default). The code shrinks to a narrower container.
- **`hideValue()`**: keeps the value out of the caption (a password, a long token). The code then needs a label.
- **`labels()`** translates the name of the image (`image`: `QR code: {label}`).

The text is encoded in the smallest version that holds it (1 to 40: up to 2,331 bytes at level M), as digits, letters or bytes, whichever is shortest, with the mask of the lowest penalty. `Caelrik\Ui\Support\QrMatrix::encode()` returns the modules, for another drawing (a PDF, an image).

## Barcode

```php
use Caelrik\Ui\Components\Barcode;

Barcode::make('order')->value('ORDER-2026-000042')->label('Order number');
Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN');
```

- **`type()`**: `code128` (the default) holds any ASCII text, and packs runs of digits two by two. `ean13` holds 12 digits and adds the check digit, or 13 digits and checks it: products and books (ISBN).
- **`label()`**, **`hideValue()`** and **`labels()`** (`image`: `Barcode: {label}`), as for QR codes.
- **`height()`**: the height of the bars in pixels (80 by default). **`scale()`**: the width of the narrowest bar, 1 to 4 pixels (2 by default).

## Accessibility

- **The value is written as text** under the code, so nobody needs a phone or a scanner to read it, and a web address is a link. Only a hidden value is left out, and the label then says what the code is for.
- **The code is an image** named by its label ("QR code: Our website"), or by its value without a label.
- **Dark on a light tile, always**: scanners need that contrast, so the code keeps its colors in dark mode and in forced colors (high contrast modes).
- **No JavaScript, no inline styles, no dependency**: an SVG sized by attributes, which works under a strict Content Security Policy. Every version and level is checked in the tests by an independent reader (ZXing), on screen in light mode, dark mode and forced colors too.
