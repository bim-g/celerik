# Record view

A read-only layout of a record's fields, by sections: a member's profile, an order's details (Filament's infolists). Each section is a heading and a description list of labels and values.

<Demo name="recordview" :min-height="420" />

<<< @/demos/recordview.php

## Sections

```php
RecordView::make('member')
    ->record($member)                 // an array, an Eloquent model (ArrayAccess) or an object
    ->section('Profile', [
        Entry::text('name', 'Name'),
        Entry::text('company.name', 'Company'),   // dot notation
    ], description: 'How to reach the member.', columns: 2)
    ->section(null, [...])            // no heading
    ->headingLevel(2);                // h2 (the default) to h6
```

- **Each section** with a title is a region named by its heading, then a `<dl>`: screen readers announce each label with its value.
- **Columns**: 1 to 4 per section; small screens show one. `->span(2)` widens an entry.
- **No JavaScript**: the record view is HTML and CSS.

## Entries

| Entry | Shows |
|---|---|
| `Entry::text($field, $label)` | The value as text (escaped). |
| `Entry::email()`, `Entry::phone()` | A `mailto:` or `tel:` link. |
| `Entry::url($field, $label, $text)` | A link to the value, or the value as text when it is not relative, `http` or `https`. |
| `Entry::date($field, $label, 'j F Y')`, `Entry::dateTime()` | A `<time>` from a `DateTimeInterface` or an ISO 8601 string, in the given format. Another value is shown as text. |
| `Entry::number($field, $label, decimals, prefix, suffix, decimalSeparator, thousandsSeparator)` | A formatted number: `$1,234.50`, `1 234,50 €`. |
| `Entry::boolean()` | Yes or No, with a mark. |
| `Entry::badge($field, $label, ['active' => 'success'])` | The value in a badge, colored by value (Bootstrap colors; others are secondary). |
| `Entry::list($field, $label, badges: true)` | An array as a list, or as badges. |
| `Entry::image($field, $label, $alt)` | A picture (relative, `http` or `https`); without `$alt`, it is decorative. |

Every entry takes:

- **`format(fn ($value, $record) => ...)`**: what to show instead. A string is escaped text; `Html::raw()` or a component is HTML.
- **`placeholder('None yet')`**: shown when the value is empty (`null`, `''` or `[]`). By default, a dash that screen readers read as "Not set" (`labels(['empty' => ...])`).
- **`help('...')`**: a short text under the value.

`labels(['empty' => ..., 'yes' => ..., 'no' => ...])` translates the record view.
