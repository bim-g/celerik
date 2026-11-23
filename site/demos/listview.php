<?php

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\ListView;

// The demo answers requests in the browser: nothing is saved.
return ListView::make('reading')
    ->label('Reading list')
    ->title('title')
    ->link('?article={id}')
    ->description('summary')
    ->meta('author', 'year')
    ->searchable()
    ->sorts(['-year' => 'Newest first', 'year' => 'Oldest first', 'title' => 'Title'])
    ->pageSize(4)
    ->actions(Action::request('save', 'Save for later', '/demo-api/reading/{id}'))
    ->rows([
        ['id' => 1, 'title' => 'Notes on the Analytical Engine', 'author' => 'Ada Lovelace', 'year' => 1843,
            'summary' => 'The first published program, and what a machine might compose.'],
        ['id' => 2, 'title' => 'On Computable Numbers', 'author' => 'Alan Turing', 'year' => 1936,
            'summary' => 'Which problems a machine can decide, and which it cannot.'],
        ['id' => 3, 'title' => 'A Mathematical Theory of Communication', 'author' => 'Claude Shannon', 'year' => 1948,
            'summary' => 'Information, measured in bits.'],
        ['id' => 4, 'title' => 'The Education of a Computer', 'author' => 'Grace Hopper', 'year' => 1952,
            'summary' => 'Programs that write programs: the compiler.'],
        ['id' => 5, 'title' => 'Go To Statement Considered Harmful', 'author' => 'Edsger Dijkstra', 'year' => 1968,
            'summary' => 'A letter on structured programming.'],
        ['id' => 6, 'title' => 'Programming with Abstract Data Types', 'author' => 'Barbara Liskov', 'year' => 1974,
            'summary' => 'Data and its operations, together.'],
    ]);
