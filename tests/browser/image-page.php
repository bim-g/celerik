<?php

/**
 * A form with an image editor, included by server.php (GET /image?mode=…). Posting it returns
 * the size and type of the picture received. The page has a strict Content Security Policy.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $file = $_FILES['photo'] ?? null;
    $size = $file !== null && $file['error'] === UPLOAD_ERR_OK ? getimagesize($file['tmp_name']) : false;
    echo json_encode($size === false ? ['error' => true] : [
        'name' => $file['name'],
        'type' => $size['mime'],
        'width' => $size[0],
        'height' => $size[1],
    ]);
    exit;
}

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:">
    <link rel="icon" href="data:,">
    <title>Image editor (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Image editor</h1>
    <?= Form::make('profile')->action('/image')->add(
        Field::file('photo', 'Photo')->required()->imageEditor(['free', '1:1', '16:9'], 200),
    )->submit('Save') ?>
</main>
</body>
</html>
