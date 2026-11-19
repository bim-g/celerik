<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A button that opens a menu of links and actions (the WAI-ARIA menu button; the Telerik
 * DropDownButton): Export ▾ → CSV, Excel, Print.
 *
 * With JavaScript, the menu opens with Enter, Space or Down (Up: on its last item), its
 * items are chosen with the arrow keys, Home, End and their first letter, and Escape or Tab
 * closes it, returning focus to the button. Without JavaScript, the items are a plain list.
 *
 *     MenuButton::make('export')->label('Export')
 *         ->link('CSV', '/members.csv')->link('Excel', '/members.xlsx')
 *         ->divider()->action(Action::request('email', 'E-mail me the list', '/members/email'));
 */
final class MenuButton extends Component
{
    use HasMenuItems;

    public const VARIANTS = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];

    private string $label = '';
    private string $variant = 'secondary';
    private bool $solid = false;
    private bool $small = false;
    private string $align = 'start';

    public static function name(): string
    {
        return 'menubutton';
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The button's color, outlined unless solid.
     */
    public function variant(string $variant, bool $solid = false): self
    {
        if (!in_array($variant, self::VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown button variant "%s".', $variant));
        }
        $this->variant = $variant;
        $this->solid = $solid;

        return $this;
    }

    public function small(bool $small = true): self
    {
        $this->small = $small;

        return $this;
    }

    /**
     * 'start' (the menu's left edge under the button's) or 'end' (right edges aligned).
     */
    public function align(string $align): self
    {
        if (!in_array($align, ['start', 'end'], true)) {
            throw new InvalidArgumentException('A menu aligns to the button\'s start or end.');
        }
        $this->align = $align;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->label === '' || $this->items === []) {
            throw new LogicException(sprintf('Menu button "%s" needs a label() and items.', $this->id));
        }
        $menu = $this->id . '-menu';
        $button = Html::tag('button', [
            'type' => 'button',
            'class' => Html::classes([
                'btn',
                'btn-' . ($this->solid ? '' : 'outline-') . $this->variant,
                $this->small ? 'btn-sm' : null,
                // Outlined buttons keep 4.5:1 text in dark mode, as actions do.
                'celerik-action-button',
                'celerik-menu-toggle',
                'dropdown-toggle',
            ]),
            'id' => $this->id . '-button',
            'aria-controls' => $menu,
        ], Html::escape($this->label));

        $classes = $this->align === 'end' ? ['celerik-menubutton', 'celerik-menu-end'] : ['celerik-menubutton'];

        return Html::tag('div', $this->rootAttributes($classes), $button . $this->renderItems($menu));
    }
}
