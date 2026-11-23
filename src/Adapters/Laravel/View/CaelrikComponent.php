<?php

declare(strict_types=1);

namespace Caelrik\Ui\Adapters\Laravel\View;

use Caelrik\Ui\Component as UiComponent;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * <x-caelrik :component="$grid" />
 */
final class CaelrikComponent extends Component
{
    public function __construct(public UiComponent $component)
    {
    }

    public function render(): HtmlString
    {
        return new HtmlString($this->component->render());
    }
}
