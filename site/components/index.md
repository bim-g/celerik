# Components

Every component is a PHP class with a fluent API. It renders accessible Bootstrap 5.3 markup, and the JavaScript bundle enhances it on the client. The demos below are the real components, rendered by PHP when this site is built; the site's dark mode applies to them too.

| Component | PHP class | Library |
|---|---|---|
| [Grid](./grid) | `Celerik\Ui\Components\Grid` | DataTables 3 |
| [Chart](./chart) | `Celerik\Ui\Components\Chart` | Chart.js 4 |
| [Form and fields](./form) | `Celerik\Ui\Components\Form`, `Form\Field` | Parsley.js, Inputmask |
| [Searchable selects and tags](./form#searchable-and-multiple-selects) | `Field::select()->searchable()`, `Field::tags()` | Tom Select 2 |
| [Form layout and wizard](./form#layout) | `Form\Section`, `Form::columns()`, `Form::wizard()` | – |
| [Repeater](./form#repeater) | `Form\Repeater` | – |
| [Rich text editor](./editor) | `Field::editor()` | Quill 2 |
| [File upload](./upload) | `Field::file()` | FilePond 4 |
| [Scheduler](./scheduler) | `Celerik\Ui\Components\Scheduler` | FullCalendar 6 |
| [Modal](./modal) | `Celerik\Ui\Components\Modal` | Bootstrap 5 |
| [Tabs](./tabs) | `Celerik\Ui\Components\Tabs` | Bootstrap 5 |
| [Notification](./notification) | `Celerik\Ui\Components\Notification` | Notyf 3 |
| [File manager](./file-manager) | `Celerik\Ui\Components\FileManager` | elFinder 2.1 |
| [Theme builder](./theme-builder) | `Celerik\Ui\Components\ThemeBuilder` | – |

## Grid

<Demo name="grid" :min-height="420" />

## Chart

<Demo name="chart" :min-height="360" />

## Form

<Demo name="form" :min-height="560" />

The [Form page](./form) has a demo for each kind of field:

| Fields | Section |
|---|---|
| Text, e-mail, password, phone (with a mask), number, textarea, hidden | [Fields](./form#fields) |
| Date, time and date-time, date and time ranges | [Dates, times and ranges](./form#dates-times-and-ranges) |
| Searchable, multiple and remote selects | [Searchable and multiple selects](./form#searchable-and-multiple-selects) |
| Tags (free text and suggestions) | [Tags](./form#tags) |
| One-time code (OTP) | [One-time codes](./form#one-time-codes) |
| Key-value list | [Key-value lists](./form#key-value-lists) |
| Radio group, checkbox group, switch, range, color, star rating | [Choices, switches, sliders, colors and ratings](./form#choices-switches-sliders-colors-and-ratings) |
| Rich text, files | [Rich text editor](./editor), [File upload](./upload) |
| Sections, columns, a multi-step wizard, repeatable rows | [Layout](./form#layout), [Wizard](./form#wizard), [Repeater](./form#repeater) |

## Tabs

<Demo name="tabs" />

## Modal

<Demo name="modal" :min-height="320" />

## Notification

<Demo name="notification" :min-height="220" />

## Scheduler

<Demo name="scheduler" :min-height="640" />
