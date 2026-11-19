<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Support\Html;

/**
 * The items of a menu (MenuButton, SplitButton): links, actions and dividers. PHP writes a
 * plain list that works without JavaScript; menu.js makes it an ARIA menu.
 */
trait HasMenuItems
{
    /** @var list<array{type: string, label?: string, url?: string, disabled?: bool, action?: Action}> */
    private array $items = [];

    /**
     * A link. A disabled one is shown, and cannot be chosen.
     */
    public function link(string $label, string $url, bool $disabled = false): static
    {
        $this->items[] = ['type' => 'link', 'label' => $label, 'url' => $url, 'disabled' => $disabled];

        return $this;
    }

    /**
     * An action: Action::link() or Action::request() (with its confirmation, or a form).
     */
    public function action(Action $action): static
    {
        $this->items[] = ['type' => 'action', 'action' => $action];

        return $this;
    }

    public function divider(): static
    {
        $this->items[] = ['type' => 'divider'];

        return $this;
    }

    private function renderItems(string $id): string
    {
        $html = '';
        $modals = '';
        foreach ($this->items as $item) {
            if ($item['type'] === 'divider') {
                $html .= Html::tag('li', ['class' => 'celerik-menu-divider'], '');
                continue;
            }
            if ($item['type'] === 'action') {
                $html .= Html::tag('li', [], $item['action']->render(modal: false));
                $modals .= $item['action']->renderModal();
                continue;
            }
            $label = Html::escape($item['label']);
            $html .= Html::tag('li', [], $item['disabled'] ?? false
                ? Html::tag('span', ['class' => 'celerik-menu-item', 'data-disabled' => true], $label)
                : Html::tag('a', ['class' => 'celerik-menu-item', 'href' => $item['url']], $label));
        }

        return Html::tag('ul', ['class' => 'celerik-menu-list list-unstyled', 'id' => $id], $html) . $modals;
    }
}
