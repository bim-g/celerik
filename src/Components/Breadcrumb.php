<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;

/**
 * Where the page sits: Home / Members / Ada Lovelace. A navigation landmark with an ordered
 * list; the last item is the current page (aria-current="page") and is not a link.
 *
 *     Breadcrumb::make()->item('Home', '/')->item('Members', '/members')->item('Ada Lovelace');
 */
final class Breadcrumb extends Component
{
    private string $label = 'Breadcrumb';

    /** @var list<array{label: string, url: ?string}> */
    private array $items = [];

    public static function name(): string
    {
        return 'breadcrumb';
    }

    /**
     * The landmark's name; 'Breadcrumb' by default.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A step of the trail. The last one is the current page: its URL is not linked.
     */
    public function item(string $label, ?string $url = null): self
    {
        $this->items[] = ['label' => $label, 'url' => $url];

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $items = '';
        $last = count($this->items) - 1;
        foreach ($this->items as $index => $item) {
            $text = Html::escape($item['label']);
            if ($index === $last) {
                $items .= Html::tag('li', ['class' => 'breadcrumb-item active', 'aria-current' => 'page'], $text);
                continue;
            }
            $items .= Html::tag('li', ['class' => 'breadcrumb-item'], $item['url'] === null
                ? $text
                : Html::tag('a', ['href' => $item['url']], $text));
        }

        return Html::tag(
            'nav',
            array_merge($this->rootAttributes(['caelrik-breadcrumb']), ['aria-label' => $this->label]),
            Html::tag('ol', ['class' => 'breadcrumb mb-0'], $items),
        );
    }
}
