# Carousel

Slides shown one at a time: pictures, news, tips.

<Demo name="carousel" :min-height="760" />

<<< @/demos/carousel.php

## Usage

```php
use Celerik\Ui\Components\Carousel;

Carousel::make('news')
    ->label('News')
    ->image('/img/lake.jpg', 'Lake Kivu at dawn', caption: 'Our new office')   // a picture
    ->slide(Html::raw('<h3>Spring sale</h3><p>Up to 40% off.</p>'), label: 'Spring sale')
    ->autoplay(6);                                                            // optional
```

- **`slide($content, $label)`**: text (escaped) or HTML. The label names the slide and its button; "2 of 5" and "Slide 2" otherwise.
- **`image($src, $alt, $caption, $label)`**: a picture cropped to the carousel's **`ratio()`** (`16x9` by default, `4x3`, `3x2`, `21x9`, `1x1`). The alternative text describes the picture. Pictures after the first load lazily.
- **`autoplay($seconds)`**: shows the next slide every 2 to 60 seconds.
- **`loop(false)`**: Previous and Next stop at the first and last slides, and rotation stops at the last one.
- **`picker(false)`**: no button per slide.
- **`labels()`** translates the buttons and slide names (`previous`, `next`, `start`, `stop`, `slides`, `slide`, `show`; `{index}` and `{count}` are replaced).

From JavaScript, the `celerik:change` event gives the slide's `index` (from 0).

## Accessibility

It follows the [WAI-ARIA carousel pattern](https://www.w3.org/WAI/ARIA/apg/patterns/carousel/):

- **A named region** ("News, carousel") whose slides are groups named "1 of 3", or by their label.
- **Previous, Next and a button per slide**. The current slide's button has `aria-current` and is filled; the others are rings. Each button is a 24px target or larger.
- **The new slide is read** when the user changes it: the slides are a polite live region. While they rotate, it is silent.
- **Automatic rotation (WCAG 2.2.2)**: the Stop button comes first, before the slides. Rotation pauses while the pointer or focus is in the carousel, and stops for good once the user picks a slide. It does not start for users who prefer reduced motion; they can start it.
- **Touch screens** swipe from slide to slide.
- **The carousel keeps the height of its tallest slide**, so the page does not jump.
- **Without JavaScript** the slides are shown one under the other, and the buttons are hidden. No inline styles: a strict Content Security Policy works.
