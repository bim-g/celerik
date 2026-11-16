<?php

declare(strict_types=1);

namespace Celerik\Ui\Components\Form;

use Celerik\Ui\Data\DataRequest;
use Celerik\Ui\Data\DataResult;
use Celerik\Ui\Data\DataSourceInterface;
use Celerik\Ui\Support\Arr;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Upload\UploadRules;
use InvalidArgumentException;
use LogicException;

/**
 * A Bootstrap form control with validation rules.
 *
 * Rules are emitted as native HTML5 attributes (required, minlength, pattern...), which
 * Parsley.js reads on the client and browsers understand without JavaScript.
 */
final class Field
{
    private const INPUT_TYPES = [
        'text', 'email', 'password', 'number', 'tel', 'url', 'date', 'time', 'datetime-local', 'search', 'hidden',
    ];
    public const REQUIRED_MARKER = ' <span class="celerik-required" aria-hidden="true">*</span>';
    private const TOOLBARS = ['basic', 'standard', 'full'];
    private const PERIOD_TYPES = ['date', 'time', 'datetime-local'];

    /** The row index in the template form.js copies for a new key-value row. */
    public const PAIR_PLACEHOLDER = '__pair__';

    private ?UploadRules $uploadRules = null;
    private ?string $uploadUrl = null;

    /** @var string|list<mixed> */
    private string|array $toolbar = 'standard';

    private bool $inline = false;
    private string $periodType = 'date';

    /** @var array{string, string} */
    private array $periodLabels = ['From', 'To'];

    private bool $searchable = false;
    private bool $multiple = false;
    private bool $tags = false;
    private ?int $minTags = null;
    private ?int $maxTags = null;
    private ?string $addText = null;
    private int $stars = 5;
    private string $starLabel = '%d of %d';
    private string $clearLabel = 'No rating';
    private int $codeLength = 6;
    private bool $alphanumeric = false;

    /** @var array{key: string, value: string, add: string, remove: string, missing: string, duplicate: string} */
    private array $pairLabels = [
        'key' => 'Key',
        'value' => 'Value',
        'add' => 'Add row',
        'remove' => 'Remove row %d',
        'missing' => 'Enter a key for this value.',
        'duplicate' => 'This key is already used.',
    ];

    /** @var list<string> */
    private array $keySuggestions = [];

    /** @var array{url: string, value: string, label: string, limit: int}|null */
    private ?array $remote = null;

    private ?string $placeholder = null;
    private ?string $help = null;
    private int|string|null $columnSpan = null;
    private mixed $value = null;
    private bool $required = false;
    private ?string $equalTo = null;
    private ?string $mask = null;
    private ?string $message = null;

    /** @var array<string, string|int|float|bool|null> */
    private array $attributes = [];

    /**
     * @param array<string|int, string> $choices value => label, for select, radio and checkbox group fields
     */
    private function __construct(
        private readonly string $type,
        private string $name,
        private readonly string $label,
        private readonly array $choices = [],
    ) {
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_\-\[\]]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid field name "%s".', $name));
        }
    }

    public static function input(string $type, string $name, ?string $label = null): self
    {
        if (!in_array($type, self::INPUT_TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported input type "%s".', $type));
        }

        return new self($type, $name, $label ?? self::humanize($name));
    }

    public static function text(string $name, ?string $label = null): self
    {
        return self::input('text', $name, $label);
    }

    public static function email(string $name, ?string $label = null): self
    {
        return self::input('email', $name, $label);
    }

    public static function password(string $name, ?string $label = null): self
    {
        return self::input('password', $name, $label);
    }

    public static function number(string $name, ?string $label = null): self
    {
        return self::input('number', $name, $label);
    }

    public static function tel(string $name, ?string $label = null): self
    {
        return self::input('tel', $name, $label);
    }

    /**
     * A date (`YYYY-MM-DD`), with the browser's date picker.
     */
    public static function date(string $name, ?string $label = null): self
    {
        return self::input('date', $name, $label);
    }

    /**
     * A time (`HH:MM`, or `HH:MM:SS` with a step under 60), with the browser's time picker.
     */
    public static function time(string $name, ?string $label = null): self
    {
        return self::input('time', $name, $label);
    }

    /**
     * A local date and time (`YYYY-MM-DDTHH:MM`), with the browser's picker. It has no
     * time zone: convert it on the server with the user's time zone.
     */
    public static function datetime(string $name, ?string $label = null): self
    {
        return self::input('datetime-local', $name, $label);
    }

    /**
     * A start and an end, in a fieldset: two native inputs that submit `name[start]` and
     * `name[end]`. The end must be on or after the start. min(), max() and step() apply to
     * both; value() takes `['start' => …, 'end' => …]`.
     *
     * @param string $type "date", "time" or "datetime-local"
     */
    public static function dateRange(string $name, ?string $label = null, string $type = 'date'): self
    {
        if (!in_array($type, self::PERIOD_TYPES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported range type "%s". Use %s.',
                $type,
                implode(', ', self::PERIOD_TYPES),
            ));
        }

        $field = new self('period', $name, $label ?? self::humanize($name));
        $field->periodType = $type;

        return $field;
    }

    /**
     * Labels of the two inputs of a dateRange() field (default "From" and "To").
     */
    public function labels(string $start, string $end): self
    {
        $this->assertType('period', 'labels');
        $this->periodLabels = [$start, $end];

        return $this;
    }

    public static function hidden(string $name, string|int|float $value): self
    {
        return self::input('hidden', $name, $name)->value($value);
    }

    public static function textarea(string $name, ?string $label = null): self
    {
        return new self('textarea', $name, $label ?? self::humanize($name));
    }

    /**
     * @param array<string|int, string> $choices value => label
     */
    public static function select(string $name, array $choices, ?string $label = null): self
    {
        return new self('select', $name, $label ?? self::humanize($name), $choices);
    }

    /**
     * Free-text tags: the user types a tag and presses Enter or a comma, or picks one of
     * the suggestions (Tom Select). Submits `name[]`; value() takes a list of strings.
     * Without JavaScript, it is a multiple select of the suggestions and current tags.
     *
     * @param list<string> $suggestions Tags offered as the user types.
     */
    public static function tags(string $name, array $suggestions = [], ?string $label = null): self
    {
        foreach ($suggestions as $suggestion) {
            if (!is_string($suggestion) || trim($suggestion) === '') {
                throw new InvalidArgumentException('Tag suggestions must be non-empty strings.');
            }
        }
        $suggestions = array_values(array_unique($suggestions));

        $field = new self('select', $name, $label ?? self::humanize($name), array_combine($suggestions, $suggestions));
        $field->tags = true;
        $field->multiple = true;
        $field->searchable = true;

        return $field;
    }

    /**
     * The fewest tags of a tags() field. It makes the field required: required() alone is
     * minTags(1).
     */
    public function minTags(int $min): self
    {
        $this->assertTags('minTags');
        if ($min < 1 || ($this->maxTags !== null && $min > $this->maxTags)) {
            throw new InvalidArgumentException('minTags() must be at least 1, and at most maxTags().');
        }
        $this->minTags = $min;
        $this->required = true;

        return $this;
    }

    /**
     * The most tags of a tags() field: the user cannot add more.
     */
    public function maxTags(int $max): self
    {
        $this->assertTags('maxTags');
        if ($max < 1 || ($this->minTags !== null && $max < $this->minTags)) {
            throw new InvalidArgumentException('maxTags() must be at least 1, and at least minTags().');
        }
        $this->maxTags = $max;

        return $this;
    }

    /**
     * The text offering a new tag in the list, `%s` being what the user typed
     * (default "Add %s").
     */
    public function addText(string $text): self
    {
        $this->assertTags('addText');
        if (!str_contains($text, '%s')) {
            throw new InvalidArgumentException('addText() needs a %s for the typed text.');
        }
        $this->addText = $text;

        return $this;
    }

    /**
     * A star rating: radio buttons shown as stars, in a fieldset with the label as its
     * legend. Submits a whole number from 1 to stars(), or "" for "No rating" when the
     * field is not required.
     */
    public static function rating(string $name, ?string $label = null): self
    {
        return new self('rating', $name, $label ?? self::humanize($name));
    }

    /**
     * How many stars a rating() field has (3 to 10, default 5).
     */
    public function stars(int $count): self
    {
        $this->assertType('rating', 'stars');
        if ($count < 3 || $count > 10) {
            throw new InvalidArgumentException('A rating has 3 to 10 stars.');
        }
        $this->stars = $count;

        return $this;
    }

    /**
     * Texts of a rating() field, for translation: each star's name, with the value and
     * the number of stars (default "%d of %d"), and the choice that clears the rating
     * when the field is not required (default "No rating").
     */
    public function ratingLabels(?string $star = null, ?string $clear = null): self
    {
        $this->assertType('rating', 'ratingLabels');
        if ($star !== null) {
            if (substr_count($star, '%d') < 1) {
                throw new InvalidArgumentException('The star label needs a %d for the value.');
            }
            $this->starLabel = $star;
        }
        $this->clearLabel = $clear ?? $this->clearLabel;

        return $this;
    }

    /**
     * A one-time code (a code sent by SMS or e-mail, or from an authenticator app): one
     * input, shown as a box per character. The browser and phones offer the code received
     * (`autocomplete="one-time-code"`), and pasting works. Digits only, 6 by default.
     * Its error message is "Enter the 6-digit code."; message() replaces it.
     */
    public static function otp(string $name, ?string $label = null): self
    {
        return new self('otp', $name, $label ?? self::humanize($name));
    }

    /**
     * How many characters a one-time code has (4 to 10, default 6).
     */
    public function codeLength(int $length): self
    {
        $this->assertType('otp', 'codeLength');
        if ($length < 4 || $length > 10) {
            throw new InvalidArgumentException('A one-time code has 4 to 10 characters.');
        }
        $this->codeLength = $length;

        return $this;
    }

    /**
     * A one-time code of letters and digits, e.g. `K7M2QX`, instead of digits only.
     */
    public function alphanumeric(bool $alphanumeric = true): self
    {
        $this->assertType('otp', 'alphanumeric');
        $this->alphanumeric = $alphanumeric;

        return $this;
    }

    /**
     * A list of key-value pairs (metadata, HTTP headers, settings): a row per pair, with
     * Add and Remove buttons. Each row submits as `name[n][key]` and `name[n][value]`;
     * Field::keyValues() turns the submitted rows into a `key => value` array. value()
     * takes such an array. A value needs a key, and a key is used once.
     */
    public static function keyValue(string $name, ?string $label = null): self
    {
        return new self('keyvalue', $name, $label ?? self::humanize($name));
    }

    /**
     * Keys the key inputs suggest as the user types (a datalist), e.g. common HTTP headers.
     * Other keys are accepted.
     *
     * @param list<string> $keys
     */
    public function keySuggestions(array $keys): self
    {
        $this->assertType('keyvalue', 'keySuggestions');
        $this->keySuggestions = array_values(array_unique(array_map('strval', $keys)));

        return $this;
    }

    /**
     * The texts of a key-value list: the column names (each input is named "Key 2",
     * "Value 2" for assistive technology), the buttons (`remove` takes the row's position)
     * and the messages for a value without a key and for a key used twice.
     */
    public function keyValueLabels(
        ?string $key = null,
        ?string $value = null,
        ?string $add = null,
        ?string $remove = null,
        ?string $missing = null,
        ?string $duplicate = null,
    ): self {
        $this->assertType('keyvalue', 'keyValueLabels');
        if ($remove !== null && !str_contains($remove, '%d')) {
            throw new InvalidArgumentException('The remove label needs %d for the row\'s position.');
        }
        foreach (compact('key', 'value', 'add', 'remove', 'missing', 'duplicate') as $name => $text) {
            if ($text !== null) {
                $this->pairLabels[$name] = $text;
            }
        }

        return $this;
    }

    /**
     * The submitted rows of a key-value list as a `key => value` array. Keys are trimmed;
     * rows without a key are left out; a key used twice keeps its last value.
     *
     * @return array<string, string>
     */
    public static function keyValues(mixed $rows): array
    {
        $values = [];
        foreach (self::pairs($rows) as ['key' => $key, 'value' => $value]) {
            if ($key !== '') {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public static function checkbox(string $name, ?string $label = null): self
    {
        return new self('checkbox', $name, $label ?? self::humanize($name));
    }

    /**
     * On/off switch: a checkbox shown as a toggle, announced as a switch. Submits "1" when on.
     */
    public static function switch(string $name, ?string $label = null): self
    {
        return new self('switch', $name, $label ?? self::humanize($name));
    }

    /**
     * Radio buttons in a fieldset, the label as its legend. required() requires a choice.
     *
     * @param array<string|int, string> $choices value => label
     */
    public static function radio(string $name, array $choices, ?string $label = null): self
    {
        return new self('radio', $name, $label ?? self::humanize($name), self::assertChoices($choices));
    }

    /**
     * Checkboxes in a fieldset, the label as its legend. Submits the checked values as an
     * array (`name[]`); value() takes an array. required() requires at least one.
     *
     * @param array<string|int, string> $choices value => label
     */
    public static function checkboxes(string $name, array $choices, ?string $label = null): self
    {
        return new self('checkboxes', $name, $label ?? self::humanize($name), self::assertChoices($choices));
    }

    /**
     * Slider (min 0, max 100 and step 1 unless set). The current value is shown next to
     * the label.
     */
    public static function range(string $name, ?string $label = null): self
    {
        return new self('range', $name, $label ?? self::humanize($name));
    }

    /**
     * Color picker. Submits a lowercase hex color such as `#0d6efd`.
     */
    public static function color(string $name, ?string $label = null): self
    {
        return new self('color', $name, $label ?? self::humanize($name));
    }

    /**
     * File upload. Without upload() it is a plain file input (the form switches to
     * multipart); with upload() FilePond sends each file to that endpoint first.
     */
    public static function file(string $name, ?string $label = null): self
    {
        return (new self('file', $name, $label ?? self::humanize($name)))->rules(UploadRules::images());
    }

    /**
     * Rich text editor (Quill). Submits HTML: always sanitize it on the server
     * (see EditorHtml::sanitize()).
     */
    public static function editor(string $name, ?string $label = null): self
    {
        return new self('editor', $name, $label ?? self::humanize($name));
    }

    /**
     * Accepted files for a file field (defaults to UploadRules::images()). Use the same
     * rules in the UploadHandler.
     */
    public function rules(UploadRules $rules): self
    {
        $this->assertType('file', 'rules');
        $this->uploadRules = $rules;

        return $this;
    }

    /**
     * Uploads each file asynchronously to this endpoint (FilePond server protocol: POST
     * returns the id as text/plain, DELETE with the id as body reverts it). The form then
     * submits the ids; claim them with UploadHandler::claim().
     */
    public function upload(string $url): self
    {
        $this->assertType('file', 'upload');
        $this->uploadUrl = $url;

        return $this;
    }

    /**
     * Editor toolbar: "basic", "standard" (default), "full", or a Quill toolbar array.
     *
     * @param string|list<mixed> $toolbar
     */
    public function toolbar(string|array $toolbar): self
    {
        $this->assertType('editor', 'toolbar');

        if (is_string($toolbar) && !in_array($toolbar, self::TOOLBARS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown toolbar "%s". Use %s or a Quill toolbar array.',
                $toolbar,
                implode(', ', self::TOOLBARS),
            ));
        }

        $this->toolbar = $toolbar;

        return $this;
    }

    /**
     * A select the user can type in to filter its choices (Tom Select). Works on
     * multiple() selects too.
     */
    public function searchable(bool $searchable = true): self
    {
        $this->assertType('select', 'searchable');
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Several choices: submits `name[]`, and value() takes an array. Searchable selects
     * show the choices as removable tags.
     */
    public function multiple(bool $multiple = true): self
    {
        $this->assertType('select', 'multiple');
        if ($this->tags && !$multiple) {
            throw new InvalidArgumentException('A tags() field is always multiple.');
        }
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * Loads the choices from an endpoint as the user types (`GET $url?search=ada`), and
     * makes the select searchable. The endpoint answers with respond(), on the same
     * field definition. The choices given to select() are the initial ones: include the
     * selected value, so its label shows before any search.
     *
     * @param string $value Field of the data source rows submitted as the value.
     * @param string $label Field shown, searched and sorted on.
     * @param int $limit Choices per search (1 to 100).
     */
    public function remote(string $url, string $value = 'id', string $label = 'name', int $limit = 20): self
    {
        $this->assertType('select', 'remote');

        foreach ([$value, $label] as $field) {
            if (!DataRequest::isValidField($field)) {
                throw new InvalidArgumentException(sprintf('Invalid field name "%s".', $field));
            }
        }
        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException('The limit must be between 1 and 100.');
        }

        $this->remote = ['url' => $url, 'value' => $value, 'label' => $label, 'limit' => $limit];
        $this->searchable = true;

        return $this;
    }

    /**
     * The endpoint of a remote() select: the first choices whose label contains the
     * `search` term, sorted by label, as `{"data": [{"value": …, "label": …}], "meta": {…}}`.
     * Only the value and label fields leave the data source.
     *
     * @param array<string, mixed> $query Usually `$_GET` or `$request->query()`.
     */
    public function respond(DataSourceInterface $source, array $query): DataResult
    {
        if ($this->remote === null) {
            throw new LogicException(sprintf('Field "%s" has no remote() endpoint.', $this->name));
        }

        ['value' => $value, 'label' => $label, 'limit' => $limit] = $this->remote;
        $request = DataRequest::fromArray(
            [
                'search' => $query['search'] ?? null,
                'page' => $query['page'] ?? 1,
                'per_page' => $limit,
                'sort' => $label,
            ],
            sortable: [$label],
            searchable: [$label],
            defaultPerPage: $limit,
        );

        return $source->fetch($request)->map(static fn (array $row): array => [
            'value' => Arr::toText(Arr::get($row, $value)),
            'label' => Arr::toText(Arr::get($row, $label)),
        ]);
    }

    /**
     * Shows the choices of a radio or checkbox group on one line.
     */
    public function inline(bool $inline = true): self
    {
        if (!in_array($this->type, ['radio', 'checkboxes'], true)) {
            throw new InvalidArgumentException('inline() is only available on radio and checkboxes fields.');
        }
        $this->inline = $inline;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function minLength(int $length): self
    {
        return $this->attribute('minlength', $length);
    }

    public function maxLength(int $length): self
    {
        return $this->attribute('maxlength', $length);
    }

    public function min(int|float|string $min): self
    {
        return $this->attribute('min', $min);
    }

    public function max(int|float|string $max): self
    {
        return $this->attribute('max', $max);
    }

    /**
     * Granularity of number, range, date and time fields, e.g. 0.5, or 900 (15 minutes,
     * in seconds) for a time field.
     */
    public function step(int|float|string $step): self
    {
        return $this->attribute('step', $step);
    }

    /**
     * HTML5 pattern (anchored implicitly, no delimiters), e.g. `[A-Z]{2}[0-9]{4}`.
     */
    public function pattern(string $pattern): self
    {
        return $this->attribute('pattern', $pattern);
    }

    /**
     * Requires the same value as another field of the form (password confirmation).
     */
    public function equalTo(string $fieldName): self
    {
        $this->equalTo = $fieldName;

        return $this;
    }

    /**
     * Inputmask.js mask, e.g. `99/99/9999` or `(999) 999-9999`.
     */
    public function mask(string $mask): self
    {
        $this->mask = $mask;

        return $this;
    }

    /**
     * Custom client-side error message for every rule of this field.
     */
    public function message(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function value(mixed $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function attribute(string $name, string|int|float|bool|null $value = true): self
    {
        $this->attributes[$name] = $value;

        return $this;
    }

    /**
     * How many columns of its form or section the field takes: a number, or "full".
     */
    public function columnSpan(int|string $span): self
    {
        Grid::assertSpan($span);
        $this->columnSpan = $span;

        return $this;
    }

    public function getColumnSpan(): int|string|null
    {
        return $this->columnSpan;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * A copy of the field under another name: a repeater's row, e.g. `lines[0][product]`.
     *
     * @internal
     */
    public function withName(string $name): self
    {
        $copy = clone $this;
        $copy->name = $name;

        return $copy;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string|null $error Server-side error message to display.
     * @param mixed $value Submitted value, takes precedence over the field default.
     * @param bool $floating Bootstrap floating label (text-like inputs, selects and textareas only).
     */
    public function render(string $formId, ?string $error = null, mixed $value = null, bool $floating = false): string
    {
        $id = self::fieldId($formId, $this->name);
        $value ??= $this->value;
        $floating = $floating && $this->canFloat();

        if ($this->type === 'hidden') {
            return Html::tag('input', [
                'type' => 'hidden',
                'id' => $id,
                'name' => $this->name,
                'value' => Arr::toText($value),
            ]);
        }

        $helpText = $this->help ?? ($this->type === 'file' ? $this->uploadRules?->describe() : null);
        $helpId = $helpText === null ? null : $id . '-help';
        $errorId = $error === null ? null : $id . '-error';
        $asyncUpload = $this->type === 'file' && $this->uploadUrl !== null;

        if ($this->type === 'radio' || $this->type === 'checkboxes') {
            return $this->renderGroup($id, $value, $error, $helpText, $helpId, $errorId);
        }

        if ($this->type === 'period') {
            return $this->renderPeriod($id, $value, $error, $helpText, $helpId, $errorId);
        }

        if ($this->type === 'rating') {
            return $this->renderRating($id, $value, $error, $helpText, $helpId, $errorId);
        }

        if ($this->type === 'keyvalue') {
            return $this->renderKeyValue($id, $value, $error, $helpText, $helpId, $errorId);
        }

        $attributes = array_merge($this->attributes, [
            'id' => $id,
            'name' => $this->name,
            // FilePond enforces "required" itself (see upload.js); the native attribute would block it.
            'required' => $this->required && !$asyncUpload,
            // Floating labels need a placeholder (Bootstrap shows the label in its place).
            'placeholder' => $floating ? ($this->placeholder ?? $this->label) : $this->placeholder,
            'aria-describedby' => Html::classes([$helpId, $errorId]),
            'aria-invalid' => $error === null ? null : 'true',
            'data-parsley-equalto' => $this->equalTo === null ? null : '#' . self::fieldId($formId, $this->equalTo),
            'data-parsley-error-message' => $this->message,
            'data-celerik-mask' => $this->mask,
        ]);

        $control = match ($this->type) {
            'textarea' => Html::tag(
                'textarea',
                $attributes + ['class' => $this->controlClass('form-control', $error)],
                Html::escape(Arr::toText($value)),
            ),
            'select' => Html::tag(
                'select',
                array_merge($attributes, [
                    'name' => $this->multiple && !str_ends_with($this->name, '[]') ? $this->name . '[]' : $this->name,
                    'multiple' => $this->multiple,
                    // Tom Select shows the placeholder in its search box.
                    'placeholder' => $this->searchable ? null : $attributes['placeholder'],
                    'data-celerik-select' => $this->searchable ? self::json([
                        'url' => $this->remote['url'] ?? null,
                        'placeholder' => $this->placeholder,
                        'tags' => $this->tags ? true : null,
                        'max' => $this->maxTags,
                        'add' => $this->addText,
                    ]) : null,
                    'data-parsley-mincheck' => $this->minTags,
                    'data-parsley-maxcheck' => $this->maxTags,
                ]) + ['class' => $this->controlClass('form-select', $error)],
                $this->renderChoices($value),
            ),
            'file' => $this->renderFile($attributes, $error),
            'editor' => Html::tag(
                'textarea',
                $attributes + [
                    'class' => $this->controlClass('form-control celerik-editor-source', $error),
                    'rows' => 8,
                    'data-celerik-editor' => self::json([
                        'toolbar' => $this->toolbar,
                        'placeholder' => $this->placeholder,
                    ]),
                ],
                Html::escape(Arr::toText($value)),
            ),
            'checkbox', 'switch' => Html::tag('input', $attributes + [
                'type' => 'checkbox',
                'role' => $this->type === 'switch' ? 'switch' : null,
                'value' => '1',
                'checked' => self::isOn($value),
                'class' => $this->controlClass('form-check-input', $error),
            ]),
            'range' => Html::tag('input', $attributes + [
                'type' => 'range',
                'value' => $value === null ? null : Arr::toText($value),
                'class' => $this->controlClass('form-range', $error),
            ]),
            // Parsley's pattern message ("This value seems to be invalid") does not say what
            // is wrong. message() replaces this, e.g. to translate it.
            'otp' => Html::tag('input', array_merge($attributes, [
                'data-parsley-error-message' => $this->message ?? sprintf(
                    $this->alphanumeric ? 'Enter the %d-character code.' : 'Enter the %d-digit code.',
                    $this->codeLength,
                ),
            ]) + [
                'type' => 'text',
                'value' => $value === null ? null : Arr::toText($value),
                'inputmode' => $this->alphanumeric ? 'text' : 'numeric',
                'autocomplete' => 'one-time-code',
                'autocapitalize' => 'off',
                'spellcheck' => 'false',
                'maxlength' => $this->codeLength,
                'pattern' => sprintf($this->alphanumeric ? '[A-Za-z0-9]{%d}' : '[0-9]{%d}', $this->codeLength),
                'class' => $this->controlClass('form-control celerik-otp-input', $error),
                // The width of the code without JavaScript (no style attribute: CSP).
                'size' => $this->codeLength,
                'data-celerik-otp' => $this->codeLength,
            ]),
            'color' => Html::tag('input', $attributes + [
                'type' => 'color',
                'value' => $value === null ? null : Arr::toText($value),
                'class' => $this->controlClass('form-control form-control-color', $error),
            ]),
            default => Html::tag('input', $attributes + [
                'type' => $this->type,
                'value' => $value === null ? null : Arr::toText($value),
                'class' => $this->controlClass('form-control', $error),
            ]),
        };

        $label = Html::tag(
            'label',
            [
                'for' => $id,
                // The editor's content area and FilePond's input are labelled by id (aria-labelledby).
                'id' => in_array($this->type, ['editor', 'file'], true) ? $id . '-label' : null,
                'class' => match (true) {
                    $this->type === 'checkbox', $this->type === 'switch' => 'form-check-label',
                    $floating => null,
                    default => 'form-label',
                },
            ],
            Html::escape($this->label) . ($this->required ? self::REQUIRED_MARKER : '')
                . $this->rangeOutput($id, $value),
        );

        $help = $helpId === null
            ? ''
            : Html::tag('div', ['id' => $helpId, 'class' => 'form-text'], Html::escape($helpText));
        $feedback = $errorId === null ? '' : Html::tag(
            'div',
            ['id' => $errorId, 'class' => 'invalid-feedback celerik-server-error'],
            Html::escape($error),
        );

        // Checkboxes, switches and floating labels put the control before its label.
        $content = $this->type === 'checkbox' || $this->type === 'switch' || $floating
            ? $control . $label . $feedback . $help
            : $label . $control . $feedback . $help;

        $wrapper = match (true) {
            $this->type === 'checkbox' => 'mb-3 form-check',
            $this->type === 'switch' => 'mb-3 form-check form-switch',
            $floating => 'form-floating mb-3',
            default => 'mb-3',
        };

        return Html::tag('div', ['class' => $wrapper], $content);
    }

    private function canFloat(): bool
    {
        // Tom Select replaces the select: its search box has no floating label.
        return !$this->searchable && !$this->multiple && !in_array(
            $this->type,
            [
                'checkbox', 'switch', 'radio', 'checkboxes', 'period', 'rating', 'otp', 'keyvalue',
                'range', 'color', 'file', 'editor', 'hidden',
            ],
            true,
        );
    }

    /**
     * A fieldset of radio buttons or checkboxes. The first input has the field's id, so
     * error summary links lead to the group.
     */
    private function renderGroup(
        string $id,
        mixed $value,
        ?string $error,
        ?string $helpText,
        ?string $helpId,
        ?string $errorId,
    ): string {
        $radio = $this->type === 'radio';
        $selected = self::selectedValues($value);

        $inputs = '';
        $index = 0;
        foreach ($this->choices as $choice => $choiceLabel) {
            $inputId = $index === 0 ? $id : $id . '-' . $index;
            $input = Html::tag('input', array_merge($this->attributes, [
                'type' => $radio ? 'radio' : 'checkbox',
                'id' => $inputId,
                'name' => $radio || str_ends_with($this->name, '[]') ? $this->name : $this->name . '[]',
                'value' => (string) $choice,
                'checked' => in_array((string) $choice, $selected, true),
                // Every radio of a required group is required (HTML). A checkbox group needs
                // one checked box, which only Parsley checks: native "required" on each box
                // would mean every box.
                'required' => $radio && $this->required,
                'data-parsley-required' => !$radio && $this->required && $index === 0 ? 'true' : null,
                'data-parsley-error-message' => $index === 0 ? $this->message : null,
                'aria-invalid' => $error === null ? null : 'true',
                'class' => $this->controlClass('form-check-input', $error),
            ]));
            $label = Html::tag('label', ['for' => $inputId, 'class' => 'form-check-label'], Html::escape($choiceLabel));
            $inputs .= Html::tag(
                'div',
                ['class' => $this->inline ? 'form-check form-check-inline' : 'form-check'],
                $input . $label,
            );
            $index++;
        }

        $legend = Html::tag(
            'legend',
            ['class' => 'form-label fs-6'],
            Html::escape($this->label) . ($this->required ? self::REQUIRED_MARKER : ''),
        );
        $fieldset = Html::tag('fieldset', [
            'id' => $id . '-group',
            'aria-describedby' => Html::classes([$helpId, $errorId]),
        ], $legend . $inputs);

        // The inputs are not siblings of the message: show it explicitly (d-block).
        $feedback = $errorId === null ? '' : Html::tag(
            'div',
            ['id' => $errorId, 'class' => 'invalid-feedback d-block celerik-server-error'],
            Html::escape($error),
        );
        $help = $helpId === null
            ? ''
            : Html::tag('div', ['id' => $helpId, 'class' => 'form-text'], Html::escape($helpText));

        return Html::tag('div', ['class' => 'mb-3'], $fieldset . $feedback . $help);
    }

    /**
     * A rating() field: a fieldset of radio buttons, each labelled by a star (an SVG, its
     * name visually hidden). The CSS fills the stars up to the checked one. The first
     * star has the field's id, so error summary links lead to the group.
     */
    private function renderRating(
        string $id,
        mixed $value,
        ?string $error,
        ?string $helpText,
        ?string $helpId,
        ?string $errorId,
    ): string {
        $current = $value === null ? '' : Arr::toText($value);
        $star = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . '<path d="M12 2.5l2.94 5.96 6.58.96-4.76 4.64 1.12 6.55L12 17.52l-5.88 3.09 1.12-6.55'
            . '-4.76-4.64 6.58-.96z"/></svg>';

        $radio = function (string $inputId, string $choice, int $index) use ($current, $error): string {
            return Html::tag('input', array_merge($this->attributes, [
                'type' => 'radio',
                'id' => $inputId,
                'name' => $this->name,
                'value' => $choice,
                'checked' => $choice === $current,
                'required' => $this->required,
                'data-parsley-error-message' => $index === 0 ? $this->message : null,
                'aria-invalid' => $error === null ? null : 'true',
                'class' => $this->controlClass('visually-hidden celerik-rating-input', $error),
            ]));
        };

        $stars = '';
        for ($count = 1; $count <= $this->stars; $count++) {
            $inputId = $count === 1 ? $id : $id . '-' . $count;
            $name = sprintf($this->starLabel, $count, $this->stars);
            $stars .= $radio($inputId, (string) $count, $count - 1) . Html::tag(
                'label',
                ['for' => $inputId, 'class' => 'celerik-rating-star'],
                $star . Html::tag('span', ['class' => 'visually-hidden'], Html::escape($name)),
            );
        }
        $content = Html::tag('div', ['class' => 'celerik-rating-stars'], $stars);

        // Not required: a choice that clears the rating, so mouse users can undo theirs.
        if (!$this->required) {
            $clearId = $id . '-none';
            $content .= Html::tag(
                'div',
                ['class' => 'celerik-rating-clear'],
                $radio($clearId, '', $this->stars)
                    . Html::tag('label', ['for' => $clearId], Html::escape($this->clearLabel)),
            );
        }

        $legend = Html::tag(
            'legend',
            ['class' => 'form-label fs-6'],
            Html::escape($this->label) . ($this->required ? self::REQUIRED_MARKER : ''),
        );
        $fieldset = Html::tag('fieldset', [
            'id' => $id . '-group',
            'class' => 'celerik-rating',
            'aria-describedby' => Html::classes([$helpId, $errorId]),
        ], $legend . $content);

        $feedback = $errorId === null ? '' : Html::tag(
            'div',
            ['id' => $errorId, 'class' => 'invalid-feedback d-block celerik-server-error'],
            Html::escape($error),
        );
        $help = $helpId === null
            ? ''
            : Html::tag('div', ['id' => $helpId, 'class' => 'form-text'], Html::escape($helpText));

        return Html::tag('div', ['class' => 'mb-3'], $fieldset . $feedback . $help);
    }

    /**
     * A keyValue() field: a fieldset with a row per pair (a key input, a value input and a
     * Remove button), an Add button and a <template> of an empty row for form.js. The first
     * key input has the field's id, so error summary links lead to the list.
     */
    private function renderKeyValue(
        string $id,
        mixed $value,
        ?string $error,
        ?string $helpText,
        ?string $helpId,
        ?string $errorId,
    ): string {
        // A new form starts with one empty row, which also works without JavaScript.
        $pairs = self::pairs($value);
        if ($pairs === []) {
            $pairs = [['key' => '', 'value' => '']];
        }
        $datalistId = $this->keySuggestions === [] ? null : $id . '-keys';

        $rows = '';
        foreach ($pairs as $index => $pair) {
            $rows .= $this->renderPair($id, (string) $index, $index + 1, $pair, $datalistId);
        }

        $legend = Html::tag(
            'legend',
            ['class' => 'form-label fs-6'],
            Html::escape($this->label) . ($this->required ? self::REQUIRED_MARKER : ''),
        );
        // The column names, for sighted users: each input has its own (hidden) label.
        $head = Html::tag(
            'div',
            ['class' => 'celerik-key-value-head', 'aria-hidden' => 'true'],
            Html::tag('span', [], Html::escape($this->pairLabels['key']))
                . Html::tag('span', [], Html::escape($this->pairLabels['value'])),
        );
        $add = Html::tag('button', [
            'type' => 'button',
            'class' => 'btn btn-sm btn-outline-primary',
            'data-celerik-key-value-add' => true,
        ], Html::escape($this->pairLabels['add']));
        $template = Html::tag(
            'template',
            [],
            $this->renderPair($id, self::PAIR_PLACEHOLDER, 0, ['key' => '', 'value' => ''], $datalistId),
        );
        $datalist = '';
        if ($datalistId !== null) {
            $options = '';
            foreach ($this->keySuggestions as $key) {
                $options .= Html::tag('option', ['value' => $key]);
            }
            $datalist = Html::tag('datalist', ['id' => $datalistId], $options);
        }

        $fieldset = Html::tag('fieldset', [
            'id' => $id . '-group',
            'aria-describedby' => Html::classes([$helpId, $errorId]),
        ], $legend . $head . Html::tag('ol', ['class' => 'celerik-key-value-rows'], $rows)
            . $add . $template . $datalist);

        $feedback = $errorId === null ? '' : Html::tag(
            'div',
            ['id' => $errorId, 'class' => 'invalid-feedback d-block celerik-server-error'],
            Html::escape($error),
        );
        $help = $helpId === null
            ? ''
            : Html::tag('div', ['id' => $helpId, 'class' => 'form-text'], Html::escape($helpText));

        return Html::tag('div', [
            'class' => 'mb-3 celerik-key-value',
            'data-celerik-key-value' => self::json([
                'id' => $id,
                'required' => $this->required,
                'key' => $this->pairLabels['key'],
                'value' => $this->pairLabels['value'],
                'remove' => $this->pairLabels['remove'],
            ]),
        ], $fieldset . $feedback . $help);
    }

    /**
     * One row of a key-value list. Its position names the inputs and the Remove button
     * ("Key 2"); form.js renumbers them when rows are added or removed.
     *
     * @param array{key: string, value: string} $pair
     */
    private function renderPair(string $id, string $index, int $position, array $pair, ?string $datalistId): string
    {
        $keyId = $index === '0' ? $id : $id . '-' . $index . '-key';
        $valueId = $id . '-' . $index . '-value';
        $name = $this->name . '[' . $index . ']';

        $key = Html::tag('input', array_merge($this->attributes, [
            'type' => 'text',
            'id' => $keyId,
            'name' => $name . '[key]',
            'value' => $pair['key'] === '' ? null : $pair['key'],
            'list' => $datalistId,
            'autocomplete' => 'off',
            // A required list needs a key in its first row (form.js keeps it on the first row).
            'required' => $this->required && $position === 1,
            'data-parsley-required-message' => $this->required ? $this->pairLabels['missing'] : null,
            // form.js: a value needs a key, and a key is used once.
            'data-parsley-validate-if-empty' => 'true',
            'data-parsley-keyneeded' => 'true',
            'data-parsley-keyneeded-message' => $this->pairLabels['missing'],
            'data-parsley-keyunique' => 'true',
            'data-parsley-keyunique-message' => $this->pairLabels['duplicate'],
            'data-parsley-error-message' => $this->message,
            'class' => 'form-control celerik-key-value-key',
        ]));
        $value = Html::tag('input', array_merge($this->attributes, [
            'type' => 'text',
            'id' => $valueId,
            'name' => $name . '[value]',
            'value' => $pair['value'] === '' ? null : $pair['value'],
            'class' => 'form-control celerik-key-value-value',
        ]));
        $hiddenLabel = static fn (string $for, string $text): string => Html::tag(
            'label',
            ['for' => $for, 'class' => 'visually-hidden'],
            Html::escape($text . ' ' . $position),
        );
        $remove = Html::tag('button', [
            'type' => 'button',
            'class' => 'btn btn-outline-secondary celerik-key-value-remove',
            'data-celerik-key-value-remove' => true,
        ], '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round"><path d="M4 4l8 8M12 4l-8 8"/></svg>'
            . Html::tag(
                'span',
                ['class' => 'visually-hidden'],
                Html::escape(sprintf($this->pairLabels['remove'], $position)),
            ));

        return Html::tag(
            'li',
            ['class' => 'celerik-key-value-row', 'data-celerik-index' => $index],
            $hiddenLabel($keyId, $this->pairLabels['key']) . $key
                . $hiddenLabel($valueId, $this->pairLabels['value']) . $value . $remove,
        );
    }

    /**
     * Key-value pairs from a `key => value` array (value()) or submitted rows (old input:
     * a list of `['key' => …, 'value' => …]`), in order, as text.
     *
     * @return list<array{key: string, value: string}>
     */
    private static function pairs(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        $submitted = array_filter(
            $value,
            static fn (mixed $row): bool => !is_array($row) || !array_key_exists('key', $row),
        ) === [];

        $pairs = [];
        foreach ($value as $key => $item) {
            $pairs[] = $submitted
                ? ['key' => trim(Arr::toText($item['key'])), 'value' => Arr::toText($item['value'] ?? null)]
                : ['key' => trim((string) $key), 'value' => Arr::toText($item)];
        }

        return $pairs;
    }

    /**
     * A dateRange() field: a fieldset with the start and end inputs side by side. The start
     * has the field's id, so error summary links lead to it.
     */
    private function renderPeriod(
        string $id,
        mixed $value,
        ?string $error,
        ?string $helpText,
        ?string $helpId,
        ?string $errorId,
    ): string {
        $inputs = '';
        foreach (['start', 'end'] as $index => $part) {
            $inputId = $part === 'start' ? $id : $id . '-end';
            $partValue = is_array($value) ? ($value[$part] ?? null) : null;
            $input = Html::tag('input', array_merge($this->attributes, [
                'type' => $this->periodType,
                'id' => $inputId,
                'name' => $this->name . '[' . $part . ']',
                'value' => $partValue === null || $partValue === '' ? null : Arr::toText($partValue),
                'required' => $this->required,
                'aria-invalid' => $error === null ? null : 'true',
                'data-parsley-error-message' => $this->message,
                // form.js: on or after the start, and the end picker starts there.
                'data-parsley-after' => $part === 'end' ? '#' . $id : null,
                'class' => $this->controlClass('form-control', $error),
            ]));
            $label = Html::tag(
                'label',
                ['for' => $inputId, 'class' => 'form-label'],
                Html::escape($this->periodLabels[$index]),
            );
            $inputs .= Html::tag('div', ['class' => 'col-sm-6'], $label . $input);
        }

        $legend = Html::tag(
            'legend',
            ['class' => 'form-label fs-6'],
            Html::escape($this->label) . ($this->required ? self::REQUIRED_MARKER : ''),
        );
        $fieldset = Html::tag('fieldset', [
            'id' => $id . '-group',
            'aria-describedby' => Html::classes([$helpId, $errorId]),
        ], $legend . Html::tag('div', ['class' => 'row g-2'], $inputs));

        $feedback = $errorId === null ? '' : Html::tag(
            'div',
            ['id' => $errorId, 'class' => 'invalid-feedback d-block celerik-server-error'],
            Html::escape($error),
        );
        $help = $helpId === null
            ? ''
            : Html::tag('div', ['id' => $helpId, 'class' => 'form-text'], Html::escape($helpText));

        return Html::tag('div', ['class' => 'mb-3'], $fieldset . $feedback . $help);
    }

    /**
     * The value of a range field, after its label. form.js updates it as the slider moves.
     * Hidden from assistive technology: the slider announces its value itself.
     */
    private function rangeOutput(string $id, mixed $value): string
    {
        if ($this->type !== 'range') {
            return '';
        }

        if ($value === null || $value === '') {
            // Without a value, browsers start the slider halfway between min and max.
            $min = (float) ($this->attributes['min'] ?? 0);
            $max = (float) ($this->attributes['max'] ?? 100);
            $value = $min + ($max - $min) / 2;
            $value = $value == (int) $value ? (int) $value : $value;
        }

        return ': ' . Html::tag('output', [
            'for' => $id,
            'class' => 'celerik-range-value',
            'aria-hidden' => 'true',
        ], Html::escape(Arr::toText($value)));
    }

    private static function isOn(mixed $value): bool
    {
        return $value !== null && $value !== false && $value !== '' && $value !== '0' && $value !== 0;
    }

    /**
     * @param array<string|int, string> $choices
     * @return array<string|int, string>
     */
    private static function assertChoices(array $choices): array
    {
        if ($choices === []) {
            throw new InvalidArgumentException('A radio or checkboxes field needs at least one choice.');
        }

        return $choices;
    }

    public static function fieldId(string $formId, string $name): string
    {
        return $formId . '-' . trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $name), '-');
    }

    public function isFile(): bool
    {
        return $this->type === 'file';
    }

    /**
     * Whether the field submits an array: a date range, checkboxes, a multiple select or
     * several files.
     */
    public function submitsArray(): bool
    {
        return str_ends_with($this->name, '[]')
            || in_array($this->type, ['period', 'checkboxes', 'keyvalue'], true)
            || ($this->type === 'select' && $this->multiple)
            || ($this->isFile() && ($this->uploadRules ?? UploadRules::images())->maxFiles > 1);
    }

    /**
     * @param array<string, string|int|float|bool|null> $attributes
     */
    private function renderFile(array $attributes, ?string $error): string
    {
        $rules = $this->uploadRules ?? UploadRules::images();
        $multiple = $rules->maxFiles > 1;

        $attributes['name'] = $multiple && !str_ends_with($this->name, '[]') ? $this->name . '[]' : $this->name;
        $attributes['placeholder'] = null;

        $upload = $this->uploadUrl === null ? null : self::json([
            'url' => $this->uploadUrl,
            'required' => $this->required,
            'maxFiles' => $rules->maxFiles,
            'maxSize' => $rules->maxSize,
            'types' => array_keys($rules->types),
        ]);

        return Html::tag('input', $attributes + [
            'type' => 'file',
            'accept' => $rules->accept(),
            'multiple' => $multiple,
            'class' => $this->controlClass('form-control', $error),
            'data-celerik-upload' => $upload,
            // FilePond replaces this input; it validates the files itself.
            'data-parsley-excluded' => $upload === null ? null : 'true',
        ]);
    }

    private function assertTags(string $method): void
    {
        if (!$this->tags) {
            throw new InvalidArgumentException(sprintf('%s() is only available on tags fields.', $method));
        }
    }

    private function assertType(string $type, string $method): void
    {
        if ($this->type !== $type) {
            throw new InvalidArgumentException(sprintf('%s() is only available on %s fields.', $method, $type));
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function json(array $data): string
    {
        return json_encode(
            array_filter($data, static fn (mixed $value): bool => $value !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function controlClass(string $base, ?string $error): string
    {
        return $error === null ? $base : $base . ' is-invalid';
    }

    private function renderChoices(mixed $value): string
    {
        // A multiple select has no "nothing chosen" option: nothing selected is empty.
        $html = $this->placeholder === null || $this->multiple
            ? ''
            : Html::tag('option', ['value' => ''], Html::escape($this->placeholder));
        $selected = self::selectedValues($value);

        foreach ($this->choices as $choice => $label) {
            $html .= Html::tag('option', [
                'value' => (string) $choice,
                'selected' => in_array((string) $choice, $selected, true),
            ], Html::escape($label));
        }

        // Tags the user typed are not among the suggestions: keep them (value(), old input).
        if ($this->tags) {
            $suggested = array_map('strval', array_keys($this->choices));
            foreach (array_unique(array_diff($selected, $suggested)) as $tag) {
                if (trim($tag) !== '') {
                    $html .= Html::tag('option', ['value' => $tag, 'selected' => true], Html::escape($tag));
                }
            }
        }

        return $html;
    }

    /**
     * The selected values of a select or a choice group, as strings.
     *
     * @return list<string>
     */
    private static function selectedValues(mixed $value): array
    {
        $values = match (true) {
            is_array($value) => array_values($value),
            $value === null => [],
            default => [$value],
        };

        return array_map(static fn (mixed $item): string => Arr::toText($item), $values);
    }

    private static function humanize(string $name): string
    {
        return ucfirst(trim(str_replace(['_', '-', '[', ']'], ' ', $name)));
    }
}
