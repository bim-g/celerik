# Badges, avatars, chips, tooltips, progress

Small elements for showing states: badges, avatars, chips, tooltips and popovers, progress bars and circles, loaders and skeletons.

<Demo name="display" :min-height="560" />

<<< @/demos/display.php

Badges, avatars, progress, loaders and skeletons are plain HTML: they need no JavaScript and can repeat in every row of a grid. Chips, tooltips and popovers add behavior with JavaScript.

## Badge

```php
Badge::make()->text(3)->color('danger')->pill()->hiddenText('unread messages');
```

- Bootstrap's `text-bg-*` colors keep an AA contrast.
- **`hiddenText()`** completes the badge for screen readers: "3" reads "3 unread messages".

## Avatar

```php
Avatar::make()->person('Ada Lovelace')->image('/avatars/ada.jpg')->size('lg')->status('online');
Avatar::make()->person('Acme')->square();
Avatar::make()->person('Ada Lovelace')->decorative();   // next to the written name
```

- **An image named after the person**: "Ada Lovelace, online" (`labels()` translates the statuses). Next to the person's written name, `decorative()` hides it from screen readers.
- **Without a picture**, or with an unsafe URL, it shows the initials ("AL") on a color picked from the name: the same person always gets the same color.
- **The status dot has a shape as well as a color**: online is filled, away is half filled, busy has a bar, offline is a ring. Sizes: `sm`, `md`, `lg`, `xl`.

## Chip

```php
Chip::make()->label('PHP');                                    // a label
Chip::make()->label('PHP')->removable('skills[]', 'php');      // with a Remove button
Chip::make('remote')->label('Remote')->checkbox('remote', '1', checked: true);   // a filter
```

- **Removable**: the "Remove PHP" button (a 24px target) takes the chip and its hidden field away, so the form posts what is left. Focus moves to the next chip's button, and the removal is announced. A cancelable `caelrik:remove` event comes first. Without JavaScript the button is hidden.
- **A filter chip** is a real checkbox, posted with its form with or without JavaScript. Checked shows a ✓, not only a color.
- `avatar(Avatar::make()->person('Ada'))` adds a small picture.

## Tooltip

```php
Tooltip::make()->text('Saves a draft you can finish later')->trigger($saveButton);
Tooltip::make()->text('A secret that identifies your application')->trigger('API key');
```

- **The [WAI-ARIA tooltip](https://www.w3.org/WAI/ARIA/apg/patterns/tooltip/)**: the control is described by the tooltip and keeps its own name. A tooltip adds to a name; it never replaces the name of an icon button.
- **WCAG 1.4.13**: it shows on hover and focus. The pointer can move onto it, Escape hides it without moving focus, and it stays until the pointer or focus leaves.
- **Plain text** as the trigger becomes focusable, so keyboard users reach its tooltip too.
- **Placement**: `top` (the default) or `bottom`. It flips when there is no room, and slides to stay on screen.
- It needs JavaScript; without it, nothing is shown.

## Popover

```php
Popover::make('pricing-help')
    ->button('?', 'About pricing')      // its text, and its name when the text is a symbol
    ->title('Pricing')
    ->content(Html::raw('<p>Prices include taxes. <a href="/pricing">See all prices</a>.</p>'))
    ->align('end');                     // under the button's end; start by default
```

- **A native disclosure** (`<details>`): it opens and closes without JavaScript.
- With JavaScript, Escape closes it and returns focus to its button, and it closes when focus or a click goes elsewhere.
- The panel follows its button in the reading and tab order, so its links are reached with Tab.

## Progress

```php
Progress::make()->label('Uploading report.pdf')->value(40)->showValue();
Progress::make()->label('Syncing', visible: false);       // no value: indeterminate
Progress::make()->label('Profile complete')->value(3)->max(4)->circle()->showValue();
```

- **The bar is a native `<progress>`**: screen readers announce its value, and it needs no inline style. The circle is an SVG `progressbar` with `aria-valuenow` and `aria-valuetext`.
- **Without a value it is indeterminate**: the bar's segment slides and the circle turns. Both stop for reduced motion.
- **Colors** keep 3:1 against the track (info and warning use darker shades). Sizes: `sm`, `md`, `lg`.
- **To move it** from your script, set the bar's `value`, or the circle's `aria-valuenow`, `aria-valuetext` and its arc's `stroke-dasharray` ("40 100" for 40%).

## Loader and skeleton

```php
Loader::make()->label('Loading orders')->showLabel()->size('sm');
Skeleton::make()->label('Loading profile')->avatar()->heading()->lines(3);
```

- **Both are status regions** that say what is loading. The spinner and the skeleton's shapes are hidden from screen readers.
- **Reduced motion**: the skeleton stops glowing and the spinner slows down. `Skeleton::still()` never glows.
