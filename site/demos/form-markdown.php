<?php

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;

return Form::make('post')
    ->action('/posts')
    ->add(
        Field::text('title')->required(),
        Field::markdown('body', 'Body')
            ->required()
            ->value("## What's new\n\nCelerik now has a **Markdown** field:\n\n- a toolbar\n- a preview")
            ->help('Markdown: **bold**, _italic_, [links](https://example.com), lists and quotes.'),
    )
    ->submit('Publish');
