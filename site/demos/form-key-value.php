<?php

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

return Form::make('webhook')
    ->action('/webhooks')
    ->add(
        Field::text('url', 'Endpoint URL')->required(),
        Field::keyValue('headers', 'Request headers')
            ->value(['Content-Type' => 'application/json'])
            ->keySuggestions(['Accept', 'Authorization', 'Content-Type', 'User-Agent'])
            ->keyValueLabels(key: 'Header', add: 'Add header')
            ->help('Sent with every call. A header is used once.'),
    )
    ->submit('Save webhook');
