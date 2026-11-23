<?php

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

return Form::make('verify')
    ->action('/verify')
    ->add(
        Field::otp('code', 'Verification code')
            ->required()
            ->help('Enter the 6 digits we sent to +243 •••• 12. You can paste them.'),
    )
    ->submit('Verify');
