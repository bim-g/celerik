<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\AdminLayout;
use Caelrik\Ui\Components\Breadcrumb;
use Caelrik\Ui\Components\Navigation;
use Caelrik\Ui\Components\UserMenu;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AdminLayoutTest extends TestCase
{
    private function navigation(?string $current): Navigation
    {
        return Navigation::make('main')->label('Main')
            ->item('Dashboard', '/')
            ->group('Members', static fn (Navigation $group) => $group
                ->item('All members', '/members')
                ->item('Invitations', '/members/invitations', badge: '3'))
            ->item('Settings', '/settings?tab=general')
            ->current($current);
    }

    public function testBreadcrumbEndsWithTheCurrentPage(): void
    {
        $html = Breadcrumb::make('crumbs')->label('You are here')
            ->item('Home', '/')->item('Members <all>', '/members')->item('Ada', '/members/1')
            ->render();

        self::assertStringContainsString('<nav id="crumbs" class="caelrik-breadcrumb" data-caelrik="breadcrumb"', $html);
        self::assertStringContainsString('aria-label="You are here"', $html);
        self::assertStringContainsString('<li class="breadcrumb-item"><a href="/members">Members &lt;all&gt;</a></li>', $html);
        self::assertStringContainsString('<li class="breadcrumb-item active" aria-current="page">Ada</li>', $html);
    }

    public function testNavigationMarksTheCurrentPageOrItsSection(): void
    {
        $exact = $this->navigation('/members/invitations?sort=name')->render();
        self::assertStringContainsString(
            '<a class="caelrik-nav-link active" href="/members/invitations" aria-current="page"><span>Invitations</span> '
            . '<span class="badge rounded-pill text-bg-secondary caelrik-nav-badge">3</span></a>',
            $exact,
        );
        self::assertSame(1, substr_count($exact, 'aria-current'));
        self::assertStringContainsString('<details class="caelrik-nav-group" open><summary class="caelrik-nav-link">Members</summary>', $exact);

        // A page under a link is in its section; the longest matching link wins.
        $section = $this->navigation('/members/42/edit')->render();
        self::assertStringContainsString('href="/members" aria-current="true"', $section);
        self::assertStringNotContainsString('aria-current="page"', $section);

        // Settings' own query is ignored; the dashboard is only the dashboard.
        self::assertStringContainsString('href="/settings?tab=general" aria-current="page"', $this->navigation('/settings')->render());
        $home = $this->navigation('/reports')->render();
        self::assertStringNotContainsString('aria-current', $home);
        self::assertStringContainsString('<details class="caelrik-nav-group"><summary', $home);
    }

    public function testUserMenuIsADisclosureWithTheUsersLinksAndActions(): void
    {
        $html = UserMenu::make('account')->user('Ada Lovelace', 'ada@example.com', 'javascript:alert(1)')
            ->link('Profile', '/profile')
            ->divider()
            ->action(Action::request('logout', 'Sign out', '/logout')->csrf('t0k3n'))
            ->render();

        self::assertStringContainsString('<details id="account" class="caelrik-usermenu" data-caelrik="usermenu"', $html);
        self::assertStringContainsString(
            '<summary class="btn caelrik-usermenu-toggle"><span class="caelrik-usermenu-avatar caelrik-usermenu-initials" '
            . 'aria-hidden="true">AL</span><span class="caelrik-usermenu-name">Ada Lovelace</span></summary>',
            $html,
        );
        self::assertStringNotContainsString('javascript', $html);
        self::assertStringContainsString('<p class="caelrik-usermenu-detail">ada@example.com</p>', $html);
        self::assertStringContainsString('<a class="caelrik-usermenu-item" href="/profile">Profile</a>', $html);
        self::assertStringContainsString('action="/logout"', $html);
        self::assertStringNotContainsString('role="menu"', $html);

        $picture = UserMenu::make('a')->user('Grace', avatar: '/avatars/grace.png')->render();
        self::assertStringContainsString('<img class="caelrik-usermenu-avatar" src="/avatars/grace.png" alt="">', $picture);
    }

    public function testLayoutHasASkipLinkAnAppBarASidebarAndAMainLandmark(): void
    {
        $html = AdminLayout::make('app')
            ->brand('Acme <Inc>', '/')
            ->navigation($this->navigation('/'))
            ->userMenu(UserMenu::make('account')->user('Ada Lovelace'))
            ->breadcrumb(Breadcrumb::make()->item('Home'))
            ->title('Dashboard')
            ->appBar(Html::raw('<a class="btn" href="/help">Help</a>'), 'Plain <text>')
            ->content(Html::raw('<p>Hello.</p>'))
            ->render();

        self::assertStringStartsWith(
            '<a class="visually-hidden-focusable caelrik-skip-link" href="#app-main">Skip to main content</a>',
            $html,
        );
        self::assertStringContainsString('<a class="navbar-brand" href="/">Acme &lt;Inc&gt;</a>', $html);
        self::assertStringContainsString(
            'data-bs-toggle="offcanvas" data-bs-target="#app-sidebar" aria-controls="app-sidebar" aria-label="Open navigation"',
            $html,
        );
        self::assertStringContainsString('<aside class="caelrik-admin-sidebar offcanvas-lg offcanvas-start" id="app-sidebar"', $html);
        self::assertStringContainsString('<a class="btn" href="/help">Help</a>Plain &lt;text&gt;', $html);
        self::assertMatchesRegularExpression(
            '#<main class="caelrik-admin-main" id="app-main" tabindex="-1"><nav [^>]*caelrik-breadcrumb.*</nav>'
            . '<h1 class="caelrik-admin-title h3">Dashboard</h1><p>Hello.</p></main>#',
            $html,
        );
    }

    public function testALayoutWithoutNavigationHasNoMenuButton(): void
    {
        $html = AdminLayout::make('app')->brand('Acme')->content('Text <b>')->render();

        self::assertStringNotContainsString('offcanvas', $html);
        self::assertStringContainsString('<span class="navbar-brand mb-0 h1 fs-5">Acme</span>', $html);
        self::assertStringContainsString('Text &lt;b&gt;</main>', $html);
    }

    public function testRejectsBadConfiguration(): void
    {
        try {
            AdminLayout::make('app')->labels(['nope' => 'x']);
            self::fail('An unknown label was accepted.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        try {
            Navigation::make('n')->render();
            self::fail('An empty navigation was rendered.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        UserMenu::make('u')->render();
    }
}
