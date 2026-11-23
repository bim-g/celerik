<?php

/**
 * Media players, included by server.php (GET /media?mode=…; the files are /media/tone.wav and
 * /media/tone.en.vtt). The page has a strict Content Security Policy.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\MediaPlayer;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$transcript = [[0, 'A soft tone starts.', 'Narrator'], [2, 'The tone goes on.'], ['0:04', 'The tone fades out.']];
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Content-Security-Policy"
          content="default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; media-src 'self' blob:">
    <link rel="icon" href="data:,">
    <title>Media player (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Media player</h1>
    <div class="row g-4">
        <div class="col-lg-7">
            <?= MediaPlayer::make('lesson')->label('A lesson in tones')->source('/media/tone.wav', 'audio/wav')
                ->captions('/media/tone.en.vtt', 'en', 'English', default: true)->transcript($transcript) ?>
        </div>
        <div class="col-lg-5">
            <?= MediaPlayer::make('podcast')->audio()->label('The tone podcast')->source('/media/tone.wav', 'audio/wav')
                ->transcript($transcript) ?>
        </div>
    </div>
</main>
</body>
</html>
