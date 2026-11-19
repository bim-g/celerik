<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use LogicException;
use Stringable;

/**
 * A row of controls: actions, menu buttons, split buttons, your own buttons and fields.
 *
 * With JavaScript it is a WAI-ARIA toolbar: one tab stop, and Left, Right, Home and End
 * move between its controls (a menu button's own keys still open its menu). Without, each
 * control is a tab stop.
 *
 *     Toolbar::make('members-tools')->label('Members')
 *         ->add(Action::link('new', 'New member', '/members/new')->variant('primary')->solid())
 *         ->separator()
 *         ->add($exportMenu, $moreSplit);
 */
final class Toolbar extends Component
{
    private string $label = '';

    /** @var list<string|Stringable|null> null: a separator */
    private array $items = [];

    public static function name(): string
    {
        return 'toolbar';
    }

    /**
     * The toolbar's accessible name, e.g. 'Members' or 'Text formatting'. Required.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Controls: components (Action, MenuButton, SplitButton...) or Html::raw() (a string is
     * escaped text).
     */
    public function add(string|Stringable ...$items): self
    {
        array_push($this->items, ...$items);

        return $this;
    }

    public function separator(): self
    {
        $this->items[] = null;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->label === '') {
            throw new LogicException(sprintf('Toolbar "%s" needs a label().', $this->id));
        }
        $html = '';
        foreach ($this->items as $item) {
            $html .= $item === null
                ? Html::tag('span', ['class' => 'celerik-toolbar-separator', 'aria-hidden' => 'true'], '')
                : Html::tag('div', ['class' => 'celerik-toolbar-item'], Html::content($item));
        }

        return Html::tag('div', array_merge($this->rootAttributes(['celerik-toolbar']), [
            'role' => 'toolbar',
            'aria-label' => $this->label,
        ]), $html);
    }
}
