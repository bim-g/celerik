<?php

declare(strict_types=1);

namespace Celerik\Ui\Adapters\Laravel\View;

use Celerik\Ui\Assets;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * <x-celerik-assets /> or <x-celerik-assets custom-theme="/css/acme.css" />
 * — load jQuery and Bootstrap before it. `theme` is deprecated: "bootstrap" is the only
 * look since 0.2.0.
 */
final class AssetsComponent extends Component
{
    public function __construct(
        public ?string $baseUrl = null,
        public ?string $nonce = null,
        public string $theme = 'bootstrap',
        public ?string $customTheme = null,
    ) {
    }

    public function render(): HtmlString
    {
        return new HtmlString(Assets::tags(
            $this->baseUrl ?? asset('vendor/celerik'),
            $this->nonce,
            $this->theme,
            $this->customTheme,
        ));
    }
}
