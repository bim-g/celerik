<?php

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

return Form::make('article')
    ->action('/articles')
    ->add(
        Field::text('title')->required(),
        Field::editor('body', 'Article')
            ->required()
            ->help('Headings, lists and links are allowed.')
            ->value('<h2>Hello</h2><p>Edit <strong>this</strong> text.</p>'),
    )
    ->submit('Publish');
