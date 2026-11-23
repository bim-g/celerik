<?php

declare(strict_types=1);

namespace Caelrik\Ui\Adapters\Laravel;

use Caelrik\Ui\Adapters\Laravel\View\AssetsComponent;
use Caelrik\Ui\Adapters\Laravel\View\CaelrikComponent;
use Caelrik\Ui\ComponentRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;

/**
 * Auto-discovered through `extra.laravel.providers` in composer.json.
 *
 * Blade: <x-caelrik-assets /> and <x-caelrik :component="$grid" />
 * Assets: php artisan vendor:publish --tag=caelrik-assets
 * elFinder assets (File Manager): php artisan vendor:publish --tag=caelrik-elfinder
 */
final class CaelrikServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ComponentRegistry::class,
            static fn (): ComponentRegistry => ComponentRegistry::withDefaults(),
        );
    }

    public function boot(): void
    {
        $this->publishes([
            dirname(__DIR__, 3) . '/public' => $this->app->publicPath('vendor/caelrik'),
        ], 'caelrik-assets');

        // elFinder's own JS, CSS, images and sounds, when studio-42/elfinder is installed.
        $elfinder = $this->app->basePath('vendor/studio-42/elfinder');
        if (is_dir($elfinder)) {
            $paths = [];
            foreach (['css', 'img', 'js', 'sounds'] as $directory) {
                $paths[$elfinder . '/' . $directory] = $this->app->publicPath('vendor/elfinder/' . $directory);
            }
            $this->publishes($paths, 'caelrik-elfinder');
        }

        $this->callAfterResolving(BladeCompiler::class, static function (BladeCompiler $blade): void {
            $blade->component('caelrik', CaelrikComponent::class);
            $blade->component('caelrik-assets', AssetsComponent::class);
        });
    }
}
