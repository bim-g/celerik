<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Data\CardMove;
use Celerik\Ui\Data\DataRequest;
use Celerik\Ui\Data\InvalidCardMove;
use Celerik\Ui\Support\Arr;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A task board (Kanban): columns of cards that move from one column to another.
 *
 * Every card has a "Move to" choice and button: a form that posts without JavaScript, and
 * a way to move cards without dragging (WCAG 2.5.7). With JavaScript, cards are also dragged
 * (SortableJS), and moved from the keyboard: Space picks a card up, the arrow keys move it,
 * Space drops it and Escape cancels, each step announced. A move is sent to moveUrl().
 *
 *     TaskBoard::make('sprint')->label('Sprint')
 *         ->columns(['todo' => 'To do', 'doing' => ['label' => 'In progress', 'limit' => 3], 'done' => 'Done'])
 *         ->cards($tasks, column: 'status')->description('summary')->meta('owner')
 *         ->moveUrl('/tasks/{id}/move')->csrf($token);
 */
final class TaskBoard extends Component
{
    public const LABELS = [
        'move' => 'Move',
        'moveTo' => 'Move {card} to',
        'help' => 'Press Space to pick up a card. Move it with the arrow keys, then press Space to drop it, '
            . 'or Escape to cancel.',
        'grabbed' => 'Picked up {card}, in {column}, position {position} of {count}.',
        'over' => '{card}: {column}, position {position} of {count}.',
        'moved' => 'Moved {card} to {column}, position {position} of {count}.',
        'cancelled' => 'Move cancelled. {card} is back in {column}.',
        'failed' => 'The card could not be moved. It is back in {column}.',
        'full' => '{column} is full.',
        'count' => '{count} cards',
        'countOne' => '1 card',
        'countLimit' => '{count} of {limit} cards',
        'invalid' => 'Choose a column of the board.',
    ];

    private ?string $label = null;

    /** @var array<string, array{label: string, limit: ?int}> */
    private array $columns = [];

    /** @var list<array<string, mixed>> */
    private array $cards = [];

    private string $idField = 'id';
    private string $columnField = 'status';
    private string $titleField = 'title';
    private ?string $descriptionField = null;

    /** @var list<string> */
    private array $metaFields = [];
    private ?string $linkTemplate = null;
    private ?string $moveUrl = null;
    private string $moveMethod = 'PATCH';
    private ?string $csrfToken = null;
    private string $csrfField = '_token';
    private string $csrfHeader = 'X-CSRF-TOKEN';
    private int $headingLevel = 2;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'taskboard';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The columns, in order: key => label, or key => ['label' => ..., 'limit' => 3]. A limit
     * stops cards from being dropped into a full column in the browser; check it on the
     * server too.
     *
     * @param array<string|int, string|array{label: string, limit?: int|null}> $columns
     */
    public function columns(array $columns): self
    {
        $this->columns = [];
        foreach ($columns as $key => $column) {
            $key = (string) $key;
            if ($key === '' || preg_match('/^[A-Za-z0-9_-]+$/', $key) !== 1) {
                throw new InvalidArgumentException(sprintf(
                    'Invalid column key "%s": use letters, digits, - and _.',
                    $key,
                ));
            }
            $label = is_array($column) ? ($column['label'] ?? $key) : $column;
            $limit = is_array($column) ? ($column['limit'] ?? null) : null;
            if ($limit !== null && (!is_int($limit) || $limit < 1)) {
                throw new InvalidArgumentException(sprintf('The limit of column "%s" is a number above 0.', $key));
            }
            $this->columns[$key] = ['label' => (string) $label, 'limit' => $limit];
        }

        return $this;
    }

    /**
     * The cards, in order within each column. `$column` is the field that holds a card's
     * column key; cards whose column is not on the board are left out.
     *
     * @param iterable<array<string, mixed>> $rows
     */
    public function cards(iterable $rows, string $id = 'id', string $column = 'status', string $title = 'title'): self
    {
        foreach ([$id, $column, $title] as $field) {
            self::field($field);
        }
        $this->idField = $id;
        $this->columnField = $column;
        $this->titleField = $title;
        $this->cards = [];
        foreach ($rows as $row) {
            $this->cards[] = $row;
        }

        return $this;
    }

    public function description(?string $field): self
    {
        $this->descriptionField = $field === null ? null : self::field($field);

        return $this;
    }

    public function meta(string ...$fields): self
    {
        $this->metaFields = array_map(self::field(...), array_values($fields));

        return $this;
    }

    /**
     * Makes a card's title a link: `{field}` takes the card's value, URL-encoded.
     */
    public function link(?string $template): self
    {
        $this->linkTemplate = $template;

        return $this;
    }

    /**
     * Where a move is sent, e.g. '/tasks/{id}/move': a form-encoded POST with `column`,
     * `position` (from 0; left out without JavaScript), `_method` and the CSRF token. Read it
     * with cardMove(); answer a refused move with a 422 and InvalidCardMove::toArray().
     */
    public function moveUrl(string $url, string $method = 'PATCH'): self
    {
        $method = strtoupper($method);
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            throw new InvalidArgumentException(sprintf('Moves are sent with POST, PUT or PATCH, not %s.', $method));
        }
        $this->moveUrl = $url;
        $this->moveMethod = $method;

        return $this;
    }

    public function csrf(string $token, string $field = '_token', string $header = 'X-CSRF-TOKEN'): self
    {
        $this->csrfToken = $token;
        $this->csrfField = $field;
        $this->csrfHeader = $header;

        return $this;
    }

    /**
     * The columns' heading level, 2 to 5; the cards' titles are one level below.
     */
    public function headingLevel(int $level): self
    {
        if ($level < 2 || $level > 5) {
            throw new InvalidArgumentException('A board column heading is h2 to h5.');
        }
        $this->headingLevel = $level;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown board label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    /**
     * Reads a move the browser sent ($_POST): a column of the board, and a position from 0.
     *
     * @param array<string, mixed> $input
     * @throws InvalidCardMove With a message for the user (answer it with a 422).
     */
    public function cardMove(array $input): CardMove
    {
        $column = is_string($input['column'] ?? null) ? $input['column'] : '';
        if (!isset($this->columns[$column])) {
            throw new InvalidCardMove($this->labels['invalid']);
        }
        $position = $input['position'] ?? null;
        if ($position === null || $position === '') {
            return new CardMove($column, null);
        }
        if (!is_scalar($position) || preg_match('/^\d{1,9}$/', (string) $position) !== 1) {
            throw new InvalidCardMove($this->labels['invalid']);
        }

        return new CardMove($column, (int) $position);
    }

    public function toArray(): array
    {
        $columns = [];
        foreach ($this->columns as $key => $column) {
            $columns[] = array_filter(
                ['key' => (string) $key, 'label' => $column['label'], 'limit' => $column['limit']],
                static fn (mixed $value): bool => $value !== null,
            );
        }

        return array_filter([
            'columns' => $columns,
            'moveUrl' => $this->moveUrl,
            'method' => $this->moveUrl === null ? null : $this->moveMethod,
            'csrf' => $this->csrfToken === null ? null : [
                'token' => $this->csrfToken,
                'field' => $this->csrfField,
                'header' => $this->csrfHeader,
            ],
            'labels' => $this->labels,
            'options' => $this->options,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    public function render(): string
    {
        if ($this->columns === []) {
            throw new LogicException(sprintf('Task board "%s" has no columns.', $this->id));
        }

        $byColumn = array_fill_keys(array_keys($this->columns), []);
        foreach ($this->cards as $card) {
            $key = Arr::toText(Arr::get($card, $this->columnField));
            if (isset($byColumn[$key])) {
                $byColumn[$key][] = $card;
            }
        }

        $help = $this->id . '-help';
        $html = '';
        $index = 0;
        foreach ($this->columns as $key => $column) {
            $headingId = $this->id . '-column-' . $key;
            $cards = '';
            foreach ($byColumn[$key] as $card) {
                $cards .= $this->renderCard($card, (string) $key, $help, ++$index);
            }
            $count = count($byColumn[$key]);
            $heading = Html::tag('h' . $this->headingLevel, [
                'class' => 'celerik-board-column-title',
                'id' => $headingId,
            ], Html::tag('span', [], Html::escape($column['label'])) . ' '
                . $this->renderCount($count, $column['limit']));
            $html .= Html::tag('section', [
                'class' => 'celerik-board-column',
                'aria-labelledby' => $headingId,
                'data-column' => $key,
            ], $heading . Html::tag('ul', [
                'class' => 'celerik-board-cards list-unstyled',
                'data-column' => $key,
                'aria-labelledby' => $headingId,
            ], $cards));
        }

        return Html::tag('div', array_merge($this->rootAttributes(['celerik-board']), [
            'role' => 'region',
            'aria-label' => $this->label,
        ]), Html::tag('p', ['class' => 'visually-hidden', 'id' => $help], Html::escape($this->labels['help']))
            . Html::tag('div', ['class' => 'celerik-board-columns'], $html));
    }

    /**
     * The count in a column's heading: "3 cards", or "3 of 5 cards" with a limit.
     */
    private function renderCount(int $count, ?int $limit): string
    {
        $text = match (true) {
            $limit !== null => strtr(
                $this->labels['countLimit'],
                ['{count}' => (string) $count, '{limit}' => (string) $limit],
            ),
            $count === 1 => $this->labels['countOne'],
            default => strtr($this->labels['count'], ['{count}' => (string) $count]),
        };
        $short = $limit === null ? (string) $count : $count . '/' . $limit;

        return Html::tag('span', ['class' => 'celerik-board-count', 'data-celerik-board-count' => true], Html::tag(
            'span',
            ['class' => 'badge rounded-pill text-bg-secondary', 'aria-hidden' => 'true'],
            Html::escape($short),
        ) . Html::tag('span', ['class' => 'visually-hidden'], Html::escape($text)));
    }

    /**
     * A card: its title (a link with link()), description and meta, and its Move form. The
     * same markup as taskboard.js writes for a card it moves.
     *
     * @param array<string, mixed> $card
     */
    private function renderCard(array $card, string $column, string $help, int $index): string
    {
        $text = static fn (?string $field): string => $field === null ? '' : Arr::toText(Arr::get($card, $field));
        $title = $text($this->titleField);
        $heading = Html::escape($title);
        if ($this->linkTemplate !== null) {
            $heading = Html::tag('a', ['href' => self::fill($this->linkTemplate, $card)], $heading);
        }
        $body = Html::tag('h' . ($this->headingLevel + 1), ['class' => 'celerik-board-card-title'], $heading);
        if ($text($this->descriptionField) !== '') {
            $description = Html::escape($text($this->descriptionField));
            $body .= Html::tag('p', ['class' => 'celerik-board-card-description'], $description);
        }
        $meta = '';
        foreach ($this->metaFields as $field) {
            if ($text($field) !== '') {
                $meta .= Html::tag('span', [], Html::escape($text($field)));
            }
        }
        if ($meta !== '') {
            $body .= Html::tag('p', ['class' => 'celerik-board-card-meta'], $meta);
        }

        return Html::tag('li', [
            'class' => 'celerik-board-card',
            'data-id' => $text($this->idField),
            'tabindex' => '0',
            'aria-describedby' => $this->moveUrl === null ? null : $help,
        ], $body . $this->renderMove($card, $column, $title, $index));
    }

    /**
     * @param array<string, mixed> $card
     */
    private function renderMove(array $card, string $column, string $title, int $index): string
    {
        if ($this->moveUrl === null) {
            return '';
        }
        $fields = $this->moveMethod === 'POST'
            ? ''
            : Html::tag('input', ['type' => 'hidden', 'name' => '_method', 'value' => $this->moveMethod]);
        if ($this->csrfToken !== null) {
            $token = ['type' => 'hidden', 'name' => $this->csrfField, 'value' => $this->csrfToken];
            $fields .= Html::tag('input', $token);
        }
        $options = '';
        foreach ($this->columns as $key => $definition) {
            $options .= Html::tag('option', [
                'value' => (string) $key,
                'selected' => (string) $key === $column,
            ], Html::escape($definition['label']));
        }
        $id = $this->id . '-move-' . $index;

        return Html::tag('form', [
            'class' => 'celerik-board-move',
            'method' => 'post',
            'action' => self::fill($this->moveUrl, $card),
        ], $fields
            . Html::tag('label', ['class' => 'visually-hidden', 'for' => $id], Html::escape(
                strtr($this->labels['moveTo'], ['{card}' => $title]),
            ))
            . Html::tag('div', ['class' => 'input-group input-group-sm'], Html::tag('select', [
                'id' => $id,
                'name' => 'column',
                'class' => 'form-select form-select-sm',
            ], $options) . Html::tag(
                'button',
                ['type' => 'submit', 'class' => 'btn btn-outline-secondary celerik-action-button'],
                Html::escape($this->labels['move']),
            )));
    }

    private static function field(string $field): string
    {
        if (!DataRequest::isValidField($field)) {
            throw new InvalidArgumentException(sprintf('Invalid field "%s".', $field));
        }

        return $field;
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
