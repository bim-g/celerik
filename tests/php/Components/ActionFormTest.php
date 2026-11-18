<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ActionFormTest extends TestCase
{
    private function edit(): Action
    {
        return Action::request('edit', 'Edit', '/people/{id}')
            ->method('PATCH')
            ->csrf('t0k3n')
            ->form(
                Form::make('person-form')->add(
                    Field::text('name')->required(),
                    Field::select('role', ['admin' => 'Admin', 'user' => 'User']),
                    Field::date('joined'),
                ),
                'Edit {name}',
                'Save',
            );
    }

    public function testTheButtonOpensTheModalWithTheRowsValuesAndTitle(): void
    {
        $html = $this->edit()->render([
            'id' => 7, 'name' => 'Ada "A"', 'role' => 'admin', 'joined' => new \DateTimeImmutable('2024-03-12 09:30'),
            'password' => 'secret',
        ]);

        self::assertStringContainsString('action="/people/7"', $html);
        self::assertStringContainsString('data-celerik-form-modal="person-form-modal"', $html);
        self::assertStringContainsString('data-title="Edit Ada &quot;A&quot;"', $html);
        self::assertStringContainsString(
            'data-values="{&quot;name&quot;:&quot;Ada \&quot;A\&quot;&quot;,&quot;role&quot;:&quot;admin&quot;,'
            . '&quot;joined&quot;:&quot;2024-03-12 09:30:00&quot;}"',
            $html,
        );
        self::assertStringNotContainsString('secret', $html, 'Only the form\'s fields go to the page.');
        self::assertStringContainsString('aria-haspopup="dialog"', $html);
    }

    public function testRenderIncludesTheModalUnlessToldNot(): void
    {
        $html = $this->edit()->render(['id' => 1, 'name' => 'Ada']);

        self::assertSame(1, substr_count($html, 'id="person-form-modal"'));
        self::assertStringContainsString('<h2 class="modal-title fs-5" id="person-form-modal-title">Edit</h2>', $html);
        self::assertStringContainsString('modal-dialog modal-lg', $html);
        self::assertStringContainsString(
            '<button type="submit" form="person-form" data-celerik-form-submit class="btn btn-secondary">Save</button>',
            $html,
        );
        self::assertStringContainsString('data-bs-dismiss="modal">Cancel</button>', $html);
        // The modal's buttons replace the form's own.
        self::assertStringNotContainsString('<button type="submit" class="btn btn-primary">', $html);

        self::assertStringNotContainsString('id="person-form-modal"', $this->edit()->render(['id' => 1], modal: false));
    }

    public function testAGridRendersEachModalOnceAfterTheTable(): void
    {
        $html = Grid::make('people')
            ->column('id', 'ID', static fn (Column $c) => $c->hidden())
            ->column('name')
            ->actions($this->edit())
            ->rows([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Alan']])
            ->render();

        self::assertSame(1, substr_count($html, 'id="person-form-modal"'));
        self::assertSame(2, substr_count($html, 'data-celerik-form-modal="person-form-modal"'));
        self::assertGreaterThan(strpos($html, '</table>'), strpos($html, 'id="person-form-modal"'));
    }

    public function testAServerSideGridGetsTheFormForItsRows(): void
    {
        $action = Grid::make('people')
            ->url('/people/data')
            ->column('id', 'ID', static fn (Column $c) => $c->hidden())
            ->column('name')
            ->actions($this->edit())
            ->toArray()['actions'][0];

        self::assertSame([
            'modal' => 'person-form-modal',
            'title' => 'Edit {name}',
            'fields' => ['name', 'role', 'joined'],
        ], $action['form']);
    }

    public function testTheTitleReadsDeclaredColumns(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('reads {email}');

        Grid::make('people')
            ->column('id')
            ->actions(Action::request('edit', 'Edit', '/people/{id}')->form(Form::make('f'), 'Edit {email}'))
            ->render();
    }

    public function testOnlyRequestsOpenForms(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Action::link('edit', 'Edit', '/people/1/edit')->form(Form::make('f'));
    }

    public function testModalSizes(): void
    {
        $small = Action::request('note', 'Add a note', '/notes')->form(Form::make('note'), size: 'sm');
        self::assertStringContainsString('modal-dialog modal-sm', $small->render());

        $this->expectException(InvalidArgumentException::class);
        Action::request('note', 'Add a note', '/notes')->form(Form::make('note2'), size: 'huge');
    }
}
