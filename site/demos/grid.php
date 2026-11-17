<?php

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Filter;

return Grid::make('users')
    ->caption('Team members')
    ->column('name', 'Name', static fn (Column $column) => $column->filter(Filter::text()))
    ->column('role', 'Role', static fn (Column $column) => $column->filter(Filter::select([
        'Engineer' => 'Engineer',
        'Researcher' => 'Researcher',
        'Director' => 'Director',
    ])))
    ->column('remote', 'Remote', static fn (Column $column) => $column->filter(Filter::boolean()))
    ->column('joined', 'Joined', static fn (Column $column) => $column->filter(Filter::dateRange()))
    ->actions(
        Action::link('profile', 'Profile', '?member={name}'),
        // The demo answers requests in the browser: nothing is saved.
        Action::request('remove', 'Remove', '/demo-api/members/{name}')
            ->method('DELETE')
            ->variant('danger')
            ->confirm('Remove {name}?', 'They lose access to the team.'),
    )
    ->export('copy', 'csv')
    ->pageSize(5)
    ->rows([
        ['name' => 'Ada Lovelace', 'role' => 'Engineer', 'remote' => true, 'joined' => '2024-03-12'],
        ['name' => 'Alan Turing', 'role' => 'Researcher', 'remote' => false, 'joined' => '2023-11-02'],
        ['name' => 'Grace Hopper', 'role' => 'Director', 'remote' => false, 'joined' => '2022-06-20'],
        ['name' => 'Katherine Johnson', 'role' => 'Researcher', 'remote' => true, 'joined' => '2025-01-15'],
        ['name' => 'Linus Torvalds', 'role' => 'Engineer', 'remote' => true, 'joined' => '2024-09-30'],
        ['name' => 'Margaret Hamilton', 'role' => 'Director', 'remote' => false, 'joined' => '2021-04-08'],
        ['name' => 'Tim Berners-Lee', 'role' => 'Engineer', 'remote' => true, 'joined' => '2025-07-01'],
    ]);
