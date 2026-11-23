# Media player

A video or audio player with [Plyr](https://plyr.io): the same accessible controls in every browser, captions, and a transcript that plays the media from any line.

<Demo name="mediaplayer" :min-height="520" />

<<< @/demos/mediaplayer.php

The demo plays a short tone (both players share one sound file). Its video player shows a poster and captions.

## Usage

```php
use Caelrik\Ui\Components\MediaPlayer;

MediaPlayer::make('intro')
    ->label('Getting started')
    ->source('/media/intro.webm', 'video/webm')
    ->source('/media/intro.mp4', 'video/mp4')
    ->poster('/media/intro.jpg')
    ->captions('/media/intro.en.vtt', 'en', 'English', default: true)
    ->captions('/media/intro.fr.vtt', 'fr', 'Français')
    ->descriptions('/media/intro.desc.vtt', 'en', 'English')
    ->transcript([
        [0, 'Welcome to Caelrik.', 'Ada'],
        ['1:05', 'Install it with Composer.'],
    ]);
```

- **`video()`** (the default) or **`audio()`**.
- **`label()`**: what is played. It names the player, its caption and its Play button ("Play, Getting started").
- **`source($url, $type)`**: one or more files; the browser plays the first format it can. **`poster()`**: the picture shown before a video plays.
- **`captions()`**, **`subtitles()`** and **`descriptions()`**: WebVTT text tracks. Captions give the words and sounds; subtitles translate; audio descriptions say what is seen. A `default` track is on from the start.
- **`transcript()`**: the words under the player, as `[time, text]` or `[time, text, speaker]` lines, the time in seconds or as `1:05` / `1:02:03`.
- **`loop()`**, **`muted()`**. The player never starts on its own: autoplay surprises people with sound and movement.
- **`labels()`** translates `transcript`, `playFrom`, `unsupported` and `download`. Plyr's own texts are translated with `options(['i18n' => [...]])` ([Plyr's keys](https://github.com/sampotts/plyr#options)), which passes any Plyr option.

Plyr's icons come from the bundle, never from its servers. Under a Content Security Policy, allow `media-src` for your media. Plyr writes caption text as HTML: use caption files you trust.

## Accessibility

- **Captions, descriptions and a transcript** cover WCAG's time-based media criteria: captions for people who do not hear (1.2.2), descriptions or a transcript for people who do not see (1.2.3, 1.2.5), a transcript for audio alone (1.2.1).
- **The transcript plays the media from any line**: each time is a button ("Play from 1:05"), and the line being spoken is marked while the media plays, by a bar and bold text (not by color alone). It is a native disclosure, closed until opened.
- **Plyr's controls** are buttons with names (Play, Mute, Captions, Settings, Full screen), a seek bar and a volume that are sliders, and keys when the player has focus (Space or K to play, arrows to seek and change the volume, M to mute, C for captions, F for full screen). They stay visible during playback.
- **Without JavaScript** the browser's own player is shown, with the same sources and tracks, and the transcript is still there.
- The player is a figure named by its label; it fits narrow screens.
