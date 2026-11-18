<?php

declare(strict_types=1);

namespace Celerik\Ui\Data;

/**
 * A checked change to one grid cell (Grid::cellEdit()): the column's field and its new value,
 * typed as its editor reads it.
 */
final class CellEdit
{
    public function __construct(
        public readonly string $field,
        public readonly string|int|float|bool|null $value,
    ) {
    }

    /**
     * The answer the grid reads after a save: {"value": ...}. Pass the value as saved when the
     * application changed it (rounded, normalised), and a message to show in a toast.
     *
     * @return array{value: mixed, message?: string}
     */
    public function answer(mixed $saved = null, ?string $message = null): array
    {
        $answer = ['value' => func_num_args() > 0 ? $saved : $this->value];
        if ($message !== null) {
            $answer['message'] = $message;
        }

        return $answer;
    }
}
