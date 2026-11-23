<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Support\Arr;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use Stringable;

/**
 * A button that does something: opens a page, or sends a request after an optional
 * confirmation. Use it on its own (a page, a toolbar) or in a Grid's rows (Grid::actions()).
 *
 *     Action::link('edit', 'Edit', '/users/{id}/edit');
 *     Action::request('delete', 'Delete', '/users/{id}')->method('DELETE')->variant('danger')
 *         ->confirm('Delete {name}?', 'This cannot be undone.')->csrf($token);
 *
 * `{field}` in the URL, the confirmation and the accessible name is replaced by the row's
 * value (URL-encoded in the URL). A request action is a form: without JavaScript it posts,
 * with the method in `_method` (Laravel and Symfony read it); with JavaScript it asks for
 * confirmation in a dialog, sends the request with fetch and shows the answer's `message`.
 *
 * A request action with a form (form()) opens it in a modal, filled with the row's values,
 * and sends it to its URL instead:
 *
 *     Action::request('edit', 'Edit', '/users/{id}')->method('PATCH')
 *         ->form(Form::make('user-form')->add(Field::text('name')->required()), 'Edit {name}');
 */
final class Action implements Stringable
{
    public const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    public const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info'];

    /** Texts of the confirmation dialog and of a failed request. */
    public const LABELS = [
        'cancel' => 'Cancel',
        'failed' => 'The action failed. Try again.',
    ];

    private const PLACEHOLDER = '/\{([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\}/';

    private string $method = 'GET';
    private string $variant = 'secondary';
    private bool $solid = false;
    private ?string $confirm = null;
    private ?string $confirmDetail = null;
    private ?string $ariaLabel = null;
    private ?string $csrfToken = null;
    private string $csrfField = '_token';
    private string $csrfHeader = 'X-CSRF-TOKEN';

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    private ?Form $form = null;
    private ?string $formTitle = null;
    private ?string $submitLabel = null;
    private ?string $modalSize = null;

    private function __construct(
        private readonly string $name,
        private readonly string $label,
        private readonly string $url,
        private readonly bool $isRequest,
    ) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid action name "%s".', $name));
        }
    }

    /**
     * A link to a page, e.g. a record's page: `/users/{id}`.
     */
    public static function link(string $name, string $label, string $url): self
    {
        return new self($name, $label, $url, false);
    }

    /**
     * A request to an endpoint, POST unless method() says otherwise.
     */
    public static function request(string $name, string $label, string $url): self
    {
        $action = new self($name, $label, $url, true);
        $action->method = 'POST';

        return $action;
    }

    public function method(string $method): self
    {
        $method = strtoupper($method);
        if (!$this->isRequest || !in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException(sprintf(
                'A request action takes one of %s; a link is always GET.',
                implode(', ', self::METHODS),
            ));
        }
        $this->method = $method;

        return $this;
    }

    /**
     * The button's color, from Bootstrap: outlined unless solid().
     */
    public function variant(string $variant): self
    {
        if (!in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown action variant "%s". Available: %s.',
                $variant,
                implode(', ', self::VARIANTS),
            ));
        }
        $this->variant = $variant;

        return $this;
    }

    public function solid(bool $solid = true): self
    {
        $this->solid = $solid;

        return $this;
    }

    /**
     * Asks before sending the request: the question (the dialog's title) and an optional
     * detail. The confirm button repeats the action's label.
     */
    public function confirm(string $question, ?string $detail = null): self
    {
        if (!$this->isRequest) {
            throw new InvalidArgumentException('Only a request action can ask for confirmation.');
        }
        $this->confirm = $question;
        $this->confirmDetail = $detail;

        return $this;
    }

    /**
     * The accessible name, when the label alone is ambiguous: 'Delete {name}'. In a grid it
     * defaults to the label and the row's title (Grid::rowTitle()).
     */
    public function ariaLabel(string $ariaLabel): self
    {
        $this->ariaLabel = $ariaLabel;

        return $this;
    }

    /**
     * The CSRF token: a hidden field for the form, a header for fetch.
     */
    public function csrf(string $token, string $field = '_token', string $header = 'X-CSRF-TOKEN'): self
    {
        $this->csrfToken = $token;
        $this->csrfField = $field;
        $this->csrfHeader = $header;

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
                    'Unknown action label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    /**
     * Opens a form in a modal, instead of sending the request at once: Save sends the form's
     * fields to the action's URL with its method. In a grid's row, the fields take the row's
     * values (fields named like columns). `{field}` in the title takes the row's value.
     *
     * - A 2xx answer closes the modal and shows its `message`; a grid reloads its rows.
     * - A 422 answer with `errors` ({"field": ["..."]}) shows them on the fields.
     *
     * The form's own submit button is left out: the modal has Cancel and `$submitLabel`
     * (the action's label by default). Without JavaScript, the button is not shown.
     */
    public function form(Form $form, ?string $title = null, ?string $submitLabel = null, ?string $size = null): self
    {
        if (!$this->isRequest) {
            throw new InvalidArgumentException(sprintf(
                'Action "%s" opens a form: use Action::request().',
                $this->name,
            ));
        }
        if ($size !== null && !in_array($size, ['sm', 'lg', 'xl'], true)) {
            throw new InvalidArgumentException(sprintf('Unknown modal size "%s". Available: sm, lg, xl.', $size));
        }
        $this->form = $form->submit(null);
        $this->formTitle = $title;
        $this->submitLabel = $submitLabel;
        $this->modalSize = $size;

        return $this;
    }

    public function hasForm(): bool
    {
        return $this->form !== null;
    }

    /**
     * The id of the modal that holds the form.
     */
    public function modalId(): ?string
    {
        return $this->form === null ? null : $this->form->getId() . '-modal';
    }

    /**
     * The modal with the form, once per page: render() includes it, unless told not to (a
     * grid renders it once for all its rows).
     */
    public function renderModal(): string
    {
        if ($this->form === null) {
            return '';
        }
        $formId = $this->form->getId();
        $title = $this->formTitle === null || self::placeholdersIn($this->formTitle) !== []
            ? $this->label
            : $this->formTitle;

        return Modal::make((string) $this->modalId())
            // A title for a row ("Edit {name}") is written when the modal opens.
            ->title($title)
            ->body($this->form)
            ->size($this->modalSize ?? 'lg')
            ->cancelButton($this->labels['cancel'])
            ->button($this->submitLabel ?? $this->label, $this->variant, attributes: [
                'type' => 'submit',
                'form' => $formId,
                'data-caelrik-form-submit' => true,
            ])
            ->render();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function hasAriaLabel(): bool
    {
        return $this->ariaLabel !== null;
    }

    public function isRequest(): bool
    {
        return $this->isRequest;
    }

    /**
     * The action as a submit button of a grid's bulk form (Grid::bulkActions()): it posts the
     * selected rows to its URL. `{count}` in its confirmation is the number of rows selected;
     * `_method` goes with the button, as each button may have its own.
     *
     * @internal
     */
    public function renderBulkButton(): string
    {
        $labels = $this->labels === self::LABELS ? null : json_encode($this->labels, JSON_THROW_ON_ERROR);

        return Html::tag('button', [
            'type' => 'submit',
            'formaction' => $this->url,
            'name' => $this->method === 'POST' ? null : '_method',
            'value' => $this->method === 'POST' ? null : $this->method,
            'class' => $this->buttonClass() . ' btn-sm',
            'data-caelrik-action' => $this->name,
            'data-method' => $this->method,
            'data-confirm' => $this->confirm,
            'data-confirm-detail' => $this->confirmDetail,
            'data-csrf-header' => $this->csrfToken === null ? null : $this->csrfHeader,
            'data-labels' => $labels,
        ], Html::escape($this->label));
    }

    /**
     * The hidden CSRF field, or an empty string without a token.
     *
     * @internal
     */
    public function renderCsrfField(): string
    {
        if ($this->csrfToken === null) {
            return '';
        }

        return Html::tag('input', ['type' => 'hidden', 'name' => $this->csrfField, 'value' => $this->csrfToken]);
    }

    /**
     * The row fields this action reads, from its URL, confirmation and accessible name.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return array_values(array_unique(self::placeholdersIn(
            $this->url . ' ' . $this->confirm . ' ' . $this->confirmDetail . ' ' . $this->ariaLabel
            . ' ' . $this->formTitle,
        )));
    }

    /**
     * The action, for a grid that renders rows in JavaScript (server-side mode).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'label' => $this->label,
            'url' => $this->url,
            'request' => $this->isRequest,
            'method' => $this->method,
            'className' => $this->buttonClass(),
            'confirm' => $this->confirm,
            'confirmDetail' => $this->confirmDetail,
            'ariaLabel' => $this->ariaLabel,
            'csrf' => $this->csrfToken === null ? null : [
                'token' => $this->csrfToken,
                'field' => $this->csrfField,
                'header' => $this->csrfHeader,
            ],
            'labels' => $this->labels === self::LABELS ? null : $this->labels,
            'form' => $this->form === null ? null : [
                'modal' => $this->modalId(),
                'title' => $this->formTitle ?? $this->label,
                'fields' => $this->formFields(),
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== false);
    }

    /**
     * The action for a row: `{field}` takes the row's values.
     *
     * @param array<string, mixed> $row
     * @param string|null $ariaLabel An accessible name used when ariaLabel() was not set.
     * @param bool $modal With a form: render the modal too (once per page).
     */
    public function render(array $row = [], bool $small = false, ?string $ariaLabel = null, bool $modal = true): string
    {
        $text = static fn (string $template): string => self::fill($template, $row, false);
        $name = $this->ariaLabel !== null ? $text($this->ariaLabel) : $ariaLabel;
        $class = $this->buttonClass() . ($small ? ' btn-sm' : '');
        $label = Html::escape($this->label);
        $url = self::fill($this->url, $row, true);

        if (!$this->isRequest) {
            return Html::tag('a', ['href' => $url, 'class' => $class, 'aria-label' => $name], $label);
        }

        $fields = '';
        if ($this->method !== 'POST') {
            $fields .= Html::tag('input', ['type' => 'hidden', 'name' => '_method', 'value' => $this->method]);
        }
        if ($this->csrfToken !== null) {
            $token = ['type' => 'hidden', 'name' => $this->csrfField, 'value' => $this->csrfToken];
            $fields .= Html::tag('input', $token);
        }

        $labels = $this->labels === self::LABELS ? null : json_encode($this->labels, JSON_THROW_ON_ERROR);

        return Html::tag('form', [
            'method' => 'post',
            'action' => $url,
            'class' => 'caelrik-action',
            'data-caelrik-action' => $this->name,
            'data-method' => $this->method,
            'data-confirm' => $this->confirm === null ? null : $text($this->confirm),
            'data-confirm-detail' => $this->confirmDetail === null ? null : $text($this->confirmDetail),
            'data-csrf-header' => $this->csrfToken === null ? null : $this->csrfHeader,
            'data-labels' => $labels,
            'data-caelrik-form-modal' => $this->modalId(),
            'data-title' => $this->form === null ? null : $text($this->formTitle ?? $this->label),
            'data-values' => $this->form === null ? null : json_encode(
                $this->formValues($row),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT,
            ),
        ], $fields . Html::tag('button', [
            'type' => 'submit',
            'class' => $class,
            'aria-label' => $name,
            'aria-haspopup' => $this->form === null ? null : 'dialog',
        ], $label)) . ($modal ? $this->renderModal() : '');
    }

    /**
     * The form's field names.
     *
     * @return list<string>
     */
    private function formFields(): array
    {
        return $this->form === null ? [] : array_values(array_unique(array_map(
            static fn (Form\Field $field): string => $field->getName(),
            $this->form->getFields(),
        )));
    }

    /**
     * The row's values for the form's fields: dates as Y-m-d H:i:s, others as they are.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formValues(array $row): array
    {
        $values = [];
        foreach ($this->formFields() as $field) {
            // A field the row does not have keeps the form's own value.
            if (!array_key_exists($field, $row) && Arr::get($row, $field) === null) {
                continue;
            }
            $value = Arr::get($row, $field);
            $values[$field] = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
        }

        return $values;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    private function buttonClass(): string
    {
        return 'btn caelrik-action-button btn-' . ($this->solid ? '' : 'outline-') . $this->variant;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fill(string $template, array $row, bool $encode): string
    {
        $replace = static function (array $match) use ($row, $encode): string {
            $value = Arr::toText(Arr::get($row, $match[1]));

            return $encode ? rawurlencode($value) : $value;
        };

        return (string) preg_replace_callback(self::PLACEHOLDER, $replace, $template);
    }

    /**
     * @return list<string>
     */
    private static function placeholdersIn(string $template): array
    {
        preg_match_all(self::PLACEHOLDER, $template, $matches);

        return $matches[1];
    }
}
