<?php

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\MenuButton;
use Caelrik\Ui\Components\SplitButton;
use Caelrik\Ui\Components\Toolbar;

// The demo answers requests in the browser: nothing is saved.
return Toolbar::make('member-tools')
    ->label('Member')
    ->add(Action::link('edit', 'Edit', '#edit')->variant('primary')->solid())
    ->separator()
    ->add(
        MenuButton::make('export')->label('Export')
            ->link('CSV', '#csv')
            ->link('Excel', '#excel')
            ->link('PDF (coming soon)', '#pdf', disabled: true)
            ->divider()
            ->action(Action::request('email', 'E-mail me the list', '/demo-api/members/email')),
        SplitButton::make('save')
            ->main(Action::request('save', 'Save', '/demo-api/members/42'))
            ->link('Save as draft', '#draft')
            ->action(Action::request('archive', 'Save and archive', '/demo-api/members/42/archive')
                ->confirm('Archive this member?')),
    );
