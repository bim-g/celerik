<?php

declare(strict_types=1);

namespace App\Twig;

use Caelrik\Ui\Theme\Theme;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The theme saved with the theme builder (CaelrikThemeController): config/caelrik-theme.json
 * and its stylesheet public/css/caelrik-theme.css.
 *
 *     {% set saved = caelrik_saved_theme() %}
 *     {{ caelrik_assets(asset('bundles/caelrik'), customTheme: saved.url ?? null) }}
 */
final class CaelrikThemeExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('caelrik_saved_theme', $this->forTemplate(...))];
    }

    public function saved(): ?Theme
    {
        $file = $this->projectDir . '/config/caelrik-theme.json';

        return is_file($file) ? Theme::fromJson((string) file_get_contents($file)) : null;
    }

    public function save(Theme $theme): void
    {
        file_put_contents($this->projectDir . '/config/caelrik-theme.json', $theme->toJson());
        if (!is_dir($this->projectDir . '/public/css')) {
            mkdir($this->projectDir . '/public/css', 0755, true);
        }
        file_put_contents($this->projectDir . '/public/css/caelrik-theme.css', $theme->toCss());
    }

    /** @return array{url: string}|null */
    public function forTemplate(): ?array
    {
        return $this->saved() === null ? null : ['url' => '/css/caelrik-theme.css'];
    }
}
