<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A video or audio player: a native <video> or <audio> element (it plays without JavaScript,
 * with the browser's controls), which Plyr gives the same accessible controls in every browser:
 * play, a seek bar, the time, volume, captions, speed, picture in picture and full screen, all
 * with the keyboard and named for screen readers.
 * - captions() and descriptions() add text tracks (WebVTT); transcript() writes the words
 *   under the player, each with a time that plays the media from there (WCAG 1.2).
 * - The player is a figure named by its label.
 *
 *     MediaPlayer::make('intro')->label('Getting started')
 *         ->source('/media/intro.mp4', 'video/mp4')->poster('/media/intro.jpg')
 *         ->captions('/media/intro.en.vtt', 'en', 'English', default: true);
 */
final class MediaPlayer extends Component
{
    public const TYPES = ['video', 'audio'];

    public const LABELS = [
        'transcript' => 'Transcript',
        'playFrom' => 'Play from {time}',
        'unsupported' => 'Your browser cannot play this media.',
        'download' => 'Download it',
    ];

    private string $type = 'video';
    private string $label = '';
    private ?string $poster = null;
    private bool $loop = false;
    private bool $muted = false;

    /** @var list<array{src: string, type: ?string}> */
    private array $sources = [];

    /** @var list<array{kind: string, src: string, srclang: string, label: string, default: bool}> */
    private array $tracks = [];

    /** @var list<array{time: float, text: string, speaker: ?string}> */
    private array $transcript = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'mediaplayer';
    }

    public function video(): self
    {
        $this->type = 'video';

        return $this;
    }

    public function audio(): self
    {
        $this->type = 'audio';

        return $this;
    }

    /**
     * What is played ("Getting started"): it names the player and its controls.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A file to play, with its MIME type ("video/mp4"). Several sources let the browser pick the
     * first format it plays.
     */
    public function source(string $url, ?string $type = null): self
    {
        $this->sources[] = ['src' => self::url($url), 'type' => $type];

        return $this;
    }

    /**
     * The picture shown before a video plays.
     */
    public function poster(string $url): self
    {
        $this->poster = self::url($url);

        return $this;
    }

    /**
     * Captions (WebVTT): the words and sounds, for people who do not hear them. A default track
     * is on from the start.
     */
    public function captions(string $url, string $language, string $label, bool $default = false): self
    {
        return $this->track('captions', $url, $language, $label, $default);
    }

    /**
     * Subtitles (WebVTT): a translation of the words.
     */
    public function subtitles(string $url, string $language, string $label, bool $default = false): self
    {
        return $this->track('subtitles', $url, $language, $label, $default);
    }

    /**
     * Audio descriptions (WebVTT): what is seen, for people who do not see it. Browsers expose
     * them to screen readers, which may read them.
     */
    public function descriptions(string $url, string $language, string $label): self
    {
        return $this->track('descriptions', $url, $language, $label, false);
    }

    /**
     * The words under the player, each with a time that plays the media from there: a list of
     * [time, text] or [time, text, speaker], the time in seconds or as "1:05" / "1:02:03".
     *
     * @param list<array{0: int|float|string, 1: string, 2?: string|null}> $cues
     */
    public function transcript(array $cues): self
    {
        $this->transcript = [];
        foreach ($cues as $cue) {
            if (!is_array($cue) || !isset($cue[0], $cue[1]) || !is_string($cue[1])) {
                throw new InvalidArgumentException('A transcript line is [time, text] or [time, text, speaker].');
            }
            $this->transcript[] = ['time' => self::seconds($cue[0]), 'text' => $cue[1], 'speaker' => $cue[2] ?? null];
        }

        return $this;
    }

    public function loop(bool $loop = true): self
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * Starts muted (a background video). The player never plays on its own: autoplay moves and
     * sounds without warning (WCAG 1.4.2, 2.2.2).
     */
    public function muted(bool $muted = true): self
    {
        $this->muted = $muted;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown media player label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->label,
            'labels' => ['playFrom' => $this->labels['playFrom']],
            'options' => $this->options,
        ];
    }

    public function render(): string
    {
        if ($this->sources === []) {
            throw new LogicException(sprintf('Media player "%s" needs a source().', $this->id));
        }
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('Media player "%s" needs a label.', $this->id));
        }

        $content = '';
        foreach ($this->sources as $source) {
            $content .= Html::tag('source', ['src' => $source['src'], 'type' => $source['type']]);
        }
        foreach ($this->tracks as $track) {
            $content .= Html::tag('track', $track);
        }
        $content .= Html::tag('p', [], Html::escape($this->labels['unsupported']) . ' ' . Html::tag(
            'a',
            ['href' => $this->sources[0]['src'], 'download' => true],
            Html::escape($this->labels['download']),
        ));

        $media = Html::tag($this->type, [
            'class' => 'celerik-media-element',
            'controls' => true,
            'preload' => 'metadata',
            'playsinline' => $this->type === 'video',
            'poster' => $this->type === 'video' ? $this->poster : null,
            'loop' => $this->loop,
            'muted' => $this->muted,
            'aria-label' => $this->label,
        ], $content);

        return Html::tag(
            'figure',
            $this->rootAttributes(['celerik-media', 'celerik-media-' . $this->type]),
            Html::tag('div', ['class' => 'celerik-media-frame'], $media)
                . Html::tag('figcaption', ['class' => 'celerik-media-caption'], Html::escape($this->label))
                . $this->renderTranscript(),
        );
    }

    private function renderTranscript(): string
    {
        if ($this->transcript === []) {
            return '';
        }
        $items = '';
        foreach ($this->transcript as $cue) {
            $speaker = $cue['speaker'] === null
                ? ''
                : Html::tag('span', ['class' => 'celerik-media-speaker'], Html::escape($cue['speaker'])) . ' ';
            $seconds = self::number($cue['time']);
            $time = Html::tag(
                'time',
                ['class' => 'celerik-media-time', 'datetime' => 'PT' . $seconds . 'S'],
                self::clock($cue['time']),
            );
            $words = Html::tag('span', ['class' => 'celerik-media-words'], $speaker . Html::escape($cue['text']));
            $items .= Html::tag('li', ['class' => 'celerik-media-cue', 'data-time' => $seconds], $time . ' ' . $words);
        }

        return Html::tag(
            'details',
            ['class' => 'celerik-media-transcript'],
            Html::tag('summary', [], Html::escape($this->labels['transcript']))
                . Html::tag('ol', ['class' => 'celerik-media-cues'], $items),
        );
    }

    private function track(string $kind, string $url, string $language, string $label, bool $default): self
    {
        if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language) !== 1) {
            throw new InvalidArgumentException(sprintf('"%s" is not a language tag.', $language));
        }
        if ($default) {
            foreach ($this->tracks as $index => $track) {
                $this->tracks[$index]['default'] = false;
            }
        }
        $this->tracks[] = [
            'kind' => $kind,
            'src' => self::url($url),
            'srclang' => $language,
            'label' => $label,
            'default' => $default,
        ];

        return $this;
    }

    private static function url(string $url): string
    {
        $safe = Url::safe($url);
        if ($safe === null) {
            throw new InvalidArgumentException('A media player takes relative, http or https URLs.');
        }

        return $safe;
    }

    private static function seconds(int|float|string $time): float
    {
        if (is_string($time)) {
            if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2}(?:\.\d+)?)$/', $time, $parts) !== 1) {
                throw new InvalidArgumentException(sprintf('"%s" is not a time such as 1:05 or 1:02:03.', $time));
            }

            return (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (float) $parts[3];
        }
        if ($time < 0) {
            throw new InvalidArgumentException('A time is not negative.');
        }

        return (float) $time;
    }

    /** 65 → "1:05", 3723 → "1:02:03". */
    private static function clock(float $seconds): string
    {
        $whole = (int) floor($seconds);
        $hours = intdiv($whole, 3600);
        $minutes = intdiv($whole % 3600, 60);

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $whole % 60)
            : sprintf('%d:%02d', $minutes, $whole % 60);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.3F', $value), '0'), '.');
    }
}
