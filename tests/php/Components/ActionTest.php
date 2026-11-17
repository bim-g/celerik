<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Data\ArrayDataSource;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ActionTest extends TestCase
{
    public function testALinkFillsItsUrlWithEncodedRowValues(): void
    {
        $html = Action::link('view', 'View', '/users/{id}/{team.slug}')
            ->ariaLabel('View {name}')
            ->render(['id' => 7, 'name' => 'Ada <L>', 'team' => ['slug' => 'a/b c']]);

        self::assertSame(
            '<a href="/users/7/a%2Fb%20c" class="btn celerik-action-button btn-outline-secondary" aria-label="View Ada &lt;L&gt;">View</a>',
            $html,
        );
    }

    public function testARowValueCannotTurnALinkIntoAScript(): void
    {
        $html = Action::link('open', 'Open', '{url}')->render(['url' => 'javascript:alert(1)']);

        self::assertStringContainsString('href="javascript%3Aalert%281%29"', $html);
    }

    public function testARequestIsAFormThatPostsWithoutJavaScript(): void
    {
        $html = (string) Action::request('delete', 'Delete', '/users/{id}')
            ->method('delete')
            ->variant('danger')
            ->solid()
            ->confirm('Delete {name}?', 'This cannot be undone.')
            ->csrf('t0ken', field: 'csrf', header: 'X-CSRF')
            ->labels(['cancel' => 'Annuler']);

        self::assertSame(
            '<form method="post" action="/users/" class="celerik-action" data-celerik-action="delete" data-method="DELETE"'
            . ' data-confirm="Delete ?" data-confirm-detail="This cannot be undone." data-csrf-header="X-CSRF"'
            . ' data-labels="{&quot;cancel&quot;:&quot;Annuler&quot;,&quot;failed&quot;:&quot;The action failed. Try again.&quot;}">'
            . '<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="csrf" value="t0ken">'
            . '<button type="submit" class="btn celerik-action-button btn-danger">Delete</button></form>',
            $html,
        );
    }

    public function testAPostNeedsNoMethodField(): void
    {
        $html = Action::request('archive', 'Archive', '/archive')->render(small: true);

        self::assertStringNotContainsString('_method', $html);
        self::assertStringNotContainsString('data-confirm', $html);
        self::assertStringContainsString('data-method="POST"', $html);
        self::assertStringContainsString('class="btn celerik-action-button btn-outline-secondary btn-sm"', $html);
    }

    public function testFieldsAndOptionsForJavaScript(): void
    {
        $action = Action::request('delete', 'Delete', '/users/{id}')->confirm('Delete {name}?')->csrf('t');

        self::assertSame(['id', 'name'], $action->fields());
        self::assertSame([
            'name' => 'delete',
            'label' => 'Delete',
            'url' => '/users/{id}',
            'request' => true,
            'method' => 'POST',
            'className' => 'btn celerik-action-button btn-outline-secondary',
            'confirm' => 'Delete {name}?',
            'csrf' => ['token' => 't', 'field' => '_token', 'header' => 'X-CSRF-TOKEN'],
        ], $action->toArray());
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidActions(): iterable
    {
        yield 'name' => [static fn () => Action::link('1 bad', 'X', '/')];
        yield 'method on a link' => [static fn () => Action::link('a', 'X', '/')->method('POST')];
        yield 'unknown method' => [static fn () => Action::request('a', 'X', '/')->method('GET')];
        yield 'variant' => [static fn () => Action::link('a', 'X', '/')->variant('dark')];
        yield 'confirm on a link' => [static fn () => Action::link('a', 'X', '/')->confirm('Sure?')];
        yield 'label' => [static fn () => Action::request('a', 'X', '/')->labels(['ok' => 'OK'])];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidActions')]
    public function testRejectsInvalidActions(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);

        $build();
    }

    public function testAGridRendersAnActionsColumnNamedByTheRowTitle(): void
    {
        $grid = Grid::make('people')
            ->column('id', null, static fn (Column $c) => $c->hidden())
            ->column('name')
            ->actions(
                Action::link('edit', 'Edit', '/people/{id}/edit'),
                Action::request('delete', 'Delete', '/people/{id}')->method('DELETE')->ariaLabel('Remove {name}'),
            )
            ->labels(['actions' => 'Actions rapides'])
            ->rows([['id' => 3, 'name' => 'Grace']]);
        $html = $grid->render();

        self::assertStringContainsString('<th scope="col" class="celerik-grid-actions">Actions rapides</th>', $html);
        self::assertStringContainsString(
            '<td class="celerik-grid-actions"><div class="celerik-grid-action-list"><a href="/people/3/edit"'
            . ' class="btn celerik-action-button btn-outline-secondary btn-sm" aria-label="Edit Grace">Edit</a>',
            $html,
        );
        self::assertStringContainsString('aria-label="Remove Grace">Delete</button>', $html);

        $options = $grid->toArray();
        self::assertSame(['edit', 'delete'], array_column($options['actions'], 'name'));
        self::assertSame('Actions rapides', $options['actionsTitle']);
        self::assertSame('name', $options['rowTitle']);
        self::assertArrayNotHasKey('actions', Grid::make()->column('name')->toArray());
    }

    public function testARowTitleCanBeChosen(): void
    {
        $html = Grid::make()->column('id')->column('email')
            ->rowTitle('email')
            ->actions(Action::link('view', 'View', '/u/{id}'))
            ->rows([['id' => 1, 'email' => 'ada@example.com']])
            ->render();

        self::assertStringContainsString('aria-label="View ada@example.com"', $html);
    }

    public function testActionsOnlyReadDeclaredColumns(): void
    {
        $grid = Grid::make('people')->column('name')->actions(Action::link('edit', 'Edit', '/people/{id}/edit'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Action "edit" of grid "people" reads {id}: add a column for it (it can be hidden).');

        $grid->render();
    }

    public function testRespondReturnsTheHiddenFieldsActionsNeed(): void
    {
        $grid = Grid::make()
            ->url('/people')
            ->column('id', null, static fn (Column $c) => $c->hidden())
            ->column('name')
            ->actions(Action::link('edit', 'Edit', '/people/{id}/edit'));

        $result = $grid->respond(new ArrayDataSource([['id' => 9, 'name' => 'Ada', 'password' => 'x']]), []);

        self::assertSame([['id' => 9, 'name' => 'Ada']], $result->rows);
    }
}
