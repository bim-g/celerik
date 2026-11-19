<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Avatar;
use Celerik\Ui\Components\Badge;
use Celerik\Ui\Components\Chip;
use Celerik\Ui\Components\Loader;
use Celerik\Ui\Components\Popover;
use Celerik\Ui\Components\Progress;
use Celerik\Ui\Components\Skeleton;
use Celerik\Ui\Components\Tooltip;
use Celerik\Ui\Support\Html;
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
            '#^<span class="celerik-avatar celerik-avatar-md text-bg-\w+" role="img" aria-label="Ada  Byron Lovelace, busy">'
            . '<span class="celerik-avatar-initials" aria-hidden="true">AL</span>'
            . '<span class="celerik-avatar-status celerik-avatar-status-busy" aria-hidden="true"></span></span>$#',
            $initials,
        );
        // The same name always gets the same color.
        self::assertSame($initials, Avatar::make()->person('Ada  Byron Lovelace')->status('busy')->render());

        self::assertSame(
            '<span class="celerik-avatar celerik-avatar-xl celerik-avatar-square" role="img" aria-label="Acme">'
            . '<img src="/logo.png" alt="" class="celerik-avatar-image" loading="lazy"></span>',
            Avatar::make()->person('Acme')->image('/logo.png')->size('xl')->square()->render(),
        );
        self::assertStringContainsString('>P</span>', Avatar::make()->person('plato')->image('javascript:x')->render());
        self::assertStringContainsString(
            'aria-hidden="true"><span class="celerik-avatar-initials"',
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
            '<span class="celerik-chip"><span class="celerik-chip-label">PHP</span></span>',
            Chip::make()->label('PHP')->render(),
        );

        $removable = Chip::make('c1')->label('Go <lang>')->removable('skills[]', 'go')->render();
        self::assertStringContainsString('data-celerik="chip"', $removable);
        self::assertStringContainsString('<input type="hidden" name="skills[]" value="go">', $removable);
        self::assertStringContainsString(
            '<button type="button" class="celerik-chip-remove" aria-label="Remove Go &lt;lang&gt;"><span aria-hidden="true">×</span></button>',
            $removable,
        );
        self::assertStringNotContainsString('<input', Chip::make()->label('A')->removable()->render());

        self::assertSame(
            '<span class="celerik-chip-check"><input type="checkbox" class="btn-check" id="remote" name="remote" value="1" '
            . 'autocomplete="off"><label class="celerik-chip" for="remote"><span class="celerik-chip-label">Remote</span>'
            . '</label></span>',
            Chip::make('remote')->label('Remote')->checkbox('remote', '1')->render(),
        );
        self::assertStringContainsString(' checked ', Chip::make('r')->label('R')->checkbox('r', '1', true)->render());
        self::assertStringContainsString(
            '<span class="celerik-avatar celerik-avatar-sm',
            Chip::make()->label('Ada')->avatar(Avatar::make()->person('Ada'))->render(),
        );
    }

    public function testATooltipDescribesItsTrigger(): void
    {
        $html = Tooltip::make('tip')->text('Saves a draft')
            ->trigger(Html::raw('<button type="button">Save</button>'))->placement('bottom')->render();
        self::assertStringContainsString(
            '<span id="tip" class="celerik-tooltip-wrap" data-celerik="tooltip" '
            . 'data-celerik-options="{&quot;placement&quot;:&quot;bottom&quot;}"><button type="button">Save</button>'
            . '<span class="celerik-tooltip" id="tip-tip" role="tooltip" hidden>Saves a draft</span></span>',
            $html,
        );
        // Plain text becomes focusable, so keyboards reach the tooltip.
        self::assertStringContainsString(
            '<span class="celerik-tooltip-text" tabindex="0">API &lt;key&gt;</span>',
            Tooltip::make()->text('A secret')->trigger('API <key>')->render(),
        );
    }

    public function testAPopoverIsADisclosure(): void
    {
        $html = Popover::make('help')->button('?', 'About pricing')->variant('link')->align('end')
            ->title('Pricing')->content('Taxes <included>.')->render();
        self::assertStringContainsString(
            '<details id="help" class="celerik-popover celerik-popover-end" data-celerik="popover" data-celerik-options="{}">'
            . '<summary class="btn btn-sm celerik-action-button btn-link" aria-label="About pricing">?</summary>'
            . '<div class="celerik-popover-panel" role="group" aria-labelledby="help-title">'
            . '<p class="celerik-popover-title" id="help-title">Pricing</p>'
            . '<div class="celerik-popover-body">Taxes &lt;included&gt;.</div></div></details>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="celerik-popover-panel"><div',
            Popover::make('p')->button('More')->content('x')->render(),
        );
    }

    public function testAProgressBarIsANativeProgress(): void
    {
        self::assertSame(
            '<div class="celerik-progress celerik-progress-success celerik-progress-lg" id="up">'
            . '<div class="celerik-progress-text"><span class="celerik-progress-label" id="up-label">Upload</span>'
            . '<span class="celerik-progress-value" aria-hidden="true">25%</span></div>'
            . '<progress class="celerik-progress-bar" id="up-bar" max="8" value="2" aria-labelledby="up-label">25%</progress>'
            . '</div>',
            Progress::make('up')->label('Upload')->value(2)->max(8)->showValue()->color('success')->size('lg')->render(),
        );
        $indeterminate = Progress::make('s')->label('Syncing', visible: false)->render();
        self::assertStringContainsString(
            '<progress class="celerik-progress-bar" id="s-bar" max="100" aria-label="Syncing"></progress>',
            $indeterminate,
        );
        self::assertStringNotContainsString('style=', $indeterminate);
        self::assertStringContainsString('value="100"', Progress::make()->label('Over')->value(250)->render());
    }

    public function testAProgressCircleIsAnSvgProgressbar(): void
    {
        $html = Progress::make('p')->label('Profile')->value(3)->max(4)->circle()->showValue()->render();
        self::assertStringContainsString(
            '<div class="celerik-progress-dial" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="3" '
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
            '<div class="celerik-loader" role="status"><span class="spinner-grow spinner-grow-sm text-success" '
            . 'aria-hidden="true"></span><span class="celerik-loader-label">Loading orders</span></div>',
            Loader::make()->label('Loading orders')->showLabel()->grow()->size('sm')->color('success')->render(),
        );
        self::assertStringContainsString('<span class="visually-hidden">Loading…</span>', Loader::make()->render());

        $skeleton = Skeleton::make()->label('Loading profile')->image()->avatar()->heading()->lines(2)->render();
        self::assertStringStartsWith(
            '<div class="celerik-skeleton placeholder-glow" role="status"><span class="visually-hidden">Loading profile</span>'
            . '<span class="celerik-skeleton-shapes" aria-hidden="true"><span class="placeholder celerik-skeleton-image"></span>',
            $skeleton,
        );
        self::assertStringContainsString('<span class="placeholder col-12"></span><span class="placeholder col-5"></span>', $skeleton);
        self::assertStringNotContainsString('placeholder-glow', Skeleton::make()->still()->render());
    }

    public function testRequiredParts(): void
    {
        foreach ([
            static fn () => Avatar::make()->render(),
            static fn () => Chip::make()->render(),
            static fn () => Tooltip::make()->trigger('x')->render(),
            static fn () => Popover::make()->content('x')->render(),
            static fn () => Progress::make()->value(1)->render(),
        ] as $render) {
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
