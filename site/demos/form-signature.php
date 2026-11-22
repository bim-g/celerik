<?php

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;

return Form::make('contract')
    ->action('/contracts/42/sign')
    ->add(
        Field::signature('signature', 'Your signature')
            ->required()
            ->help('It is stored with the contract.'),
    )
    ->submit('Sign the contract');
