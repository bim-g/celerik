<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\MediaPlayer;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class MediaPlayerTest extends TestCase
{
    public function testAVideoWithSourcesTracksAndAFallback(): void
    {
        $html = MediaPlayer::make('intro')->label('Getting <started>')
            ->source('/media/intro.webm', 'video/webm')->source('/media/intro.mp4?v=2&q=hd', 'video/mp4')
            ->poster('/media/intro.jpg')
            ->captions('/media/intro.en.vtt', 'en', 'English')
            ->captions('/media/intro.fr.vtt', 'fr', 'Français', default: true)
            ->subtitles('/media/intro.ln.vtt', 'ln', 'Lingála')
            ->descriptions('/media/intro.desc.vtt', 'en-GB', 'English descriptions')
            ->render();

        self::assertSame(
            '<figure id="intro" class="caelrik-media caelrik-media-video" data-caelrik="mediaplayer" data-caelrik-options="{'
            . '&quot;title&quot;:&quot;Getting &lt;started&gt;&quot;,&quot;labels&quot;:{&quot;playFrom&quot;:&quot;Play from {time}&quot;},'
            . '&quot;options&quot;:[]}"><div class="caelrik-media-frame">'
            . '<video class="caelrik-media-element" controls preload="metadata" playsinline poster="/media/intro.jpg" '
            . 'aria-label="Getting &lt;started&gt;">'
            . '<source src="/media/intro.webm" type="video/webm"><source src="/media/intro.mp4?v=2&amp;q=hd" type="video/mp4">'
            . '<track kind="captions" src="/media/intro.en.vtt" srclang="en" label="English">'
            . '<track kind="captions" src="/media/intro.fr.vtt" srclang="fr" label="Français" default>'
            . '<track kind="subtitles" src="/media/intro.ln.vtt" srclang="ln" label="Lingála">'
            . '<track kind="descriptions" src="/media/intro.desc.vtt" srclang="en-GB" label="English descriptions">'
            . '<p>Your browser cannot play this media. <a href="/media/intro.webm" download>Download it</a></p></video></div>'
            . '<figcaption class="caelrik-media-caption">Getting &lt;started&gt;</figcaption></figure>',
            $html,
        );
    }

    public function testOnlyOneDefaultTrack(): void
    {
        $html = MediaPlayer::make('a')->label('A')->source('/a.mp4')
            ->captions('/en.vtt', 'en', 'English', default: true)->captions('/fr.vtt', 'fr', 'Français', default: true)
            ->render();

        self::assertSame(1, substr_count($html, ' default>'));
        self::assertStringContainsString('label="Français" default>', $html);
    }

    public function testAnAudioPlayerWithATranscript(): void
    {
        $html = MediaPlayer::make('podcast')->audio()->label('Episode 12')->source('/media/12.mp3', 'audio/mpeg')
            ->poster('/ignored.jpg')->loop()->muted()
            ->transcript([[0, 'Welcome <back>.', 'Ada'], [65.5, 'Today: Caelrik.'], ['1:02:03', 'Goodbye.']])
            ->labels(['transcript' => 'Transcription', 'playFrom' => 'Lire depuis {time}'])
            ->render();

        self::assertStringContainsString(
            '<audio class="caelrik-media-element" controls preload="metadata" loop muted aria-label="Episode 12">',
            $html,
        );
        self::assertStringContainsString('caelrik-media caelrik-media-audio', $html);
        self::assertStringNotContainsString('poster', $html);
        self::assertStringContainsString('&quot;playFrom&quot;:&quot;Lire depuis {time}&quot;', $html);
        self::assertStringEndsWith(
            '<details class="caelrik-media-transcript"><summary>Transcription</summary><ol class="caelrik-media-cues">'
            . '<li class="caelrik-media-cue" data-time="0"><time class="caelrik-media-time" datetime="PT0S">0:00</time> '
            . '<span class="caelrik-media-words"><span class="caelrik-media-speaker">Ada</span> Welcome &lt;back&gt;.</span></li>'
            . '<li class="caelrik-media-cue" data-time="65.5"><time class="caelrik-media-time" datetime="PT65.5S">1:05</time> '
            . '<span class="caelrik-media-words">Today: Caelrik.</span></li>'
            . '<li class="caelrik-media-cue" data-time="3723"><time class="caelrik-media-time" datetime="PT3723S">1:02:03</time> '
            . '<span class="caelrik-media-words">Goodbye.</span></li></ol></details></figure>',
            $html,
        );
        // No inline style: a strict Content Security Policy blocks style attributes.
        self::assertStringNotContainsString('style=', $html);
    }

    public function testRawPlyrOptions(): void
    {
        $html = MediaPlayer::make('a')->label('A')->source('/a.mp4')
            ->options(['i18n' => ['play' => 'Lire']])->render();

        self::assertStringContainsString('&quot;options&quot;:{&quot;i18n&quot;:{&quot;play&quot;:&quot;Lire&quot;}}', $html);
    }

    public function testASourceAndALabelAreNeeded(): void
    {
        foreach (
            [
                [MediaPlayer::make('a')->label('A'), 'Media player "a" needs a source().'],
                [MediaPlayer::make('b')->source('/b.mp4'), 'Media player "b" needs a label.'],
            ] as [$player, $message]
        ) {
            try {
                $player->render();
                self::fail('A player rendered without what it needs.');
            } catch (LogicException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testInvalidOptions(): void
    {
        foreach (
            [
                static fn () => MediaPlayer::make()->source('javascript:alert(1)'),
                static fn () => MediaPlayer::make()->poster('data:image/png;base64,AAAA'),
                static fn () => MediaPlayer::make()->captions('/a.vtt', 'english!', 'English'),
                static fn () => MediaPlayer::make()->transcript([['1:5', 'Bad time']]),
                static fn () => MediaPlayer::make()->transcript([[-1, 'Negative']]),
                static fn () => MediaPlayer::make()->transcript([['Only text']]),
                static fn () => MediaPlayer::make()->labels(['play' => 'x']),
            ] as $invalid
        ) {
            try {
                $invalid();
                self::fail('An invalid option was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
