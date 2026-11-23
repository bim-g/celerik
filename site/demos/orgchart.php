<?php

use Caelrik\Ui\Components\OrgChart;

// Rows as they come from a database: an id, the manager's id, a name and a job title.
$people = [
    ['id' => 1, 'parent_id' => null, 'name' => 'Grace Hopper', 'title' => 'Chief executive'],
    ['id' => 2, 'parent_id' => 1, 'name' => 'Ada Lovelace', 'title' => 'Chief technology officer'],
    ['id' => 3, 'parent_id' => 1, 'name' => 'Katherine Johnson', 'title' => 'Chief financial officer'],
    ['id' => 4, 'parent_id' => 1, 'name' => 'Margaret Hamilton', 'title' => 'Head of operations'],
    ['id' => 5, 'parent_id' => 2, 'name' => 'Alan Turing', 'title' => 'Lead engineer'],
    ['id' => 6, 'parent_id' => 2, 'name' => 'Barbara Liskov', 'title' => 'Engineer'],
    ['id' => 7, 'parent_id' => 3, 'name' => 'Dorothy Vaughan', 'title' => 'Accountant'],
    ['id' => 8, 'parent_id' => 4, 'name' => 'Mary Jackson', 'title' => 'Office manager'],
];

return [
    OrgChart::make('company')->label('Company')->fromRows($people)->collapsible(),
];
