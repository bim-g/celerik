<?php

declare(strict_types=1);

namespace Celerik\Ui\Data;

use RuntimeException;

/**
 * A move a task board refuses (TaskBoard::cardMove(), or your own rules, e.g. a full
 * column): answer it with a 422 and toArray(); the card goes back and the message is read.
 */
final class InvalidCardMove extends RuntimeException
{
    /**
     * @return array{message: string}
     */
    public function toArray(): array
    {
        return ['message' => $this->getMessage()];
    }
}
