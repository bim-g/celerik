<?php

declare(strict_types=1);

namespace Caelrik\Ui\Adapters\Symfony;

use Caelrik\Ui\Assets;
use Caelrik\Ui\Component;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * {{ caelrik_assets() }} or {{ caelrik_assets(customTheme: '/css/acme.css') }}
 * — load jQuery and Bootstrap before it.
 * {{ caelrik(grid) }}    — renders any Caelrik component.
 */
final class CaelrikTwigExtension extends AbstractExtension
{
    public function __construct(private readonly string $assetsBaseUrl = '/bundles/caelrik')
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('caelrik', $this->render(...), ['is_safe' => ['html']]),
            new TwigFunction('caelrik_assets', $this->assets(...), ['is_safe' => ['html']]),
        ];
    }

    public function render(Component $component): string
    {
        return $component->render();
    }

    public function assets(
        ?string $baseUrl = null,
        ?string $nonce = null,
        string $theme = 'bootstrap',
        ?string $customTheme = null,
    ): string {
        return Assets::tags($baseUrl ?? $this->assetsBaseUrl, $nonce, $theme, $customTheme);
    }
}
