<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A chip: a compact label, plain, removable or selectable.
 * - removable(): a Remove button ("Remove PHP") takes the chip and its hidden field away
 *   (JavaScript; without it the button is hidden), moves focus to the next chip's button,
 *   says so, and fires a cancelable celerik:remove.
 * - checkbox(): a filter chip, a real checkbox posted with its form, with or without
 *   JavaScript.
 *
 *     Chip::make()->label('PHP')->removable('skills[]', 'php');
 *     Chip::make('chip-php')->label('PHP')->checkbox('skills[]', 'php', checked: true);
 */
final class Chip extends Component
{
    public const LABELS = ['remove' => 'Remove {label}', 'removed' => '{label} removed.'];

    private string $label = '';
    private ?string $avatar = null;
    private bool $removable = false;
    private bool $checkbox = false;
    private ?string $field = null;
    private ?string $value = null;
    private bool $checked = false;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'chip';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A small picture before the label (decorative: the label names the chip).
     */
    public function avatar(?Avatar $avatar): self
    {
        $this->avatar = $avatar?->size('sm')->decorative()->render();

        return $this;
    }

    /**
     * A Remove button; with a field name, a hidden field posts the value while the chip stays.
     */
    public function removable(?string $name = null, ?string $value = null): self
    {
        $this->removable = true;
        $this->checkbox = false;
        $this->field = $name;
        $this->value = $value ?? $this->label;

        return $this;
    }

    /**
     * A filter chip: a checkbox named $name, posting $value when checked.
     */
    public function checkbox(string $name, string $value, bool $checked = false): self
    {
        $this->checkbox = true;
        $this->removable = false;
        $this->field = $name;
        $this->value = $value;
        $this->checked = $checked;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown chip label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->removable ? ['labels' => ['removed' => $this->labels['removed']]] : [];
    }

    public function render(): string
    {
        if (trim($this->label) === '') {
            throw new LogicException('A chip needs a label.');
        }
        $text = ($this->avatar ?? '')
            . Html::tag('span', ['class' => 'celerik-chip-label'], Html::escape($this->label));

        if ($this->checkbox) {
            return Html::tag('span', array_merge($this->attributes, [
                'class' => Html::classes(['celerik-chip-check', ...$this->classes]),
            ]), Html::tag('input', [
                'type' => 'checkbox',
                'class' => 'btn-check',
                'id' => $this->id,
                'name' => $this->field,
                'value' => $this->value,
                'checked' => $this->checked,
                'autocomplete' => 'off',
            ]) . Html::tag('label', ['class' => 'celerik-chip', 'for' => $this->id], $text));
        }

        if (!$this->removable) {
            return Html::tag('span', array_merge($this->attributes, [
                'class' => Html::classes(['celerik-chip', ...$this->classes]),
            ]), $text);
        }

        $hidden = $this->field === null
            ? ''
            : Html::tag('input', ['type' => 'hidden', 'name' => $this->field, 'value' => $this->value]);
        $button = Html::tag('button', [
            'type' => 'button',
            'class' => 'celerik-chip-remove',
            'aria-label' => strtr($this->labels['remove'], ['{label}' => $this->label]),
        ], '<span aria-hidden="true">×</span>');

        return Html::tag('span', $this->rootAttributes(['celerik-chip']), $text . $hidden . $button);
    }
}
