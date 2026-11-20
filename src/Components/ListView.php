<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Data\DataRequest;
use Celerik\Ui\Data\DataResult;
use Celerik\Ui\Data\DataSourceInterface;
use Celerik\Ui\Support\Arr;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A list of items, one per row, with a pager: articles, people, products, notifications.
 *
 * Each item shows row fields: a title (a link with link()), a description, meta texts, an
 * image and actions. Pass the rows with rows() (the browser pages, searches and sorts them;
 * without JavaScript, every item is shown), or an endpoint with url(), answered with
 * respond() and any DataSourceInterface.
 *
 *     ListView::make('articles')->label('Articles')
 *         ->title('title')->link('/articles/{id}')->description('summary')->meta('author', 'published')
 *         ->sorts(['-published' => 'Newest first', 'title' => 'Title'])->searchable()
 *         ->rows($articles);
 */
final class ListView extends Component
{
    public const LAYOUTS = ['list', 'cards'];

    /** Texts, with their English defaults. */
    public const LABELS = [
        'search' => 'Search',
        'sort' => 'Sort by',
        'pages' => 'Pages of {label}',
        'previous' => 'Previous',
        'next' => 'Next',
        'page' => 'Page {page}',
        'showing' => '{from}–{to} of {total}',
        'empty' => 'No items to show',
        'noMatches' => 'No items match your search',
        'reset' => 'Clear search',
        'loading' => 'Loading…',
        'failed' => 'The items could not be loaded. Try again.',
    ];

    private ?string $label = null;
    private ?string $titleField = null;
    private ?string $descriptionField = null;

    /** @var list<string> */
    private array $metaFields = [];
    private ?string $linkTemplate = null;
    private ?string $imageField = null;
    private ?string $imageAltField = null;
    private int $headingLevel = 3;
    private string $layout = 'list';
    private int $cardColumns = 3;
    private ?string $url = null;
    private int $pageSize = 10;
    private bool $searchable = false;

    /** @var array<string, string> sort ('field' or '-field') => label */
    private array $sorts = [];

    /** @var list<array<string, mixed>> */
    private array $rows = [];

    /** @var list<Action> */
    private array $actions = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    private ?string $emptyTitle = null;
    private ?string $emptyDescription = null;
    private ?Action $emptyAction = null;

    public static function name(): string
    {
        return 'listview';
    }

    /**
     * The list's accessible name, e.g. 'Articles'; it also names the pager.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The field each item is titled by: a heading (see headingLevel()), and what names the
     * item's actions ("Edit Ada Lovelace").
     */
    public function title(string $field): self
    {
        $this->titleField = self::field($field);

        return $this;
    }

    public function description(?string $field): self
    {
        $this->descriptionField = $field === null ? null : self::field($field);

        return $this;
    }

    /**
     * Short texts under the title, e.g. the author and the date.
     */
    public function meta(string ...$fields): self
    {
        $this->metaFields = array_map(self::field(...), array_values($fields));

        return $this;
    }

    /**
     * Makes the title a link: `{field}` takes the row's value, URL-encoded.
     */
    public function link(?string $template): self
    {
        $this->linkTemplate = $template;

        return $this;
    }

    /**
     * A picture from a field holding its URL (relative, http or https; others are left out).
     * Its text alternative comes from `$altField`; without one, the picture is decorative.
     */
    public function image(?string $field, ?string $altField = null): self
    {
        $this->imageField = $field === null ? null : self::field($field);
        $this->imageAltField = $altField === null ? null : self::field($altField);

        return $this;
    }

    /**
     * The level of the items' title headings, 2 to 6: one below the heading above the list.
     */
    public function headingLevel(int $level): self
    {
        if ($level < 2 || $level > 6) {
            throw new InvalidArgumentException('A list item heading is h2 to h6.');
        }
        $this->headingLevel = $level;

        return $this;
    }

    /**
     * 'list' (one item per line) or 'cards' (a grid of cards, `$columns` wide on large screens).
     */
    public function layout(string $layout, int $columns = 3): self
    {
        if (!in_array($layout, self::LAYOUTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown list layout "%s". Available: %s.',
                $layout,
                implode(', ', self::LAYOUTS),
            ));
        }
        if ($columns < 1 || $columns > 6) {
            throw new InvalidArgumentException('Cards are 1 to 6 columns wide.');
        }
        $this->layout = $layout;
        $this->cardColumns = $columns;

        return $this;
    }

    /**
     * @param iterable<array<string, mixed>> $rows
     */
    public function rows(iterable $rows): self
    {
        $this->rows = [];
        foreach ($rows as $row) {
            $this->rows[] = $row;
        }

        return $this;
    }

    /**
     * Server-side mode: the browser asks this endpoint for each page (see respond()).
     */
    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function pageSize(int $pageSize): self
    {
        if ($pageSize < 1 || $pageSize > DataRequest::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                sprintf('Page size must be between 1 and %d.', DataRequest::MAX_PER_PAGE),
            );
        }
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * A search box over the titles, descriptions and meta texts.
     */
    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * The orders the user can choose, the first by default: ['-published' => 'Newest first',
     * 'title' => 'Title']. A leading `-` sorts descending.
     *
     * @param array<string, string> $sorts
     */
    public function sorts(array $sorts): self
    {
        foreach (array_keys($sorts) as $sort) {
            self::field(ltrim((string) $sort, '-'));
        }
        $this->sorts = array_map('strval', $sorts);

        return $this;
    }

    /**
     * Buttons for each item (Action::link(), Action::request(), with a form or not).
     */
    public function actions(Action ...$actions): self
    {
        array_push($this->actions, ...$actions);

        return $this;
    }

    /**
     * What the list shows when it has no items at all.
     */
    public function emptyState(string $title, ?string $description = null, ?Action $action = null): self
    {
        $this->emptyTitle = $title;
        $this->emptyDescription = $description;
        $this->emptyAction = $action;

        return $this;
    }

    /**
     * @param array<string, string> $labels Keys from self::LABELS.
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown list label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    /**
     * Answers a server-side request (page, per_page, search, sort): only the list's sorts and
     * text fields are allowed, and the rows hold only the fields the list shows.
     *
     * @param array<string, mixed> $query Usually `$_GET`.
     */
    public function respond(DataSourceInterface $source, array $query): DataResult
    {
        $this->assertReady();
        $sortable = array_values(array_unique(array_map(
            static fn (string $sort): string => ltrim($sort, '-'),
            array_keys($this->sorts),
        )));
        $request = DataRequest::fromArray(
            $query,
            sortable: $sortable,
            searchable: $this->searchable ? $this->textFields() : [],
            defaultPerPage: $this->pageSize,
        );
        if (!$this->searchable && $request->search !== null) {
            $request = new DataRequest($request->page, $request->perPage, $request->sort, null, [], [], $request->draw);
        }
        $fields = $this->fields();

        return $source->fetch($request)->map(static function (array $row) use ($fields): array {
            $projected = [];
            foreach ($fields as $field) {
                Arr::set($projected, $field, Arr::get($row, $field));
            }

            return $projected;
        });
    }

    public function toArray(): array
    {
        $this->assertReady();
        $sorts = [];
        foreach ($this->sorts as $sort => $label) {
            $sorts[] = [$sort, $label];
        }

        return array_filter([
            'url' => $this->url,
            'pageSize' => $this->pageSize,
            'layout' => $this->layout === 'cards' ? 'cards' : null,
            'searchable' => $this->searchable ?: null,
            'sorts' => $sorts,
            'item' => array_filter([
                'title' => $this->titleField,
                'description' => $this->descriptionField,
                'meta' => $this->metaFields,
                'link' => $this->linkTemplate,
                'image' => $this->imageField,
                'imageAlt' => $this->imageAltField,
                'heading' => $this->headingLevel,
            ], static fn (mixed $value): bool => $value !== null && $value !== []),
            'actions' => array_map(static fn (Action $action): array => $action->toArray(), $this->actions),
            'labels' => $this->labels,
            'emptyHtml' => $this->renderEmpty(),
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function render(): string
    {
        $this->assertReady();
        $label = $this->label;
        $items = '';
        if ($this->url === null) {
            foreach ($this->rows as $row) {
                $items .= $this->renderItem($row);
            }
        }
        $empty = $this->url === null && $this->rows === [];

        $list = Html::tag('ul', [
            'class' => Html::classes([
                'celerik-listview-items',
                'list-unstyled',
                $this->layout === 'cards' ? $this->cardClasses() : null,
            ]),
            'aria-label' => $label,
            'tabindex' => '-1',
            'hidden' => $empty,
        ], $items);

        $pages = $label === null ? 'Pages' : strtr($this->labels['pages'], ['{label}' => $label]);

        return Html::tag(
            'div',
            $this->rootAttributes(['celerik-listview', 'celerik-listview-' . $this->layout]),
            $this->renderToolbar()
            . $list
            . Html::tag('div', ['class' => 'celerik-listview-empty', 'hidden' => !$empty], $this->renderEmpty())
            . Html::tag('div', ['class' => 'celerik-listview-footer'], Html::tag(
                'p',
                ['class' => 'celerik-listview-status', 'role' => 'status', 'data-celerik-listview-status' => true],
                '',
            ) . Html::tag('nav', ['class' => 'celerik-listview-pager', 'aria-label' => $pages, 'hidden' => true], '')),
        ) . $this->renderActionModals();
    }

    /**
     * One item: the same markup as listview.js writes for rows from the endpoint.
     *
     * @param array<string, mixed> $row
     */
    private function renderItem(array $row): string
    {
        $text = static fn (?string $field): string => $field === null ? '' : Arr::toText(Arr::get($row, $field));
        $title = $text($this->titleField);

        $heading = Html::escape($title);
        if ($this->linkTemplate !== null) {
            $heading = Html::tag('a', ['href' => self::fill($this->linkTemplate, $row)], $heading);
        }
        $body = Html::tag('h' . $this->headingLevel, ['class' => 'celerik-listview-title'], $heading);

        if ($this->descriptionField !== null && $text($this->descriptionField) !== '') {
            $body .= Html::tag(
                'p',
                ['class' => 'celerik-listview-description'],
                Html::escape($text($this->descriptionField)),
            );
        }
        $meta = '';
        foreach ($this->metaFields as $field) {
            if ($text($field) !== '') {
                $meta .= Html::tag('span', [], Html::escape($text($field)));
            }
        }
        if ($meta !== '') {
            $body .= Html::tag('p', ['class' => 'celerik-listview-meta'], $meta);
        }

        $image = '';
        $src = $this->imageField === null ? null : Url::safe($text($this->imageField));
        if ($src !== null) {
            $image = Html::tag('img', [
                'class' => 'celerik-listview-image',
                'src' => $src,
                'alt' => $text($this->imageAltField),
                'loading' => 'lazy',
            ]);
        }

        $actions = '';
        foreach ($this->actions as $action) {
            $name = $action->hasAriaLabel() ? null : trim($action->getLabel() . ' ' . $title);
            $actions .= $action->render($row, small: true, ariaLabel: $name, modal: false);
        }
        if ($actions !== '') {
            $actions = Html::tag('div', ['class' => 'celerik-listview-actions'], $actions);
        }

        $sort = [];
        foreach (array_keys($this->sorts) as $key) {
            $field = ltrim($key, '-');
            $value = Arr::get($row, $field);
            $sort[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }

        $item = Html::tag(
            'div',
            ['class' => 'celerik-listview-item'],
            $image . Html::tag('div', ['class' => 'celerik-listview-body'], $body) . $actions,
        );

        return Html::tag('li', [
            'class' => $this->layout === 'cards' ? 'col' : null,
            'data-sort' => $sort === [] ? null : json_encode($sort, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ], $item);
    }

    /**
     * The search box and the sort choice, shown once the list's JavaScript runs.
     */
    private function renderToolbar(): string
    {
        $controls = '';
        if ($this->searchable) {
            $id = $this->id . '-search';
            $controls .= Html::tag('div', ['class' => 'celerik-listview-search'], Html::tag(
                'label',
                ['class' => 'form-label', 'for' => $id],
                Html::escape($this->labels['search']),
            ) . Html::tag('input', [
                'type' => 'search',
                'id' => $id,
                'class' => 'form-control',
                'autocomplete' => 'off',
                'data-celerik-listview-search' => true,
            ]));
        }
        if ($this->sorts !== []) {
            $id = $this->id . '-sort';
            $options = '';
            foreach ($this->sorts as $sort => $label) {
                $options .= Html::tag('option', ['value' => $sort], Html::escape($label));
            }
            $controls .= Html::tag('div', ['class' => 'celerik-listview-sort'], Html::tag(
                'label',
                ['class' => 'form-label', 'for' => $id],
                Html::escape($this->labels['sort']),
            ) . Html::tag('select', [
                'id' => $id,
                'class' => 'form-select',
                'data-celerik-listview-sort' => true,
            ], $options));
        }

        return $controls === '' ? '' : Html::tag('div', [
            'class' => 'celerik-listview-toolbar d-flex flex-wrap align-items-end gap-3 mb-3',
            'hidden' => true,
        ], $controls);
    }

    private function renderEmpty(): string
    {
        $html = Html::tag(
            'p',
            ['class' => 'celerik-listview-empty-title'],
            Html::escape($this->emptyTitle ?? $this->labels['empty']),
        );
        if ($this->emptyDescription !== null) {
            $description = Html::escape($this->emptyDescription);
            $html .= Html::tag('p', ['class' => 'celerik-listview-empty-description'], $description);
        }
        if ($this->emptyAction !== null) {
            $action = $this->emptyAction->render(small: true);
            $html .= Html::tag('div', ['class' => 'celerik-listview-empty-action'], $action);
        }

        return $html;
    }

    /**
     * Cards: one column on phones, two on small screens, `cardColumns` on large ones.
     */
    private function cardClasses(): string
    {
        $small = min(2, $this->cardColumns);

        return 'row row-cols-1 row-cols-sm-' . $small . ' row-cols-lg-' . $this->cardColumns . ' g-3';
    }

    private function renderActionModals(): string
    {
        $html = '';
        foreach ($this->actions as $action) {
            $html .= $action->renderModal();
        }

        return $html;
    }

    /**
     * The fields the list shows and its actions read: all respond() sends.
     *
     * @return list<string>
     */
    private function fields(): array
    {
        $fields = [
            $this->titleField,
            $this->descriptionField,
            ...$this->metaFields,
            $this->imageField,
            $this->imageAltField,
            ...array_map(static fn (string $sort): string => ltrim($sort, '-'), array_keys($this->sorts)),
            ...self::placeholders((string) $this->linkTemplate),
        ];
        foreach ($this->actions as $action) {
            // Its URL, texts, and the fields of its form, which open with the item's values.
            array_push($fields, ...$action->fields(), ...($action->toArray()['form']['fields'] ?? []));
        }

        return array_values(array_unique(array_filter($fields, static fn (?string $field): bool => $field !== null)));
    }

    /**
     * @return list<string>
     */
    private function textFields(): array
    {
        return array_values(array_filter(
            [$this->titleField, $this->descriptionField, ...$this->metaFields],
            static fn (?string $field): bool => $field !== null,
        ));
    }

    private function assertReady(): void
    {
        if ($this->titleField === null) {
            throw new LogicException(sprintf(
                'List "%s" has no title(): name the field that titles its items.',
                $this->id,
            ));
        }
    }

    private static function field(string $field): string
    {
        if (!DataRequest::isValidField($field)) {
            throw new InvalidArgumentException(sprintf('Invalid field "%s".', $field));
        }

        return $field;
    }

    /**
     * @return list<string>
     */
    private static function placeholders(string $template): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\}/', $template, $matches);

        return $matches[1];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fill(string $template, array $row): string
    {
        return (string) preg_replace_callback(
            '/\{([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\}/',
            static fn (array $match): string => rawurlencode(Arr::toText(Arr::get($row, $match[1]))),
            $template,
        );
    }
}
