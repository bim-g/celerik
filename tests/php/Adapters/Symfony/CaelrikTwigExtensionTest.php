<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Adapters\Symfony;

use Caelrik\Ui\Adapters\Symfony\CaelrikTwigExtension;
use Caelrik\Ui\Components\Grid;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class CaelrikTwigExtensionTest extends TestCase
{
    public function testRendersComponentsAndAssetsWithoutDoubleEscaping(): void
    {
        $twig = new Environment(new ArrayLoader([
            'page' => '{{ caelrik_assets() }}{{ caelrik(grid) }}',
        ]), ['autoescape' => 'html']);
        $twig->addExtension(new CaelrikTwigExtension());

        $html = $twig->render('page', ['grid' => Grid::make('users')->column('name')->rows([['name' => '<i>Ada</i>']])]);

        self::assertStringContainsString('<script type="module" src="/bundles/caelrik/caelrik.js"></script>', $html);
        self::assertStringContainsString('<table id="users"', $html);
        self::assertStringContainsString('<td>&lt;i&gt;Ada&lt;/i&gt;</td>', $html);
    }

    public function testAssetsFunctionAcceptsACustomTheme(): void
    {
        $twig = new Environment(new ArrayLoader([
            'page' => "{{ caelrik_assets(customTheme: '/css/acme.css') }}",
        ]));
        $twig->addExtension(new CaelrikTwigExtension());

        self::assertStringContainsString(
            'caelrik.css">' . "\n" . '<link rel="stylesheet" href="/css/acme.css">',
            $twig->render('page'),
        );
    }
}
