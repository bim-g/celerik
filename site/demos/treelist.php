<?php

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\TreeList;

// The demo answers requests in the browser: nothing is saved.
return TreeList::make('roadmap')
    ->caption('Release plan')
    ->column('name', 'Task')
    ->column('owner', 'Owner')
    ->column('due', 'Due')
    ->column('hours', 'Hours', static fn (Column $column) => $column->className('text-end'))
    ->expanded(1)
    ->actions(Action::request('done', 'Mark done', '/demo-api/tasks/{id}/done'))
    ->rows([
        ['id' => 1, 'parent_id' => null, 'name' => 'Design', 'owner' => 'Ada', 'due' => '2026-12-04', 'hours' => 24],
        ['id' => 2, 'parent_id' => 1, 'name' => 'Wireframes', 'owner' => 'Ada', 'due' => '2026-11-27', 'hours' => 10],
        ['id' => 3, 'parent_id' => 2, 'name' => 'Mobile', 'owner' => 'Grace', 'due' => '2026-11-25', 'hours' => 6],
        ['id' => 4, 'parent_id' => 1, 'name' => 'User tests', 'owner' => 'Alan', 'due' => '2026-12-04', 'hours' => 14],
        ['id' => 5, 'parent_id' => null, 'name' => 'Build', 'owner' => 'Grace', 'due' => '2026-12-18', 'hours' => 60],
        ['id' => 6, 'parent_id' => 5, 'name' => 'API', 'owner' => 'Alan', 'due' => '2026-12-11', 'hours' => 35],
        ['id' => 7, 'parent_id' => 5, 'name' => 'Front end', 'owner' => 'Grace', 'due' => '2026-12-18', 'hours' => 25],
        ['id' => 8, 'parent_id' => null, 'name' => 'Launch', 'owner' => 'Ada', 'due' => '2026-12-21', 'hours' => 4],
    ]);
