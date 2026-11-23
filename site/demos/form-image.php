<?php

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

return Form::make('profile')
    ->action('/profile/picture')
    ->add(
        Field::file('picture', 'Profile picture')
            ->required()
            ->imageEditor(['1:1', 'free'], 512)
            ->help('Choose a picture from your device: it is edited here, and sent at most 512 pixels wide.'),
    )
    ->submit('Save');
