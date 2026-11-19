<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A main action with a menu of others beside it: Save | ▾ Save as draft, Save and close.
 * The main action is any Action; the arrow is a menu button named "More options".
 *
 *     SplitButton::make('save')->main(Action::request('save', 'Save', '/posts/1')->variant('primary')->solid())
 *         ->action(Action::request('draft', 'Save as draft', '/posts/1/draft'))
 *         ->link('Preview', '/posts/1/preview');
 */
final class SplitButton extends Component
{
    use HasMenuItems;

    public const LABELS = ['more' => 'More options'];

    private ?Action $main = null;
    private string $variant = 'secondary';
    private bool $solid = false;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'splitbutton';
    }

    public function main(Action $action): self
    {
        $this->main = $action;

        return $this;
    }

    /**
     * The arrow's color: the main action's, if you set its variant.
     */
    public function variant(string $variant, bool $solid = false): self
    {
        if (!in_array($variant, MenuButton::VARIANTS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown button variant "%s".', $variant));
        }
        $this->variant = $variant;
        $this->solid = $solid;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown split button label "%s".', $key));
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
        if ($this->main === null || $this->items === []) {
            throw new LogicException(sprintf('Split button "%s" needs a main() action and items.', $this->id));
        }
        $menu = $this->id . '-menu';
        $toggle = Html::tag('button', [
            'type' => 'button',
            'class' => Html::classes([
                'btn',
                'btn-' . ($this->solid ? '' : 'outline-') . $this->variant,
                'celerik-action-button',
                'celerik-menu-toggle',
                'dropdown-toggle',
                'dropdown-toggle-split',
            ]),
            'id' => $this->id . '-button',
            'aria-controls' => $menu,
            'aria-label' => $this->labels['more'],
        ], '');

        return Html::tag(
            'div',
            $this->rootAttributes(['celerik-splitbutton', 'btn-group']),
            $this->main->render() . $toggle . $this->renderItems($menu),
        );
    }
}
