# List view

A list of items with a pager: articles, people, products, notifications. Each item shows fields of its row: a title (a link if you want), a description, short meta texts, a picture and actions. The user can page, search and sort, client-side or server-side.

<Demo name="listview" :min-height="620" />

<<< @/demos/listview.php

## Client-side or server-side

- **Client-side**: pass the rows with `rows()`. PHP writes every item, and the browser pages, searches and sorts them. Without JavaScript, every item is shown.
- **Server-side**: pass an endpoint with `url()`, and answer it with `respond()` using the same list (see [Data endpoints](../guide/data)). The browser asks for one page at a time: `?page=2&per_page=10&search=ada&sort=-published`.

```php
$list = ListView::make('articles')
    ->label('Articles')
    ->url('/articles/data')
    ->title('title')
    ->link('/articles/{id}')
    ->description('summary')
    ->meta('author', 'published')
    ->searchable()
    ->sorts(['-published' => 'Newest first', 'title' => 'Title']);

// GET /articles/data
echo json_encode($list->respond(new EloquentDataSource(Article::query()), $request->query()));
```

`respond()` sorts only by the list's sorts, searches only its title, description and meta fields, and sends only the fields the list shows and its actions read.

## Options

| Method | Use |
|---|---|
| `label($label)` | The list's accessible name, e.g. 'Articles'. It also names the pager ("Pages of Articles"). |
| `title($field)` | The field that titles each item, as a heading. It also names the item's actions ("Delete Notes on the Engine"). Required. |
| `link('/articles/{id}')` | Makes the title a link; `{field}` takes the row's value, URL-encoded. |
| `description($field)` | A paragraph under the title. |
| `meta($field, ...)` | Short texts under it, e.g. the author and the date. |
| `image($field, $altField = null)` | A picture from a URL field: relative, `http` or `https` only. Without `$altField`, it is decorative. |
| `headingLevel(3)` | The titles' heading level, 2 to 6: one below the heading above the list. |
| `layout('list')`, `layout('cards', 3)` | One item per line, or cards: one column on phones, two on small screens, `$columns` on large ones. |
| `rows(iterable $rows)` | Client-side items. |
| `url($url)` | Server-side mode. |
| `pageSize(10)` | Items per page. |
| `searchable()` | A search box over the titles, descriptions and meta texts. |
| `sorts(['-published' => 'Newest first', 'title' => 'Title'])` | The orders to choose from, the first by default; `-` sorts descending. |
| `actions(Action ...$actions)` | [Actions](./actions) for each item, including ones that open a form. |
| `emptyState($title, $description = null, $action = null)` | What the list shows without items. |
| `labels([...])` | Translates `search`, `sort`, `pages` (with `{label}`), `previous`, `next`, `page` (with `{page}`), `showing` (with `{from}`, `{to}`, `{total}`), `empty`, `noMatches`, `reset`, `loading` and `failed`. |

## Accessibility

- **The list** is a `<ul>` named by `label()`, and each item's title is a heading, so screen reader users can jump from item to item.
- **The pager** is a `<nav>` named "Pages of Articles", with **Previous**, **Next** and page buttons. The current page has `aria-current="page"`; Previous and Next are disabled at the ends. Its buttons are 36px tall.
- **Changing page** moves focus to the list, so reading starts at its first item, and the status says which items are shown ("5–8 of 23").
- **Searching and sorting** start again from page 1. When nothing matches, the list says so, with a **Clear search** button that returns focus to the search box.
- **Pictures** take their text alternative from a field, or are decorative.

## Security

- Titles, descriptions, meta texts and picture alternatives are escaped. Link values are URL-encoded.
- Picture URLs other than relative, `http` or `https` are left out, so a `javascript:` URL cannot run.
- `respond()` sends only the fields the list uses; other columns of your rows never reach the browser.
