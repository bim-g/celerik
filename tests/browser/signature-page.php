<?php

/**
 * A form with signature fields, included by server.php (GET /signature?mode=…). Posting it
 * shows what Field::signatureImage() makes of each value.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $describe = static function (mixed $value): array {
        $png = Field::signatureImage($value);
        if ($png === null) {
            return ['text' => $value];
        }
        $size = getimagesizefromstring($png);

        return ['width' => $size[0] ?? null, 'height' => $size[1] ?? null, 'bytes' => strlen($png)];
    };
    echo json_encode([
        'signature' => $describe($_POST['signature'] ?? null),
        'witness' => $describe($_POST['witness'] ?? null),
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
    <link rel="icon" href="data:,">
    <title>Signature (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Signature</h1>
    <?= Form::make('contract')->action('/signature')->add(
        Field::signature('signature', 'Your signature')->required()->help('It is stored with the contract.'),
        Field::signature('witness', 'Witness'),
    )->submit('Sign') ?>
</main>
</body>
</html>
