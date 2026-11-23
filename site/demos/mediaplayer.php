<?php

use Caelrik\Ui\Components\MediaPlayer;
use Caelrik\Ui\Support\Html;

$transcript = [
    [0, 'A soft tone starts.', 'Narrator'],
    [2, 'The tone goes on.'],
    [4, 'The tone fades out.'],
];

return [
    Html::raw('<div class="row g-4"><div class="col-md-7">'),
    MediaPlayer::make('lesson')
        ->label('A lesson in tones')
        ->source('../media/tone.wav', 'audio/wav')
        ->poster('../media/poster.svg')
        ->captions('../media/tone.en.vtt', 'en', 'English', default: true)
        ->transcript($transcript),
    Html::raw('</div><div class="col-md-5">'),
    MediaPlayer::make('podcast')
        ->audio()
        ->label('The tone podcast, episode 1')
        ->source('../media/tone.wav', 'audio/wav')
        ->transcript($transcript),
    Html::raw('</div></div>'),
];
