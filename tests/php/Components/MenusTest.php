<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\ContextMenu;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\MenuButton;
use Caelrik\Ui\Components\Navigation;
use Caelrik\Ui\Components\SplitButton;
use Caelrik\Ui\Components\Toolbar;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MenusTest extends TestCase
{
    public function testMenuButtonIsAButtonAndAPlainListOfItems(): void
    {
        $html = MenuButton::make('export')->label('Export <all>')->variant('primary', solid: true)->small()->align('end')
            ->link('CSV', '/members.csv')
            ->link('PDF', '/members.pdf', disabled: true)
            ->divider()
            ->action(Action::request('email', 'E-mail me', '/members/email')->csrf('t0k3n'))
            ->render();

        self::assertStringContainsString('<div id="export" class="caelrik-menubutton caelrik-menu-end" data-caelrik="menubutton"', $html);
        self::assertStringContainsString(
            '<button type="button" class="btn btn-primary btn-sm caelrik-action-button caelrik-menu-toggle dropdown-toggle" '
            . 'id="export-button" '
            . 'aria-controls="export-menu">Export &lt;all&gt;</button>',
            $html,
        );
        self::assertStringContainsString('<ul class="caelrik-menu-list list-unstyled" id="export-menu">', $html);
        self::assertStringContainsString('<li><a class="caelrik-menu-item" href="/members.csv">CSV</a></li>', $html);
        self::assertStringContainsString('<span class="caelrik-menu-item" data-disabled>PDF</span>', $html);
        self::assertStringContainsString('<li class="caelrik-menu-divider"></li>', $html);
        self::assertStringContainsString('action="/members/email"', $html);
        // Roles come with JavaScript: without it, this is a list, not a menu that cannot be used.
        self::assertStringNotContainsString('role="menu', $html);
    }

    public function testAnActionWithAFormHasItsModalAfterTheMenu(): void
    {
        $html = MenuButton::make('m')->label('More')
            ->action(Action::request('rename', 'Rename', '/x')->form(Form::make('rename-form')))
            ->render();

        self::assertSame(1, substr_count($html, 'id="rename-form-modal"'));
        self::assertGreaterThan(strpos($html, '</ul>'), strpos($html, 'id="rename-form-modal"'));
    }

    public function testSplitButtonHasAMainActionAndAMoreOptionsMenu(): void
    {
        $html = SplitButton::make('save')
            ->main(Action::request('save', 'Save', '/posts/1')->variant('primary')->solid())
            ->variant('primary', solid: true)
            ->labels(['more' => 'Plus d\'options'])
            ->link('Preview', '/posts/1/preview')
            ->render();

        self::assertStringContainsString('class="caelrik-splitbutton btn-group" data-caelrik="splitbutton"', $html);
        self::assertStringContainsString('action="/posts/1"', $html);
        self::assertStringContainsString('dropdown-toggle-split" id="save-button" aria-controls="save-menu" aria-label="Plus d&apos;options"', $html);
    }

    public function testToolbarIsANamedRowOfControlsWithSeparators(): void
    {
        $html = Toolbar::make('tools')->label('Members')
            ->add(Action::link('new', 'New', '/new'), Html::raw('<button type="button" class="btn">Raw</button>'), 'Text <b>')
            ->separator()
            ->render();

        self::assertStringContainsString('class="caelrik-toolbar" data-caelrik="toolbar" data-caelrik-options="{}" role="toolbar" aria-label="Members"', $html);
        self::assertStringContainsString('<div class="caelrik-toolbar-item"><button type="button" class="btn">Raw</button></div>', $html);
        self::assertStringContainsString('<div class="caelrik-toolbar-item">Text &lt;b&gt;</div>', $html);
        self::assertStringContainsString('<span class="caelrik-toolbar-separator" aria-hidden="true"></span>', $html);
    }

    public function testContextMenuSendsItsItemsToTheBrowser(): void
    {
        $menu = ContextMenu::make('files-menu')->for('#files [data-id]')->label('File actions')
            ->link('Open', '/files/{id}')
            ->divider()
            ->action(Action::request('delete', 'Delete', '/files/{id}')->method('DELETE'));

        self::assertStringStartsWith('<div id="files-menu" class="caelrik-contextmenu" data-caelrik="contextmenu"', $menu->render());
        self::assertStringContainsString(' hidden></div>', $menu->render());
        $options = $menu->toArray();
        self::assertSame('#files [data-id]', $options['target']);
        self::assertSame(['type' => 'link', 'label' => 'Open', 'url' => '/files/{id}'], $options['items'][0]);
        self::assertSame('DELETE', $options['items'][2]['action']['method']);
    }

    public function testHorizontalNavigationKeepsItsGroupsClosed(): void
    {
        $nav = Navigation::make('site')->label('Site')->horizontal()
            ->group('Products', static fn (Navigation $group) => $group->item('Grid', '/grid'))
            ->current('/grid');

        $html = $nav->render();
        self::assertStringContainsString('class="caelrik-nav caelrik-nav-horizontal"', $html);
        self::assertStringContainsString('<details class="caelrik-nav-group"><summary', $html);
        self::assertStringContainsString('aria-current="page"', $html);
        self::assertSame(['horizontal' => true], $nav->toArray());
    }

    public function testRejectsIncompleteMenus(): void
    {
        $cases = [
            static fn () => MenuButton::make('m')->label('Empty')->render(),
            static fn () => MenuButton::make('m')->link('A', '/a')->render(),
            static fn () => SplitButton::make('s')->link('A', '/a')->render(),
            static fn () => Toolbar::make('t')->render(),
            static fn () => ContextMenu::make('c')->link('A', '/a')->render(),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('An incomplete menu was rendered.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }

        $invalid = [
            static fn () => MenuButton::make('m')->variant('pink'),
            static fn () => MenuButton::make('m')->align('center'),
            static fn () => ContextMenu::make('c')->for(' '),
            static fn () => ContextMenu::make('c')->action(Action::request('a', 'A', '/a')->form(Form::make('f'))),
            static fn () => SplitButton::make('s')->labels(['nope' => 'x']),
        ];
        foreach ($invalid as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
