# Component Mapping

Telerik UI → open-source alternatives.

This document maps the Telerik UI web components, and a few ideas from [Filament](https://filamentphp.com), to open-source libraries or native Bootstrap features.
- ✅ The **core** only includes MIT, Apache 2.0 or BSD libraries, or native Bootstrap 5.3 / HTML features.
- ⚠️ GPL, commercial or proprietary libraries are listed separately as **optional extras**.
- 🧩 marks libraries that need jQuery (a peer dependency the application loads itself).

The **Phase** column refers to the [roadmap](ROADMAP.md); "–" means not yet scheduled.

---

## Core Components

"Native" means Bootstrap 5.3 or plain HTML, CSS and a little JavaScript, with no library. *To evaluate*: the library is a candidate; its maintenance and accessibility are checked before the work starts.

### Data & Lists

| Component | Library | License | Phase |
|---|---|---|---|
| Grid | DataTables 🧩 (+ Buttons) | MIT | 1 ✅ |
| Grid filters, row actions, bulk actions, totals, grouping, inline editing | DataTables 🧩 + native | MIT | 5 ✅ |
| Grid (alternative) | AG Grid Community | MIT | – |
| Pager | Native (Bootstrap pagination, in ListView) | MIT | 5 ✅ |
| ListView | Native + Pager | MIT | 5 ✅ |
| TreeView | Native (WAI-ARIA tree) | MIT | 5 ✅ |
| TreeList | Native (WAI-ARIA treegrid) | MIT | 5 ✅ |
| TaskBoard (Kanban) | SortableJS | MIT | 5 ✅ |
| Filter (query builder) | jQuery QueryBuilder 🧩 (to evaluate) | MIT | – |
| PivotGrid | PivotTable.js 🧩 | MIT | Later |
| Spreadsheet | Univer | Apache 2.0 | Later |

### Selection & Inputs

| Component | Library | License | Phase |
|---|---|---|---|
| TextBox, TextArea, NumericTextBox, Checkbox | HTML5 inputs | – | 1 ✅ (`Field::text`, `textarea`, `number`, `checkbox`) |
| MaskedTextBox | Inputmask | MIT | 1 ✅ (`->mask()`) |
| RadioGroup, CheckBoxGroup | Native | – | 4 ✅ (`Field::radio`, `checkboxes`) |
| Switch | Native (Bootstrap `form-switch`) | MIT | 4 ✅ (`Field::switch`) |
| Slider | Native (`input type=range`) | – | 4 ✅ (`Field::range`) |
| ColorPicker | Native (`input type=color`) | – | 4 ✅ (`Field::color`) |
| DropDownList, ComboBox, MultiSelect, AutoComplete | Tom Select | Apache 2.0 | 4 ✅ (`->searchable()`, `->multiple()`, `->remote()`) |
| DatePicker, TimePicker, DateTimePicker, DateRangePicker | Native (`input type=date`, `time`, `datetime-local`): keyboard, screen reader and mobile pickers from the browser | – | 4 ✅ (`Field::date`, `time`, `datetime`, `dateRange`) |
| Tags input (Telerik ChipList) | Tom Select (create mode) | Apache 2.0 | 4 ✅ (`Field::tags`) |
| Rating | Native (radio buttons) | – | 4 ✅ (`Field::rating`) |
| OTP input | Native | – | 4 ✅ (`Field::otp`) |
| Key-value list, repeater (Filament) | Native | – | 4 ✅ (`Field::keyValue`, `Repeater::make()`) |
| RangeSlider | noUiSlider | MIT | – |
| ListBox (two lists, move items between them) | Native + SortableJS | MIT | – |
| DropDownTree | To evaluate | – | – |
| Signature | signature_pad | MIT | 8 |

### Forms

| Component | Library | License | Phase |
|---|---|---|---|
| Form, validation | Parsley.js 🧩 | MIT | 1 ✅ |
| Form layout: sections, fieldsets, columns | Native | – | 4 ✅ (`Section`, `columns()`) |
| Wizard, Stepper | Native | – | 4 ✅ (`Form::wizard()`; standalone Stepper later) |
| Upload | FilePond (+ validate-type, validate-size, image-preview plugins) | MIT | 2 ✅ (`Field::file`) |
| Rich Text Editor | Quill 2 | BSD-3 | 2 ✅ (`Field::editor`) |
| Markdown Editor | EasyMDE | MIT | 8 |
| Code editor | CodeMirror 6 | MIT | 8 |
| ImageEditor | Filerobot Image Editor (to evaluate) | MIT | 8 |
| File Manager | elFinder 2.1 (`studio-42/elfinder` on Composer) 🧩, with jQuery UI | BSD-3 / MIT | 2 ✅ (`FileManager`) |

### Scheduling

| Component | Library | License | Phase |
|---|---|---|---|
| Scheduler | FullCalendar (standard plugins) | MIT | 2 ✅ |
| Calendar | FullCalendar (`Scheduler` month view) | MIT | 2 ✅ |
| Gantt | Frappe Gantt | MIT | 8 |

> The npm package named `elfinder` is **not** elFinder (it is an unrelated "element finder" tool): elFinder is installed with Composer (`studio-42/elfinder`).

> FullCalendar's resource and timeline views are **premium** (commercial). Only the MIT standard plugins are bundled.

### Navigation & Layout

| Component | Library | License | Phase |
|---|---|---|---|
| TabStrip | Bootstrap Tabs | MIT | 2 ✅ (`Tabs`) |
| AppBar, Drawer (sidebar), Breadcrumb, user menu: an admin layout | Native (Bootstrap navbar, offcanvas, breadcrumb) | MIT | 6 ✅ (`AdminLayout`, `Navigation`, `Breadcrumb`, `UserMenu`) |
| Menu | Native (`Navigation::horizontal()`, disclosure navigation) | MIT | 6 ✅ |
| ContextMenu | Native (WAI-ARIA menu) | MIT | 6 ✅ (`ContextMenu`) |
| DropDownButton, SplitButton, ButtonGroup | Native (WAI-ARIA menu button) | MIT | 6 ✅ (`MenuButton`, `SplitButton`) |
| Toolbar | Native (WAI-ARIA toolbar) | MIT | 6 ✅ (`Toolbar`) |
| PanelBar, ExpansionPanel | Native (WAI-ARIA accordion, Bootstrap's look) | MIT | 6 ✅ (`Accordion`) |
| Card | Native (Bootstrap card) | MIT | 6 ✅ (`Card`) |
| Splitter | Native (WAI-ARIA window splitter) | MIT | 6 ✅ (`Splitter`) |
| TileLayout (dashboards) | Native (CSS grid; one-click tools, keys and drag) | MIT | 6 ✅ (`TileLayout`) |

### Feedback & Display

| Component | Library | License | Phase |
|---|---|---|---|
| Window / Dialog / Modal | Bootstrap Modal | MIT | 2 ✅ (`Modal`) |
| Notification | Notyf | MIT | 2 ✅ (`Notification`) |
| Actions: confirmation dialog, form in a modal (Filament) | Bootstrap Modal | MIT | 5 |
| Record view (Filament infolists) | Native | – | 7 ✅ (`RecordView`) |
| Stat tile, dashboard widgets | Native (SVG sparkline) + Chart.js in a TileLayout | MIT | 7 ✅ (`Stat`, `Stats`) |
| Badge, Avatar, Chip | Native (Bootstrap) | MIT | 7 ✅ (`Badge`, `Avatar`, `Chip`) |
| Tooltip, Popover | Native (WAI-ARIA tooltip; `<details>` popover) | MIT | 7 ✅ (`Tooltip`, `Popover`) |
| ProgressBar, circular progress | Native (`<progress>`, SVG) | MIT | 7 ✅ (`Progress`) |
| Loader, Skeleton | Native (Bootstrap spinners, placeholders) | MIT | 7 ✅ (`Loader`, `Skeleton`) |
| Timeline | Native | – | 7 ✅ (`Timeline`) |
| ScrollView (carousel) | Native (WAI-ARIA carousel) | – | 7 ✅ (`Carousel`) |
| Chat | Native | – | – |

### Charts & Visualization

| Component | Library | License | Phase |
|---|---|---|---|
| Chart (line, bar, pie, doughnut, radar, scatter…) | Chart.js | MIT | 1 ✅ |
| Sparkline | Native SVG, drawn by PHP | – | 7 ✅ (`Stat::sparkline()`), 8 ✅ (`Sparkline`) |
| Gauges (radial / linear) | Native SVG, drawn by PHP (`role="meter"`) | – | 8 ✅ (`Gauge`) |
| Map | Leaflet | BSD-2 | 8 |
| Diagram | Mermaid | MIT | 8 |
| OrgChart | D3 hierarchy layout | ISC | 8 |
| QRCode | qrcode-generator | MIT | 8 |
| Barcode | JsBarcode | MIT | 8 |
| Advanced charts | Plotly.js / D3.js | MIT / ISC | – |
| StockChart | TradingView Lightweight Charts | Apache 2.0 | – |
| BulletGraph | D3.js custom chart | ISC | – |

### Miscellaneous

| Component | Library | License | Phase |
|---|---|---|---|
| PDF Viewer | PDF.js | Apache 2.0 | 8 |
| Media Player | Plyr | MIT | 8 |
| Drag & Drop, Sortable | SortableJS | MIT | 5 |
| Captcha | hCaptcha / reCAPTCHA / Cloudflare Turnstile (external services) | service terms | – |

---

## Optional Extras (not MIT / Apache / BSD)

These libraries are **not bundled** because of GPL, commercial or proprietary licenses. Applications may install them separately if they accept the terms.

| Component | Library | License |
|---|---|---|
| Gantt | dhtmlxGantt | GPL / commercial |
| Gantt | Bryntum Gantt | Commercial |
| Spreadsheet | Handsontable | Non-commercial license since v7 / commercial |
| OrgChart, Diagram | GoJS | Commercial |
| Rich Text Editor | TinyMCE 7, CKEditor 5 | GPL / commercial |
| PivotGrid | WebDataRocks | Proprietary (free tier) |
| Scheduler resources / timeline | FullCalendar Premium | Commercial |
| Menu | mmenu.js (v8+) | CC BY-NC 4.0 / commercial |

---

## Replaced Libraries

These were considered earlier and replaced because they are archived, unmaintained, tied to Bootstrap 3, or not usable in the core:

| Replaced | By | Reason |
|---|---|---|
| Luckysheet | Univer | Archived; Univer is its successor |
| SimpleMDE | EasyMDE | Unmaintained fork origin |
| Selectize.js | Tom Select | Abandoned; Tom Select is its successor |
| Select2 | Tom Select | Needs jQuery, slow release cadence |
| Pikaday, Bootstrap Timepicker, Flatpickr | Native date and time inputs | Unmaintained (Flatpickr's last release is from 2022); the browsers' pickers are accessible and localized |
| Bootstrap Switch, Switchery | Bootstrap `form-switch` | Bootstrap 3 era / abandoned; native in Bootstrap 5 |
| Spectrum, Pickr | Native `input type=color` | Spectrum is unmaintained and needs jQuery; the native picker needs no library |
| RichFilemanager | elFinder | Abandoned |
| Toastr | Notyf | Needs jQuery, unmaintained |
| Accordion.js, jQuery UI Toolbar | Bootstrap Accordion, `btn-toolbar` | Native in Bootstrap 5 (jQuery UI has no Toolbar widget) |
| jQuery contextMenu | Bootstrap dropdown + Popper | Avoid a jQuery-only dependency |
| mmenu.js, MetisMenu | Bootstrap navbar and dropdowns | mmenu.js has a non-commercial license since v8; Bootstrap covers menus without a library |
| "dayflow" | FullCalendar | No identifiable maintained library under that name |

---

## Licensing Notes

- ✅ MIT, Apache 2.0, BSD and ISC libraries may be bundled in the core.
- ⚠️ GPL, commercial and proprietary libraries are excluded and must stay optional extras.
- Developers are responsible for compliance when enabling extras.
- Bundled libraries and their licenses are listed in [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md).
