<?php

declare(strict_types=1);

namespace Caelrik\Ui\Data;

use RuntimeException;

/**
 * A value a grid cell cannot take (Grid::cellEdit()): answer it with a 422 and toArray(), and
 * the grid shows the message in the cell.
 */
final class InvalidCellEdit extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    /**
     * The answer the grid reads: {"message": "...", "errors": {"field": ["..."]}}, as Laravel
     * answers a failed validation.
     *
     * @return array{message: string, errors: array<string, list<string>>}
     */
    public function toArray(): array
    {
        return ['message' => $this->getMessage(), 'errors' => [$this->field => [$this->getMessage()]]];
    }
}
