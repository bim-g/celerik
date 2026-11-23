<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Avatar;
use Caelrik\Ui\Components\Badge;
use Caelrik\Ui\Components\Chip;
use Caelrik\Ui\Components\Loader;
use Caelrik\Ui\Components\Popover;
use Caelrik\Ui\Components\Progress;
use Caelrik\Ui\Components\Skeleton;
use Caelrik\Ui\Components\Tooltip;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DisplayElementsTest extends TestCase
{
    public function testABadgeIsPlainHtmlWithAHiddenText(): void
    {
        self::assertSame(
            '<span class="badge text-bg-danger rounded-pill extra">3<span class="visually-hidden"> unread messages</span></span>',
            Badge::make()->text(3)->color('danger')->pill()->hiddenText('unread messages')->addClass('extra')->render(),
        );
        self::assertSame('<span class="badge text-bg-secondary">&lt;b&gt;</span>', Badge::make()->text('<b>')->render());
    }

    public function testAnAvatarIsAnImageNamedAfterItsPerson(): void
    {
        $initials = Avatar::make()->person('Ada  Byron Lovelace')->status('busy')->render();
        self::assertMatchesRegularExpression(
            '#^<span class="caelrik-avatar caelrik-avatar-md text-bg-\w+" role="img" aria-label="Ada  Byron Lovelace, busy">'
            . '<span class="caelrik-avatar-initials" aria-hidden="true">AL</span>'
            . '<span class="caelrik-avatar-status caelrik-avatar-status-busy" aria-hidden="true"></span></span>$#',
            $initials,
        );
        // The same name always gets the same color.
        self::assertSame($initials, Avatar::make()->person('Ada  Byron Lovelace')->status('busy')->render());

        self::assertSame(
            '<span class="caelrik-avatar caelrik-avatar-xl caelrik-avatar-square" role="img" aria-label="Acme">'
            . '<img src="/logo.png" alt="" class="caelrik-avatar-image" loading="lazy"></span>',
            Avatar::make()->person('Acme')->image('/logo.png')->size('xl')->square()->render(),
        );
        self::assertStringContainsString('>P</span>', Avatar::make()->person('plato')->image('javascript:x')->render());
        self::assertStringContainsString(
            'aria-hidden="true"><span class="caelrik-avatar-initials"',
            Avatar::make()->person('Ada')->decorative()->render(),
        );
        self::assertStringNotContainsString('role="img"', Avatar::make()->person('Ada')->decorative()->render());
        self::assertStringContainsString(
            'aria-label="Ada, en ligne"',
            Avatar::make()->person('Ada')->status('online')->labels(['online' => '{name}, en ligne'])->render(),
        );
        self::assertSame('ÉV', Avatar::initials('élise van'));
    }

    public function testChipsArePlainRemovableOrCheckboxes(): void
    {
        self::assertSame(
            '<span class="caelrik-chip"><span class="caelrik-chip-label">PHP</span></span>',
            Chip::make()->label('PHP')->render(),
        );

        $removable = Chip::make('c1')->label('Go <lang>')->removable('skills[]', 'go')->render();
        self::assertStringContainsString('data-caelrik="chip"', $removable);
        self::assertStringContainsString('<input type="hidden" name="skills[]" value="go">', $removable);
        self::assertStringContainsString(
            '<button type="button" class="caelrik-chip-remove" aria-label="Remove Go &lt;lang&gt;"><span aria-hidden="true">×</span></button>',
            $removable,
        );
        self::assertStringNotContainsString('<input', Chip::make()->label('A')->removable()->render());

        self::assertSame(
            '<span class="caelrik-chip-check"><input type="checkbox" class="btn-check" id="remote" name="remote" value="1" '
            . 'autocomplete="off"><label class="caelrik-chip" for="remote"><span class="caelrik-chip-label">Remote</span>'
            . '</label></span>',
            Chip::make('remote')->label('Remote')->checkbox('remote', '1')->render(),
        );
        self::assertStringContainsString(' checked ', Chip::make('r')->label('R')->checkbox('r', '1', true)->render());
        self::assertStringContainsString(
            '<span class="caelrik-avatar caelrik-avatar-sm',
            Chip::make()->label('Ada')->avatar(Avatar::make()->person('Ada'))->render(),
        );
    }

    public function testATooltipDescribesItsTrigger(): void
    {
        $html = Tooltip::make('tip')->text('Saves a draft')
            ->trigger(Html::raw('<button type="button">Save</button>'))->placement('bottom')->render();
        self::assertStringContainsString(
            '<span id="tip" class="caelrik-tooltip-wrap" data-caelrik="tooltip" '
            . 'data-caelrik-options="{&quot;placement&quot;:&quot;bottom&quot;}"><button type="button">Save</button>'
            . '<span class="caelrik-tooltip" id="tip-tip" role="tooltip" hidden>Saves a draft</span></span>',
            $html,
        );
        // Plain text becomes focusable, so keyboards reach the tooltip.
        self::assertStringContainsString(
            '<span class="caelrik-tooltip-text" tabindex="0">API &lt;key&gt;</span>',
            Tooltip::make()->text('A secret')->trigger('API <key>')->render(),
        );
    }

    public function testAPopoverIsADisclosure(): void
    {
        $html = Popover::make('help')->button('?', 'About pricing')->variant('link')->align('end')
            ->title('Pricing')->content('Taxes <included>.')->render();
        self::assertStringContainsString(
            '<details id="help" class="caelrik-popover caelrik-popover-end" data-caelrik="popover" data-caelrik-options="{}">'
            . '<summary class="btn btn-sm caelrik-action-button btn-link" aria-label="About pricing">?</summary>'
            . '<div class="caelrik-popover-panel" role="group" aria-labelledby="help-title">'
            . '<p class="caelrik-popover-title" id="help-title">Pricing</p>'
            . '<div class="caelrik-popover-body">Taxes &lt;included&gt;.</div></div></details>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="caelrik-popover-panel"><div',
            Popover::make('p')->button('More')->content('x')->render(),
        );
    }

    public function testAProgressBarIsANativeProgress(): void
    {
        self::assertSame(
            '<div class="caelrik-progress caelrik-progress-success caelrik-progress-lg" id="up">'
            . '<div class="caelrik-progress-text"><span class="caelrik-progress-label" id="up-label">Upload</span>'
            . '<span class="caelrik-progress-value" aria-hidden="true">25%</span></div>'
            . '<progress class="caelrik-progress-bar" id="up-bar" max="8" value="2" aria-labelledby="up-label">25%</progress>'
            . '</div>',
            Progress::make('up')->label('Upload')->value(2)->max(8)->showValue()->color('success')->size('lg')->render(),
        );
        $indeterminate = Progress::make('s')->label('Syncing', visible: false)->render();
        self::assertStringContainsString(
            '<progress class="caelrik-progress-bar" id="s-bar" max="100" aria-label="Syncing"></progress>',
            $indeterminate,
        );
        self::assertStringNotContainsString('style=', $indeterminate);
        self::assertStringContainsString('value="100"', Progress::make()->label('Over')->value(250)->render());
    }

    public function testAProgressCircleIsAnSvgProgressbar(): void
    {
        $html = Progress::make('p')->label('Profile')->value(3)->max(4)->circle()->showValue()->render();
        self::assertStringContainsString(
            '<div class="caelrik-progress-dial" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="3" '
            . 'aria-valuetext="75%" aria-labelledby="p-label">',
            $html,
        );
        self::assertStringContainsString('stroke-dasharray="75 100" transform="rotate(-90 18 18)"', $html);
        self::assertStringNotContainsString('style=', $html);

        $spinning = Progress::make('q')->label('Working')->circle()->render();
        self::assertStringContainsString('is-indeterminate', $spinning);
        self::assertStringNotContainsString('aria-valuenow', $spinning);
    }

    public function testLoaderAndSkeletonAreNamedStatuses(): void
    {
        self::assertSame(
            '<div class="caelrik-loader" role="status"><span class="spinner-grow spinner-grow-sm text-success" '
            . 'aria-hidden="true"></span><span class="caelrik-loader-label">Loading orders</span></div>',
            Loader::make()->label('Loading orders')->showLabel()->grow()->size('sm')->color('success')->render(),
        );
        self::assertStringContainsString('<span class="visually-hidden">Loading…</span>', Loader::make()->render());

        $skeleton = Skeleton::make()->label('Loading profile')->image()->avatar()->heading()->lines(2)->render();
        self::assertStringStartsWith(
            '<div class="caelrik-skeleton placeholder-glow" role="status"><span class="visually-hidden">Loading profile</span>'
            . '<span class="caelrik-skeleton-shapes" aria-hidden="true"><span class="placeholder caelrik-skeleton-image"></span>',
            $skeleton,
        );
        self::assertStringContainsString('<span class="placeholder col-12"></span><span class="placeholder col-5"></span>', $skeleton);
        self::assertStringNotContainsString('placeholder-glow', Skeleton::make()->still()->render());
    }

    public function testRequiredParts(): void
    {
        $renders = [
            static fn () => Avatar::make()->render(),
            static fn () => Chip::make()->render(),
            static fn () => Tooltip::make()->trigger('x')->render(),
            static fn () => Popover::make()->content('x')->render(),
            static fn () => Progress::make()->value(1)->render(),
        ];
        foreach ($renders as $render) {
            try {
                $render();
                self::fail('A required part was missing.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'badge color' => [static fn () => Badge::make()->color('pink')];
        yield 'avatar size' => [static fn () => Avatar::make()->size('huge')];
        yield 'avatar status' => [static fn () => Avatar::make()->status('asleep')];
        yield 'avatar label' => [static fn () => Avatar::make()->labels(['nope' => 'x'])];
        yield 'chip label' => [static fn () => Chip::make()->labels(['nope' => 'x'])];
        yield 'tooltip placement' => [static fn () => Tooltip::make()->placement('left')];
        yield 'popover variant' => [static fn () => Popover::make()->variant('pink')];
        yield 'popover align' => [static fn () => Popover::make()->align('center')];
        yield 'progress max' => [static fn () => Progress::make()->max(0)];
        yield 'progress color' => [static fn () => Progress::make()->color('light')];
        yield 'progress size' => [static fn () => Progress::make()->size('xl')];
        yield 'loader size' => [static fn () => Loader::make()->size('lg')];
        yield 'loader color' => [static fn () => Loader::make()->color('pink')];
        yield 'skeleton lines' => [static fn () => Skeleton::make()->lines(21)];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
