<?php

use Caelrik\Ui\Components\TreeView;

// A checkable tree: its checkboxes post the checked ids with the form around it.
return TreeView::make('shared-folders')
    ->label('Folders to share')
    ->checkable('folders')
    ->nodes([
        ['id' => 1, 'label' => 'Documents', 'expanded' => true, 'children' => [
            ['id' => 2, 'label' => 'Invoices', 'checked' => true],
            ['id' => 3, 'label' => 'Contracts', 'children' => [
                ['id' => 4, 'label' => 'Signed'],
                ['id' => 5, 'label' => 'Drafts'],
            ]],
            ['id' => 6, 'label' => 'Reports'],
        ]],
        ['id' => 7, 'label' => 'Pictures', 'children' => [
            ['id' => 8, 'label' => 'Holidays'],
            ['id' => 9, 'label' => 'Family'],
        ]],
        ['id' => 10, 'label' => 'Archive (read only)', 'disabled' => true],
    ]);
