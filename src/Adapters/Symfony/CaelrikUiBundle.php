<?php

declare(strict_types=1);

namespace Caelrik\Ui\Adapters\Symfony;

use Caelrik\Ui\ComponentRegistry;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Register in config/bundles.php:
 *     Caelrik\Ui\Adapters\Symfony\CaelrikUiBundle::class => ['all' => true],
 *
 * Twig: {{ caelrik_assets() }} and {{ caelrik(grid) }}
 * Assets: php bin/console assets:install (copies public/ to public/bundles/caelrik)
 */
final class CaelrikUiBundle extends AbstractBundle
{
    /**
     * The package root, so that assets:install finds the prebuilt public/ directory.
     */
    public function getPath(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(ComponentRegistry::class)
            ->factory([ComponentRegistry::class, 'withDefaults']);

        $services->set(CaelrikTwigExtension::class)
            ->tag('twig.extension');
    }
}
