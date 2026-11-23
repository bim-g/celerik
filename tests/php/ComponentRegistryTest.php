<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests;

use Caelrik\Ui\Assets;
use Caelrik\Ui\ComponentRegistry;
use Caelrik\Ui\Components\Grid;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ComponentRegistryTest extends TestCase
{
    public function testCreatesRegisteredComponents(): void
    {
        $registry = ComponentRegistry::withDefaults();

        self::assertSame(['grid', 'listview', 'treeview', 'treelist', 'taskboard', 'chart', 'form', 'scheduler', 'modal', 'tabs', 'notification', 'filemanager', 'themebuilder', 'adminlayout', 'navigation', 'breadcrumb', 'usermenu', 'menubutton', 'splitbutton', 'toolbar', 'contextmenu', 'accordion', 'card', 'splitter', 'tilelayout', 'recordview', 'stat', 'stats', 'badge', 'avatar', 'chip', 'tooltip', 'popover', 'progress', 'loader', 'skeleton', 'timeline', 'carousel', 'sparkline', 'gauge', 'gantt', 'map', 'orgchart', 'diagram', 'qrcode', 'barcode', 'pdfviewer', 'mediaplayer'], array_keys($registry->all()));
        self::assertInstanceOf(Grid::class, $registry->create('grid', 'orders'));
        self::assertSame('orders', $registry->create('grid', 'orders')->getId());
    }

    public function testRejectsUnknownNamesAndNonComponents(): void
    {
        $registry = new ComponentRegistry();

        try {
            $registry->create('gantt');
            self::fail('Expected an exception for an unknown component.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        /** @phpstan-ignore argument.type */
        $registry->register(stdClass::class);
    }

    public function testRejectsInvalidIds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Grid::make('"><img src=x>');
    }

    public function testAssetTags(): void
    {
        self::assertSame(
            '<link rel="stylesheet" href="/vendor/caelrik/caelrik.css">' . "\n"
            . '<script type="module" src="/vendor/caelrik/caelrik.js"></script>',
            Assets::tags('/vendor/caelrik/'),
        );
    }

    public function testAssetTagsAcceptOnlyTheBootstrapLook(): void
    {
        self::assertSame(['bootstrap'], Assets::THEMES);
        self::assertSame(Assets::tags('/assets'), Assets::tags('/assets', theme: 'bootstrap'));

        foreach (['material', 'tailwind'] as $removed) {
            try {
                Assets::tags('/assets', theme: $removed);
                self::fail(sprintf('The %s look was accepted.', $removed));
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('was removed in Caelrik UI 0.2.0', $exception->getMessage());
            }
        }

        $this->expectException(InvalidArgumentException::class);
        Assets::tags('/assets', theme: '../../evil');
    }

    public function testAssetTagsLoadACustomThemeLastAndEscapeItsUrl(): void
    {
        self::assertSame(
            '<link rel="stylesheet" href="/assets/caelrik.css">' . "\n"
            . '<link rel="stylesheet" href="/css/acme.css?v=2&amp;x=&quot;">' . "\n"
            . '<script type="module" src="/assets/caelrik.js"></script>',
            Assets::tags('/assets', customTheme: '/css/acme.css?v=2&x="'),
        );
    }

    public function testCdnUrlPointsToAReleaseOnJsDelivr(): void
    {
        self::assertSame('https://cdn.jsdelivr.net/npm/@caelrik/ui@0.2.0/public', Assets::cdnUrl('0.2.0'));
        self::assertSame('https://cdn.jsdelivr.net/npm/@caelrik/ui@1.0.0-beta.1/public', Assets::cdnUrl('v1.0.0-beta.1'));
        self::assertStringContainsString(
            '<script type="module" src="https://cdn.jsdelivr.net/npm/@caelrik/ui@0.2.0/public/caelrik.js"></script>',
            Assets::tags(Assets::cdnUrl('0.2.0')),
        );
    }

    public function testCdnUrlRejectsVersionsThatAreNotReleases(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Assets::cdnUrl('latest/../../evil');
    }

    public function testCdnUrlNeedsAVersionForADevelopmentInstall(): void
    {
        // In this repository caelrik/ui is the root package, installed as a branch (dev-…).
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which is not on the CDN');

        Assets::cdnUrl();
    }

    public function testAssetTagsWithNonceIncludeCspNonceMetaForFullCalendar(): void
    {
        self::assertSame(
            '<meta name="csp-nonce" content="abc">' . "\n"
            . '<link rel="stylesheet" href="/vendor/caelrik/caelrik.css">' . "\n"
            . '<script type="module" src="/vendor/caelrik/caelrik.js" nonce="abc"></script>',
            Assets::tags('/vendor/caelrik/', 'abc'),
        );
    }
}
