# Tree view

A tree of nodes: folders, categories, an outline, a site's pages. Nodes can be links, or be selected or checked as form fields. Large trees can load a node's children when it opens.

<Demo name="treeview" :min-height="380" />

<<< @/demos/treeview.php

## Nodes

```php
TreeView::make('site')->label('Site')->nodes([
    ['label' => 'Guide', 'href' => '/guide', 'expanded' => true, 'children' => [
        ['label' => 'Install', 'href' => '/guide/install'],
        ['label' => 'Data endpoints', 'href' => '/guide/data'],
    ]],
    ['label' => 'Components', 'href' => '/components'],
]);
```

| Key | Use |
|---|---|
| `label` | The node's text. Required. |
| `id` | Its value in a selectable or checkable tree, and in a children endpoint's URL. |
| `href` | Makes the node a link: relative, `http` or `https` only. |
| `children` | Its child nodes. |
| `expanded` | Open at first. `expandAll()` opens every node. |
| `checked` (or `selected`) | Checked, or selected, at first. |
| `disabled` | Cannot be selected or checked. |
| `hasChildren` | Its children come from `url()` when it first opens. |

From flat rows with a parent field, e.g. categories from a database:

```php
TreeView::make('categories')->label('Categories')
    ->fromRows($categories, id: 'id', parent: 'parent_id', label: 'name', href: '/categories/{id}');
```

A row whose parent is not among the rows is a root. Rows in a cycle (a row that is its own ancestor) have no root and are left out.

## Selecting and checking

- `selectable('folder')`: one node can be selected, a radio button named `folder` that posts its id.
- `checkable('folders')`: nodes can be checked, checkboxes named `folders[]` that post the checked ids. Checking a parent checks its children; a parent with some of its children checked is mixed (`aria-checked="mixed"`), and does not post its own id.

The tree fires `caelrik:change` with `{value}`: the selected id, or the list of checked ids.

## Children loaded on demand

```php
$tree = TreeView::make('folders')->label('Folders')->url('/folders/{id}/children')
    ->nodes([['id' => 6, 'label' => 'Pictures', 'hasChildren' => true]]);

// GET /folders/{id}/children
echo json_encode(TreeView::children($folder->children()));   // {"data": [nodes]}
```

`TreeView::children()` checks the nodes as `nodes()` does. A node whose endpoint answers no children no longer opens. A failed load is announced, and opening the node again tries again.

## Options

| Method | Use |
|---|---|
| `label($label)` | The tree's accessible name. |
| `nodes(iterable $nodes)`, `fromRows(...)` | The nodes. |
| `selectable($name)`, `checkable($name)` | Radio buttons or checkboxes, posted with the form. |
| `url('/folders/{id}/children')` | Where `hasChildren` nodes load their children. |
| `expandAll()` | Every node starts open. |
| `labels(['loading' => ..., 'failed' => ...])` | Translates the announcements of a load. |

## Keyboard

| Key | Does |
|---|---|
| Tab | Enters and leaves the tree: it is one tab stop, on the selected or first checked node, or the first node. |
| Down, Up | The next or previous visible node. |
| Right | Opens a node; on an open node, goes to its first child. |
| Left | Closes a node; on a closed node, goes to its parent. |
| Home, End | The first or last visible node. |
| A letter | The next node starting with it. |
| `*` | Opens the node's siblings. |
| Enter | Follows the node's link, or selects or checks it, or opens or closes it. |
| Space | Selects or checks the node. |

## Accessibility

- **The tree** follows the [WAI-ARIA tree pattern](https://www.w3.org/WAI/ARIA/apg/patterns/treeview/): each node's row is a `treeitem`, named by its own text, with its level, position, and open, selected or checked state. It owns its children's `group` (`aria-owns`).
- **Without JavaScript**, the tree is nested lists: links work, and radio buttons and checkboxes post with the form.
- **The focused node** shows an outline, also in Windows High Contrast; the selected node is drawn in the system's Highlight colors there.

## Security

- Labels are text: they are escaped, and loaded nodes are built with `textContent`.
- Links other than relative, `http` or `https` are dropped, on the server and in the browser.
