# Actions

Buttons that do something: open a page, or send a request to your endpoint, after a confirmation when it matters. Use them on their own, in a toolbar or a page, [in a grid's rows](./grid#row-actions), or [for the rows selected in a grid](./grid#row-selection-and-bulk-actions).

<Demo name="actions" :min-height="520" />

<<< @/demos/actions.php

## Links and requests

```php
use Caelrik\Ui\Components\Action;

// A link: an ordinary <a href>.
echo Action::link('new', 'New member', '/members/create')->variant('primary')->solid();

// A request: a form that posts to your endpoint.
echo Action::request('delete', 'Delete', '/members/7')
    ->method('DELETE')
    ->variant('danger')
    ->confirm('Delete Ada Lovelace?', 'This cannot be undone.')
    ->csrf($csrfToken);
```

- **With JavaScript**, a request action:
  1. asks for confirmation when it has one;
  2. sends the request with `fetch`, with its method and the CSRF token in a header;
  3. shows the `message` of the JSON answer in a toast, or an error toast when the request fails.
- **Without JavaScript**, a request action is a form that posts, with the method in `_method` (Laravel and Symfony read it) and the token in a hidden field. There is no confirmation then.

## Options

| Method | Use |
|---|---|
| `Action::link($name, $label, $url)` | A link to a page. |
| `Action::request($name, $label, $url)` | A request to an endpoint, `POST` by default. |
| `method('PUT' \| 'PATCH' \| 'DELETE')` | The request's method. |
| `variant('primary' \| 'secondary' \| 'success' \| 'danger' \| 'warning' \| 'info')` | The button's color, outlined. `secondary` by default. |
| `solid()` | A filled button instead of an outlined one. |
| `confirm($question, $detail = null)` | Asks first, in a dialog titled by the question. The confirm button repeats the action's label. |
| `ariaLabel('Delete {name}')` | The accessible name, when the label alone is ambiguous. |
| `csrf($token, $field = '_token', $header = 'X-CSRF-TOKEN')` | The CSRF token: a hidden field for the form, a header for `fetch`. |
| `form($form, $title = null, $submitLabel = null, $size = null)` | Opens a form in a modal and sends it (see [Forms in a modal](#forms-in-a-modal)). |
| `labels(['cancel' => ..., 'failed' => ...])` | Translates the dialog's Cancel button and the message of a failed request. |

`{field}` in the URL, the question, the detail and the accessible name is replaced by the row's value in a grid, and is URL-encoded in the URL.

## Forms in a modal

A request action with a `form()` opens it in a modal, and **Save** sends its fields to the action's URL. In a grid's row, the form opens with the row's values:

```php
Grid::make('members')
    ->column('id', 'ID', static fn (Column $c) => $c->hidden())
    ->column('name', 'Name')
    ->column('role', 'Role')
    ->actions(
        Action::request('edit', 'Edit', '/members/{id}')
            ->method('PATCH')
            ->csrf($token)
            ->form(Form::make('member-form')->add(
                Field::text('name', 'Name')->required(),
                Field::select('role', ['engineer' => 'Engineer', 'director' => 'Director'], 'Role'),
            ), 'Edit {name}', 'Save'),
    );
```

- **The values**: fields named like the row's columns take the row's values; other fields keep the form's own. Only the form's fields are written in the page, not the whole row.
- **The title** takes the row's values (`Edit {name}`), and so must name declared columns.
- **The buttons**: the modal has **Cancel** and `$submitLabel` (the action's label by default), in place of the form's own submit button.
- **Checks**: the form's rules (`required()`, `email()`, `min()`...) are checked in the browser first, as in any Caelrik form.
- **The request** is a form-encoded `POST`, with `_method` set to the action's method and the action's CSRF field and header, so PHP reads it in `$_POST`.
- **The answer**:
  - **2xx**: the modal closes, its `message` is shown, and `caelrik:action` fires. A server-side grid reloads its rows; a client-side grid shows the values of `{"data": {...}}`, if the answer has them.
  - **422** with `errors` (`{"name": ["This name is taken."]}`, Laravel's shape): each error is shown on its field, and focus moves to the first. Other errors and messages go in an alert at the top of the form.
- **Once per page**: `render()` writes the modal too; a grid writes it once for all its rows. On a page, `render(modal: false)` and `renderModal()` place them apart.
- **Without JavaScript**, the button is not shown: the form needs it.

## The endpoint

Answer with JSON. A `message` is shown in a toast, a success for a 2xx status and an error otherwise:

```php
// DELETE /members/{id}
// Check authorization and the CSRF token first.
$member->delete();
header('Content-Type: application/json');
echo json_encode(['message' => "{$member->name} was deleted."]);
```

After every request, the form fires `caelrik:action`, with `{name, method, ok, status, data}` in `event.detail`:

```js
document.addEventListener('caelrik:action', (event) => {
  if (event.detail.ok && event.detail.name === 'archive') {
    window.location.reload();
  }
});
```

## Accessibility

- **The confirmation** is a modal `alertdialog`:
  - the question is its name, and the detail is its description;
  - **Cancel** has the focus first, so pressing Enter does not confirm by accident;
  - Escape cancels, and focus returns to the button.
- **Outlined buttons** use Bootstrap's text-emphasis colors, which keep 4.5:1 contrast in light and dark mode. Focus stays visible in Windows High Contrast.
- **A request in progress** marks its button `aria-disabled`, and a second press is ignored.
- **A form in a modal** is a dialog named by its title; the button says it opens one (`aria-haspopup="dialog"`). Focus starts in the first field, Escape closes it, and focus returns to the button. Errors are each field's description (`aria-describedby`), and the alert for the others is announced.

## Security

- The endpoint must check authorization and the CSRF token, as for any form.
- Row values are URL-encoded in URLs, so a value cannot turn a link into a `javascript:` URL. In texts, they are escaped.
