# Admin layout

The frame of an admin screen: a skip link, an app bar with the user's menu, a sidebar with the navigation (the Telerik Drawer), and the main content with its breadcrumb and title. Four components that work together, or each on its own.

<Demo name="admin-layout" :min-height="520" />

<<< @/demos/admin-layout.php

## AdminLayout

```php
echo AdminLayout::make('app')
    ->brand('Acme', '/')
    ->navigation($navigation)
    ->userMenu($userMenu)
    ->breadcrumb($breadcrumb)
    ->title('Members')
    ->content($grid);   // a component, or Html::raw($html); a string is escaped text
```

- **On large screens** (992px and up), the sidebar stays beside the content, and scrolls on its own.
- **On small screens**, the app bar's menu button opens the sidebar as a panel: Bootstrap's offcanvas, a dialog that traps focus, closes with Escape and returns focus to the button. It needs `bootstrap.bundle.js`, as the [Modal](./modal) does.
- **Without JavaScript**, the sidebar is shown above the content on small screens.
- **A skip link** is the page's first tab stop; it moves focus to the main content.

| Method | Use |
|---|---|
| `brand($name, $url = null)` | The application's name, a link home. |
| `navigation(Navigation)`, `userMenu(UserMenu)`, `breadcrumb(Breadcrumb)` | The parts below. |
| `title($title)` | The page's `h1`, under the breadcrumb. |
| `appBar(...$items)` | More in the app bar, before the user menu: components or `Html::raw()`. |
| `content($content)` | The page: components or `Html::raw()`. |
| `labels([...])` | Translates `skip`, `openNavigation`, `navigation` and `close`. |

## Navigation

```php
$navigation = Navigation::make('main')->label('Main')
    ->item('Dashboard', '/')
    ->group('Members', static fn (Navigation $group) => $group
        ->item('All members', '/members')
        ->item('Invitations', '/members/invitations', badge: '3'))
    ->item('Settings', '/settings')
    ->current($_SERVER['REQUEST_URI']);
```

- **The current page**: the link to it has `aria-current="page"`. When no link is the page itself, e.g. `/members/42`, the closest section (`/members`) has `aria-current="true"`. Queries are ignored.
- **Groups** are native disclosures (`<details>`): they open and close with the keyboard and without JavaScript. A group holding the current link starts open.
- **Name each navigation** with `label()` when a page has more than one.
- **`horizontal()`** makes it a bar across the page, a site's menu: its groups drop down below their names, closed at first, and close with Escape, when another opens, or when focus or a click leaves.

## Breadcrumb

```php
$breadcrumb = Breadcrumb::make()->item('Home', '/')->item('Members', '/members')->item('Ada Lovelace');
```

A navigation landmark named "Breadcrumb" (`label()` translates it) with an ordered list. The last item is the current page: `aria-current="page"`, and not a link.

## UserMenu

```php
$userMenu = UserMenu::make('account')
    ->user('Ada Lovelace', 'ada@example.com', '/avatars/ada.jpg')
    ->link('Profile', '/profile')
    ->divider()
    ->action(Action::request('logout', 'Sign out', '/logout')->csrf($token));
```

- **A disclosure** (`<details>`), not an ARIA menu: it opens without JavaScript, and its links are read and reached as links. With JavaScript, Escape closes it and returns focus to its button, and it closes when focus or a click leaves it.
- **The picture**: a relative, `http` or `https` URL, or the user's initials. It is decorative: the user's name is the button's text (hidden on phones, still read).
- **Actions** such as Sign out are [request actions](./actions): a form that posts, with a confirmation if you want one.

## Accessibility

- Landmarks: a `header` (the app bar), the `navigation` and `breadcrumb` landmarks, an `aside` and the `main` content.
- Every part works from the keyboard; focus is visible, also in Windows High Contrast.
- The current page is announced in the navigation and the breadcrumb.
