<?php

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;

$code = <<<'PHP'
<?php

// Greets someone by name.
function greet(string $name): string
{
    return "Hello, {$name}!";
}

echo greet('Ada');
PHP;

return Form::make('snippet')
    ->action('/snippets')
    ->add(
        Field::code('source', 'PHP source')->language('php')->value($code),
        Field::code('settings', 'Settings')->language('json')->tabIndents()->value("{\n    \"theme\": \"dark\"\n}"),
    )
    ->submit('Save');
