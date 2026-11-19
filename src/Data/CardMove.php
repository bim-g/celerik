<?php

declare(strict_types=1);

namespace Celerik\Ui\Data;

/**
 * A checked move of a task board's card (TaskBoard::cardMove()): the column it goes to, and
 * its position there, from 0 at the top; null puts it last (a move sent without JavaScript).
 */
final class CardMove
{
    public function __construct(
        public readonly string $column,
        public readonly ?int $position,
    ) {
    }
}
