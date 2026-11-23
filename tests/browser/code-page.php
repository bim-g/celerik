<?php

/**
 * A form with code fields, included by server.php (GET /code?mode=…). The page has a strict
 * Content Security Policy with a nonce, which CodeMirror's injected stylesheet needs.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$php = "<?php\n\n// Greets someone.\nfunction greet(string \$name): string\n{\n    return \"Hello, {\$name}!\";\n}\n\necho greet('Ada') . 42;\n";
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'self'; script-src 'self' 'nonce-caelrik-test'; style-src 'self' 'nonce-caelrik-test'; img-src 'self' data:">
    <link rel="icon" href="data:,">
    <title>Code (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <?= Assets::tags('/assets', 'caelrik-test') ?>
</head>
<body>
<main class="container py-3">
    <h1>Code</h1>
    <?= Form::make('snippet')->action('/code')->add(
        Field::code('source', 'PHP source')->language('php')->value($php)->help('Any PHP 8 code.'),
        Field::code('config', 'Configuration')->language('json')->required()->tabIndents(),
        Field::text('title', 'Title'),
    )->submit('Save') ?>
</main>
</body>
</html>
