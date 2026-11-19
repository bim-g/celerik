<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use ArrayAccess;
use Celerik\Ui\Component;
use Celerik\Ui\Components\RecordView\Entry;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A read-only layout of a record's fields, by sections (Filament's infolists): each section a
 * heading and a description list of labels and values, in one to four columns (one on small
 * screens). The record is an array, an ArrayAccess (an Eloquent model) or an object; fields
 * use dot notation ("company.name").
 *
 *     RecordView::make('member')->record($member)
 *         ->section('Profile', [Entry::text('name', 'Name'), Entry::email('email', 'E-mail')], columns: 2)
 *         ->section('Account', [Entry::badge('status', 'Status', ['active' => 'success'])]);
 */
final class RecordView extends Component
{
    public const LABELS = ['empty' => 'Not set', 'yes' => 'Yes', 'no' => 'No'];

    private mixed $record = [];
    private int $headingLevel = 2;

    /** @var list<array{title: ?string, description: ?string, entries: list<Entry>, columns: int}> */
    private array $sections = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'recordview';
    }

    /**
     * @param array<array-key, mixed>|object $record
     */
    public function record(array|object $record): self
    {
        $this->record = $record;

        return $this;
    }

    /**
     * A section: a heading (none with null), a description, its entries and its columns (1 to 4).
     *
     * @param list<Entry> $entries
     */
    public function section(?string $title, array $entries, ?string $description = null, int $columns = 1): self
    {
        if ($entries === []) {
            throw new InvalidArgumentException('A record view section needs entries.');
        }
        foreach ($entries as $entry) {
            if (!$entry instanceof Entry) {
                throw new InvalidArgumentException('A record view section holds Entry objects.');
            }
        }
        if ($columns < 1 || $columns > 4) {
            throw new InvalidArgumentException('A record view section has 1 to 4 columns.');
        }
        $this->sections[] = [
            'title' => $title,
            'description' => $description,
            'entries' => array_values($entries),
            'columns' => $columns,
        ];

        return $this;
    }

    /**
     * The sections' headings, h2 (the default) to h6.
     */
    public function headingLevel(int $level): self
    {
        if ($level < 2 || $level > 6) {
            throw new InvalidArgumentException('A record view heading is h2 to h6.');
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
                throw new InvalidArgumentException(sprintf('Unknown record view label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->sections === []) {
            throw new LogicException(sprintf('Record view "%s" has no sections.', $this->id));
        }
        $html = '';
        foreach ($this->sections as $index => $section) {
            $id = $this->id . '-section-' . ($index + 1);
            $items = '';
            $columns = $section['columns'];
            foreach ($section['entries'] as $entry) {
                $raw = self::read($this->record, $entry->getField());
                $value = $entry->renderValue($raw, $this->record, $this->labels);
                $help = $entry->getHelp() === null
                    ? ''
                    : Html::tag('div', ['class' => 'form-text'], Html::escape($entry->getHelp()));
                $items .= Html::tag('div', [
                    'class' => Html::classes([
                        'celerik-recordview-entry',
                        $entry->getSpan() > 1 ? 'celerik-recordview-span-' . min($entry->getSpan(), $columns) : null,
                    ]),
                ], Html::tag('dt', [], Html::escape($entry->getLabel())) . Html::tag('dd', [], $value . $help));
            }
            $list = Html::tag('dl', [
                'class' => 'celerik-recordview-entries celerik-recordview-cols-' . $section['columns'],
            ], $items);

            if ($section['title'] === null) {
                $html .= Html::tag('div', ['class' => 'celerik-recordview-section'], $list);
                continue;
            }
            $heading = Html::tag(
                'h' . $this->headingLevel,
                ['class' => 'celerik-recordview-title', 'id' => $id . '-title'],
                Html::escape($section['title']),
            );
            $description = $section['description'] === null
                ? ''
                : Html::tag('p', ['class' => 'celerik-recordview-description'], Html::escape($section['description']));
            $html .= Html::tag(
                'section',
                ['class' => 'celerik-recordview-section', 'aria-labelledby' => $id . '-title'],
                $heading . $description . $list,
            );
        }

        return Html::tag('div', $this->rootAttributes(['celerik-recordview']), $html);
    }

    /**
     * A field of the record, by dot notation, from arrays, ArrayAccess and objects.
     */
    private static function read(mixed $record, string $path): mixed
    {
        if (is_array($record) && array_key_exists($path, $record)) {
            return $record[$path];
        }
        $value = $record;
        foreach (explode('.', $path) as $segment) {
            if (is_array($value)) {
                if (!array_key_exists($segment, $value)) {
                    return null;
                }
                $value = $value[$segment];
            } elseif ($value instanceof ArrayAccess) {
                if (!$value->offsetExists($segment)) {
                    return null;
                }
                $value = $value[$segment];
            } elseif (is_object($value)) {
                if (!isset($value->{$segment})) {
                    return null;
                }
                $value = $value->{$segment};
            } else {
                return null;
            }
        }

        return $value;
    }
}
