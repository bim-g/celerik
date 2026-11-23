<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\Grid\Editor;
use Caelrik\Ui\Data\InvalidCellEdit;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class GridInlineEditTest extends TestCase
{
    private function grid(): Grid
    {
        return Grid::make('members')
            ->column('id', 'ID', static fn (Column $c) => $c->hidden())
            ->column('name', 'Name', static fn (Column $c) => $c->editable(Editor::text(10)->required()))
            ->column('role', 'Role', static fn (Column $c) => $c->editable(Editor::select([1 => 'Engineer', 'dir' => 'Director'])))
            ->column('remote', 'Remote', static fn (Column $c) => $c->editable(Editor::boolean()))
            ->column('salary', 'Salary', static fn (Column $c) => $c->editable(Editor::number(0, 1000)))
            ->column('joined', 'Joined', static fn (Column $c) => $c->editable(Editor::date()))
            ->column('email')
            ->inlineEdit('/members/{id}')
            ->csrf('t0k3n');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function refusal(array $input): InvalidCellEdit
    {
        try {
            $this->grid()->cellEdit($input);
        } catch (InvalidCellEdit $refusal) {
            return $refusal;
        }
        self::fail('The edit was accepted.');
    }

    public function testRendersEditableCellsWithTheirValueAndANamedButton(): void
    {
        $html = $this->grid()->rows([[
            'id' => 7, 'name' => 'Ada <b>', 'role' => 1, 'remote' => false, 'salary' => 900,
            'joined' => new \DateTimeImmutable('2024-03-12 10:00'), 'email' => 'ada@example.com',
        ]])->render();

        self::assertStringContainsString(
            '<td class="caelrik-grid-editable" data-value="Ada &lt;b&gt;"><span class="caelrik-grid-value">Ada &lt;b&gt;'
            . '</span><button type="button" class="btn btn-link btn-sm caelrik-grid-edit" aria-label="Edit Name of Ada'
            . ' &lt;b&gt;" data-caelrik-edit="name"><svg',
            $html,
        );
        self::assertStringContainsString('data-value="1"><span class="caelrik-grid-value">Engineer</span>', $html);
        self::assertStringContainsString('data-value="0"><span class="caelrik-grid-value">No</span>', $html);
        self::assertStringContainsString('data-value="2024-03-12">', $html);
        self::assertStringContainsString('<td>ada@example.com</td>', $html);
    }

    public function testSendsTheEditorsTheEndpointAndTheLabels(): void
    {
        $options = $this->grid()->labels(['edit' => 'Modifier {column}'])->toArray();

        self::assertSame(['type' => 'text', 'required' => true, 'maxLength' => 10], $options['columns'][1]['editor']);
        self::assertSame([['1', 'Engineer'], ['dir', 'Director']], $options['columns'][2]['editor']['options']);
        self::assertSame(['type' => 'number', 'min' => 0, 'max' => 1000], $options['columns'][4]['editor']);
        self::assertArrayNotHasKey('editor', $options['columns'][6]);
        self::assertSame('/members/{id}', $options['edit']['url']);
        self::assertSame('PATCH', $options['edit']['method']);
        self::assertSame(['token' => 't0k3n', 'field' => '_token', 'header' => 'X-CSRF-TOKEN'], $options['edit']['csrf']);
        self::assertSame('Modifier {column}', $options['edit']['labels']['edit']);
        self::assertSame('name', $options['rowTitle']);
    }

    public function testCellEditReadsTypedValues(): void
    {
        $grid = $this->grid();

        self::assertSame('Ada', $grid->cellEdit(['field' => 'name', 'value' => ' Ada '])->value);
        self::assertSame('1', $grid->cellEdit(['field' => 'role', 'value' => '1'])->value);
        self::assertTrue($grid->cellEdit(['field' => 'remote', 'value' => '1'])->value);
        self::assertFalse($grid->cellEdit(['field' => 'remote', 'value' => 'false'])->value);
        self::assertSame(12.5, $grid->cellEdit(['field' => 'salary', 'value' => '12.5'])->value);
        self::assertSame(12, $grid->cellEdit(['field' => 'salary', 'value' => '12'])->value);
        self::assertNull($grid->cellEdit(['field' => 'salary', 'value' => ''])->value);
        self::assertSame('2026-02-28', $grid->cellEdit(['field' => 'joined', 'value' => '2026-02-28'])->value);

        $edit = $grid->cellEdit(['field' => 'name', 'value' => 'Ada']);
        self::assertSame(['value' => 'Ada'], $edit->answer());
        self::assertSame(['value' => 'ADA', 'message' => 'Saved.'], $edit->answer('ADA', 'Saved.'));
    }

    public function testCellEditRefusesValuesTheEditorDoesNotAllow(): void
    {
        self::assertSame('Enter a value.', $this->refusal(['field' => 'name', 'value' => '  '])->getMessage());
        self::assertSame('Use 10 characters at most.', $this->refusal(['field' => 'name', 'value' => str_repeat('a', 11)])->getMessage());
        self::assertSame('Enter a valid value.', $this->refusal(['field' => 'role', 'value' => '01'])->getMessage());
        self::assertSame('Enter a valid value.', $this->refusal(['field' => 'remote', 'value' => 'yes'])->getMessage());
        self::assertSame('Enter 0 or more.', $this->refusal(['field' => 'salary', 'value' => '-1'])->getMessage());
        self::assertSame('Enter 1000 or less.', $this->refusal(['field' => 'salary', 'value' => '1001'])->getMessage());
        self::assertSame('Enter a valid value.', $this->refusal(['field' => 'salary', 'value' => '1e'])->getMessage());
        self::assertSame('Enter a valid value.', $this->refusal(['field' => 'joined', 'value' => '2026-02-30'])->getMessage());
        self::assertSame('Enter a valid value.', $this->refusal(['field' => 'name', 'value' => ['a']])->getMessage());

        self::assertSame(
            ['message' => 'Enter a value.', 'errors' => ['name' => ['Enter a value.']]],
            $this->refusal(['field' => 'name', 'value' => ''])->toArray(),
        );
    }

    public function testOnlyEditableColumnsCanBeChanged(): void
    {
        self::assertSame('email', $this->refusal(['field' => 'email', 'value' => 'x@example.com'])->field);
        self::assertSame('id', $this->refusal(['field' => 'id', 'value' => '1'])->field);
        self::assertSame('value', $this->refusal(['field' => 'password; drop', 'value' => 'x'])->field);
        self::assertSame('value', $this->refusal([])->field);
    }

    public function testEditableColumnsNeedAnEndpointThatReadsColumns(): void
    {
        $grid = Grid::make('members')->column('name', 'Name', static fn (Column $c) => $c->editable(Editor::text()));
        try {
            $grid->render();
            self::fail('No endpoint was accepted.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('call inlineEdit()', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('reads {id}');
        $grid->inlineEdit('/members/{id}')->toArray();
    }

    public function testRejectsBadEditorsAndMethods(): void
    {
        $cases = [
            static fn () => Editor::select([]),
            static fn () => Editor::number(5, 1),
            static fn () => Editor::number(step: 0),
            static fn () => Editor::text(0),
            static fn () => Grid::make('m')->inlineEdit('/m', 'DELETE'),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
