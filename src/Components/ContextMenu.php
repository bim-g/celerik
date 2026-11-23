<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A menu of actions for an element, opened by a right click, Shift+F10 or the Menu key on
 * it: the files of a list, a grid's rows, the cards of a page.
 *
 * Targets are the elements matching `for()`; `{field}` in the items' URLs and texts takes
 * the target's `data-field` value (`data-id="7"` fills `{id}`). The menu is written in the
 * browser for each target, as an ARIA menu.
 *
 * A context menu is a shortcut: offer the same actions another way too (a row's buttons, a
 * MenuButton), for users who never right-click, and on touch screens.
 *
 *     ContextMenu::make('file-menu')->for('#files [data-id]')
 *         ->link('Open', '/files/{id}')
 *         ->action(Action::request('delete', 'Delete {name}', '/files/{id}')->method('DELETE')
 *             ->confirm('Delete {name}?'));
 */
final class ContextMenu extends Component
{
    private ?string $target = null;
    private string $label = 'Actions';

    /** @var list<array<string, mixed>> */
    private array $items = [];

    public static function name(): string
    {
        return 'contextmenu';
    }

    /**
     * A CSS selector for the elements the menu opens on.
     */
    public function for(string $selector): self
    {
        if (trim($selector) === '') {
            throw new InvalidArgumentException('A context menu needs a selector for its targets.');
        }
        $this->target = $selector;

        return $this;
    }

    /**
     * The menu's accessible name; 'Actions' by default.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->items[] = ['type' => 'link', 'label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * An Action::link() or Action::request() (confirmations included; forms in modals are
     * not supported here).
     */
    public function action(Action $action): self
    {
        if ($action->hasForm()) {
            throw new InvalidArgumentException('A context menu action cannot open a form: use a MenuButton.');
        }
        $this->items[] = ['type' => 'action', 'action' => $action->toArray()];

        return $this;
    }

    public function divider(): self
    {
        $this->items[] = ['type' => 'divider'];

        return $this;
    }

    public function toArray(): array
    {
        return ['target' => $this->target, 'label' => $this->label, 'items' => $this->items];
    }

    public function render(): string
    {
        if ($this->target === null || $this->items === []) {
            throw new LogicException(sprintf('Context menu "%s" needs for() and items.', $this->id));
        }

        return Html::tag('div', array_merge($this->rootAttributes(['caelrik-contextmenu']), ['hidden' => true]), '');
    }
}
