# Menus and toolbar

Buttons that open a menu of links and actions (the Telerik DropDownButton), a main action with a menu beside it (SplitButton), a context menu for an element, and a toolbar to hold them. They follow the WAI-ARIA [menu button](https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/) and [toolbar](https://www.w3.org/WAI/ARIA/apg/patterns/toolbar/) patterns.

<Demo name="menus" :min-height="300" />

<<< @/demos/menus.php

## MenuButton

```php
MenuButton::make('export')->label('Export')
    ->link('CSV', '/members.csv')
    ->link('PDF', '/members.pdf', disabled: true)
    ->divider()
    ->action(Action::request('email', 'E-mail me the list', '/members/email')->csrf($token));
```

| Method | Use |
|---|---|
| `label($label)` | The button's text. |
| `link($label, $url, $disabled = false)` | A link. A disabled one is shown, and cannot be chosen. |
| `action(Action $action)` | An [action](./actions): a link, or a request with its confirmation or its form. |
| `divider()` | A line between groups of items. |
| `variant('secondary', $solid = false)`, `small()` | The button's color and size. |
| `align('start' \| 'end')` | The menu's left edge under the button's, or right edges aligned. |

**From the keyboard**: Enter, Space or Down open the menu on its first item, Up on its last. Down, Up, Home, End and a letter move between items; Enter and Space choose; Escape closes and returns focus to the button, and Tab closes. Choosing an action gives focus back to the button first, so its confirmation returns focus there.

**Without JavaScript**, the button is hidden and the items are a plain list of links and forms.

## SplitButton

```php
SplitButton::make('save')
    ->main(Action::request('save', 'Save', '/posts/1')->variant('primary')->solid())
    ->variant('primary', solid: true)
    ->link('Preview', '/posts/1/preview')
    ->action(Action::request('draft', 'Save as draft', '/posts/1/draft'));
```

The main action is any `Action`; the arrow beside it is a menu button named "More options" (`labels(['more' => ...])`), with the same items and keys as a `MenuButton`.

## Toolbar

```php
Toolbar::make('member-tools')->label('Member')
    ->add(Action::link('edit', 'Edit', '/members/42/edit'))
    ->separator()
    ->add($exportMenu, $saveSplit);
```

A named row of controls (`role="toolbar"`): components, or `Html::raw()` for your own. It is one tab stop: Left, Right, Home and End move between its controls, while a field keeps its own Left and Right, and a menu button its own keys.

## ContextMenu

```php
ContextMenu::make('file-menu')->for('#files [data-id]')->label('File actions')
    ->link('Open {name}', '/files/{id}')
    ->divider()
    ->action(Action::request('delete', 'Delete', '/files/{id}')->method('DELETE')->confirm('Delete {name}?'));
```

- **It opens** on an element matching `for()`: a right click, or **Shift+F10** or the **Menu** key while the element or something in it has focus.
- **`{field}`** in its links, texts, URLs and confirmations takes the element's `data-field` value: `data-id="7"` fills `{id}`. Values are URL-encoded in URLs and escaped in texts.
- **Escape** closes it and returns focus to the element.
- **A context menu is a shortcut.** Offer the same actions another way too, a row's buttons or a `MenuButton`, for users who never right-click and on touch screens. Actions that open a form are not supported here.

## A horizontal navigation

For a site's menu bar, use [Navigation](./admin-layout#navigation) with `horizontal()`: a bar of links whose groups drop down, and close with Escape, when another opens, or when focus or a click leaves. It follows the WAI-ARIA disclosure navigation pattern, which suits a website better than an application menubar.

## Accessibility

- The menu button has `aria-haspopup="menu"` and `aria-expanded`, and controls a `menu` it names. Items are `menuitem`s, and disabled ones `aria-disabled`.
- Outlined buttons keep 4.5:1 contrast in light and dark mode, and focus is visible in Windows High Contrast.
