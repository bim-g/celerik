<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\TaskBoard;
use Caelrik\Ui\Data\InvalidCardMove;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class TaskBoardTest extends TestCase
{
    private function board(): TaskBoard
    {
        return TaskBoard::make('sprint')
            ->label('Sprint')
            ->columns(['todo' => 'To do', 'doing' => ['label' => 'In progress', 'limit' => 2], 'done' => 'Done'])
            ->cards([
                ['id' => 1, 'status' => 'todo', 'title' => 'Write <docs>', 'owner' => 'Ada', 'summary' => 'Guide'],
                ['id' => 'a/b', 'status' => 'done', 'title' => 'Plan', 'owner' => null],
                ['id' => 9, 'status' => 'archived', 'title' => 'Old'],
            ])
            ->description('summary')
            ->meta('owner')
            ->link('/tasks/{id}')
            ->moveUrl('/tasks/{id}/move')
            ->csrf('t0k3n');
    }

    public function testRendersColumnsAsNamedSectionsWithCountsAndCards(): void
    {
        $html = $this->board()->render();

        self::assertStringContainsString('class="caelrik-board" data-caelrik="taskboard"', $html);
        self::assertStringContainsString('role="region" aria-label="Sprint"', $html);
        self::assertStringContainsString(
            '<section class="caelrik-board-column" aria-labelledby="sprint-column-doing" data-column="doing">'
            . '<h2 class="caelrik-board-column-title" id="sprint-column-doing"><span>In progress</span> ',
            $html,
        );
        self::assertStringContainsString('aria-hidden="true">0/2</span><span class="visually-hidden">0 of 2 cards</span>', $html);
        self::assertStringContainsString('<span class="visually-hidden">1 card</span>', $html);
        self::assertStringContainsString(
            '<li class="caelrik-board-card" data-id="1" tabindex="0" aria-describedby="sprint-help">'
            . '<h3 class="caelrik-board-card-title"><a href="/tasks/1">Write &lt;docs&gt;</a></h3>'
            . '<p class="caelrik-board-card-description">Guide</p><p class="caelrik-board-card-meta"><span>Ada</span></p>',
            $html,
        );
        self::assertStringNotContainsString('Old', $html, 'A card whose column is not on the board is left out.');
    }

    public function testEachCardHasAMoveFormThatPostsWithoutJavaScript(): void
    {
        $html = $this->board()->render();

        self::assertStringContainsString(
            '<form class="caelrik-board-move" method="post" action="/tasks/a%2Fb/move">'
            . '<input type="hidden" name="_method" value="PATCH"><input type="hidden" name="_token" value="t0k3n">'
            . '<label class="visually-hidden" for="sprint-move-2">Move Plan to</label>',
            $html,
        );
        self::assertStringContainsString('<option value="done" selected>Done</option>', $html);
        self::assertStringContainsString('<button type="submit" class="btn btn-outline-secondary caelrik-action-button">Move</button>', $html);
    }

    public function testAReadOnlyBoardHasNoMoveForms(): void
    {
        $html = TaskBoard::make('b')->columns(['a' => 'A'])->cards([['id' => 1, 'status' => 'a', 'title' => 'T']])->render();

        self::assertStringNotContainsString('<form', $html);
        self::assertStringNotContainsString('aria-describedby', $html);
        self::assertArrayNotHasKey('moveUrl', TaskBoard::make('b')->columns(['a' => 'A'])->toArray());
    }

    public function testCardMoveReadsAColumnOfTheBoardAndAPosition(): void
    {
        $board = $this->board();

        $move = $board->cardMove(['column' => 'done', 'position' => '3']);
        self::assertSame(['done', 3], [$move->column, $move->position]);
        self::assertNull($board->cardMove(['column' => 'todo'])->position);

        $refused = [
            ['column' => 'archived'],
            [],
            ['column' => ['todo']],
            ['column' => 'todo', 'position' => '-1'],
            ['column' => 'todo', 'position' => '1.5'],
            ['column' => 'todo', 'position' => ['0']],
        ];
        foreach ($refused as $input) {
            try {
                $board->cardMove($input);
                self::fail('A bad move was accepted: ' . json_encode($input));
            } catch (InvalidCardMove $refusal) {
                self::assertSame(['message' => 'Choose a column of the board.'], $refusal->toArray());
            }
        }
    }

    public function testOptionsForTheBrowser(): void
    {
        $options = $this->board()->labels(['move' => 'Déplacer'])->toArray();

        self::assertSame([
            ['key' => 'todo', 'label' => 'To do'],
            ['key' => 'doing', 'label' => 'In progress', 'limit' => 2],
            ['key' => 'done', 'label' => 'Done'],
        ], $options['columns']);
        self::assertSame('PATCH', $options['method']);
        self::assertSame('t0k3n', $options['csrf']['token']);
        self::assertSame('Déplacer', $options['labels']['move']);
    }

    public function testRejectsBadConfiguration(): void
    {
        $cases = [
            static fn () => TaskBoard::make('b')->columns(['bad key' => 'X']),
            static fn () => TaskBoard::make('b')->columns(['a' => ['label' => 'A', 'limit' => 0]]),
            static fn () => TaskBoard::make('b')->cards([], column: 'bad field'),
            static fn () => TaskBoard::make('b')->moveUrl('/x', 'DELETE'),
            static fn () => TaskBoard::make('b')->headingLevel(6),
            static fn () => TaskBoard::make('b')->labels(['nope' => 'x']),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(LogicException::class);
        TaskBoard::make('b')->render();
    }
}
