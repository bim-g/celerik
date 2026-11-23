<?php

use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\Grid\Editor;

// The demo answers edits in the browser: nothing is saved.
return Grid::make('crew')
    ->caption('Crew')
    ->column('id', 'ID', static fn (Column $column) => $column->hidden())
    ->column('name', 'Name', static fn (Column $column) => $column->editable(Editor::text(60)->required()))
    ->column('role', 'Role', static fn (Column $column) => $column->editable(Editor::select([
        'engineer' => 'Engineer',
        'researcher' => 'Researcher',
        'director' => 'Director',
    ])->required()))
    ->column('remote', 'Remote', static fn (Column $column) => $column->editable(Editor::boolean()))
    ->column('hours', 'Hours a week', static fn (Column $column) => $column
        ->editable(Editor::number(0, 60))
        ->total())
    ->column('since', 'Since', static fn (Column $column) => $column->editable(Editor::date()))
    ->inlineEdit('/demo-api/crew/{id}')
    ->pageSize(5)
    ->rows([
        [
            'id' => 1, 'name' => 'Ada Lovelace', 'role' => 'engineer', 'remote' => true, 'hours' => 32,
            'since' => '2024-03-12',
        ],
        ['id' => 2, 'name' => 'Alan Turing', 'role' => 'researcher', 'remote' => false, 'hours' => 40, 'since' => null],
        ['id' => 3, 'name' => 'Grace Hopper', 'role' => 'director', 'remote' => false, 'hours' => 38, 'since' => null],
        ['id' => 4, 'name' => 'Mary Jackson', 'role' => 'researcher', 'remote' => true, 'hours' => 24, 'since' => null],
    ]);
