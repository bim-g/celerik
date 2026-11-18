<?php

use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;

return Grid::make('expenses')
    ->caption('Expenses by team')
    ->column('team', 'Team', static fn (Column $column) => $column->hidden())
    ->column('item', 'Item')
    ->column('date', 'Date')
    ->column('amount', 'Amount', static fn (Column $column) => $column->total()->className('text-end'))
    ->column('nights', 'Nights', static fn (Column $column) => $column->total('avg', 1)->className('text-end'))
    ->groupBy('team')
    ->pageSize(10)
    ->rows([
        ['team' => 'Sales', 'item' => 'Flights', 'date' => '2026-10-02', 'amount' => 1240.5, 'nights' => 4],
        ['team' => 'Design', 'item' => 'Font licences', 'date' => '2026-10-05', 'amount' => 380, 'nights' => null],
        ['team' => 'Sales', 'item' => 'Hotel', 'date' => '2026-10-06', 'amount' => 860, 'nights' => 4],
        ['team' => 'Engineering', 'item' => 'Tickets', 'date' => '2026-10-09', 'amount' => 1500, 'nights' => 2],
        ['team' => 'Design', 'item' => 'Usability study', 'date' => '2026-10-12', 'amount' => 2200, 'nights' => 1],
        ['team' => 'Engineering', 'item' => 'Laptops', 'date' => '2026-10-15', 'amount' => 4800, 'nights' => null],
        ['team' => 'Sales', 'item' => 'Client dinner', 'date' => '2026-10-20', 'amount' => 145.75, 'nights' => null],
    ]);
