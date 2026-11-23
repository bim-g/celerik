<?php

use Caelrik\Ui\Components\TaskBoard;

// The demo answers moves in the browser: nothing is saved.
return TaskBoard::make('release-board')
    ->label('Release board')
    ->columns([
        'todo' => 'To do',
        'doing' => ['label' => 'In progress', 'limit' => 3],
        'review' => 'In review',
        'done' => 'Done',
    ])
    ->cards([
        ['id' => 1, 'status' => 'todo', 'title' => 'Write the release notes', 'owner' => 'Ada', 'due' => 'Dec 4'],
        ['id' => 2, 'status' => 'todo', 'title' => 'Translate the labels', 'owner' => 'Grace', 'due' => 'Dec 6'],
        ['id' => 3, 'status' => 'doing', 'title' => 'Keyboard moves', 'owner' => 'Alan', 'due' => 'Dec 2'],
        ['id' => 4, 'status' => 'doing', 'title' => 'Dark mode contrast', 'owner' => 'Ada', 'due' => 'Dec 3'],
        ['id' => 5, 'status' => 'review', 'title' => 'Column limits', 'owner' => 'Grace', 'due' => 'Nov 28'],
        ['id' => 6, 'status' => 'done', 'title' => 'Card markup', 'owner' => 'Alan', 'due' => 'Nov 21'],
    ])
    ->meta('owner', 'due')
    ->moveUrl('/demo-api/tasks/{id}/move');
