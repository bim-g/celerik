<?php

/**
 * Media for the media player's tests and demo: a soft tone as a WAV file and its captions as
 * WebVTT.
 *
 *     php tests/browser/sample-media.php site/public/media
 */

declare(strict_types=1);

/** A mono 8-bit WAV of $seconds: a quiet 440 Hz tone that fades in and out. */
function celerik_sample_wav(float $seconds = 6.0, int $rate = 8000): string
{
    $count = (int) round($seconds * $rate);
    $samples = '';
    for ($i = 0; $i < $count; $i++) {
        $fade = min(1.0, $i / ($rate * 0.2), ($count - $i) / ($rate * 0.2));
        $samples .= chr(128 + (int) round(20 * $fade * sin(2 * M_PI * 440 * $i / $rate)));
    }

    return 'RIFF' . pack('V', 36 + $count) . 'WAVE'
        . 'fmt ' . pack('VvvVVvv', 16, 1, 1, $rate, $rate, 1, 8)
        . 'data' . pack('V', $count) . $samples;
}

function celerik_sample_vtt(): string
{
    return "WEBVTT\n\n"
        . "00:00:00.000 --> 00:00:02.000\nA soft tone starts.\n\n"
        . "00:00:02.000 --> 00:00:04.000\nThe tone goes on.\n\n"
        . "00:00:04.000 --> 00:00:06.000\nThe tone fades out.\n";
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $folder = rtrim($argv[1] ?? '.', '/');
    file_put_contents($folder . '/tone.wav', celerik_sample_wav());
    file_put_contents($folder . '/tone.en.vtt', celerik_sample_vtt());
    echo "Wrote $folder/tone.wav and $folder/tone.en.vtt\n";
}
