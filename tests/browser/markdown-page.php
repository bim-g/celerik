<?php

/**
 * A form with Markdown fields, included by server.php (GET /markdown?mode=…). The page has a
 * strict Content Security Policy.
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$notes = "## Release notes\n\nCelerik **0.6** adds a [Markdown field](https://example.com/docs).\n\n- Tabs\n- A toolbar\n\n"
    . "<img src=x alt=\"\" onerror=\"document.title='hacked'\"> <script>document.title='hacked'</script>";
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:">
    <link rel="icon" href="data:,">
    <title>Markdown (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Markdown</h1>
    <?= Form::make('post')->action('/markdown')->add(
        Field::markdown('body', 'Body')->required()->help('Markdown: **bold**, _italic_, [links](https://…).'),
        Field::markdown('notes', 'Release notes')->value($notes),
    )->submit('Publish') ?>
</main>
</body>
</html>
