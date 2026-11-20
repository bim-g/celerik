<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * A carousel (the WAI-ARIA carousel pattern): slides shown one at a time, with Previous and
 * Next buttons and a button per slide.
 * - Each slide is a group named "2 of 5", or by its label; changes made by the user are
 *   announced (the slides are a polite live region, silent while they rotate).
 * - autoplay(): a Stop/Start button comes first; rotation pauses while the pointer or focus is
 *   in the carousel, stops once the user picks a slide, and does not start for reduced motion
 *   (WCAG 2.2.2).
 * - Touch screens swipe. Without JavaScript, the slides are shown one under the other.
 *
 *     Carousel::make('news')->label('News')
 *         ->image('/img/lake.jpg', 'Lake Kivu at dawn', caption: 'Our new office')
 *         ->slide(Html::raw('<h3>Spring sale</h3><p>…</p>'), label: 'Spring sale')
 *         ->autoplay(6);
 */
final class Carousel extends Component
{
    public const RATIOS = ['16x9', '4x3', '3x2', '21x9', '1x1'];
    public const LABELS = [
        'previous' => 'Previous slide',
        'next' => 'Next slide',
        'start' => 'Start automatic slide show',
        'stop' => 'Stop automatic slide show',
        'slides' => 'Choose a slide',
        'slide' => '{index} of {count}',
        'show' => 'Slide {index}',
    ];

    private const CHEVRON_START = '<svg class="celerik-carousel-icon" viewBox="0 0 16 16" width="16" height="16" '
        . 'aria-hidden="true" focusable="false"><path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    private const CHEVRON_END = '<svg class="celerik-carousel-icon" viewBox="0 0 16 16" width="16" height="16" '
        . 'aria-hidden="true" focusable="false"><path d="M6 3l5 5-5 5" fill="none" stroke="currentColor" '
        . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    private const PAUSE = '<svg class="celerik-carousel-icon celerik-carousel-icon-pause" viewBox="0 0 16 16" '
        . 'width="16" height="16" aria-hidden="true" focusable="false"><rect x="3.5" y="3" width="3" height="10" '
        . 'rx="0.5" fill="currentColor"/><rect x="9.5" y="3" width="3" height="10" rx="0.5" '
        . 'fill="currentColor"/></svg>';
    private const PLAY = '<svg class="celerik-carousel-icon celerik-carousel-icon-play" viewBox="0 0 16 16" '
        . 'width="16" height="16" aria-hidden="true" focusable="false"><path d="M4.5 2.5v11l9-5.5z" '
        . 'fill="currentColor"/></svg>';

    private ?string $label = null;
    private int $autoplay = 0;
    private bool $loop = true;
    private bool $picker = true;
    private string $ratio = '16x9';

    /** @var list<array{content: string, label: ?string}> */
    private array $slides = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'carousel';
    }

    /**
     * Names the carousel ("Highlights, carousel").
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Adds a slide: text (escaped) or HTML (a component, Html::raw()). The label names the
     * slide and its button ("Spring sale"); "2 of 5" and "Slide 2" otherwise.
     */
    public function slide(string|Stringable $content, ?string $label = null): self
    {
        $this->slides[] = ['content' => Html::content($content), 'label' => $label];

        return $this;
    }

    /**
     * Adds a picture slide, cropped to the carousel's ratio. The alternative text describes
     * the picture; the caption is shown under it.
     */
    public function image(string $src, string $alt, ?string $caption = null, ?string $label = null): self
    {
        $src = Url::safe($src);
        if ($src === null) {
            throw new InvalidArgumentException('A carousel picture needs a relative, http or https URL.');
        }
        $image = Html::tag('img', [
            'src' => $src,
            'alt' => $alt,
            'class' => 'celerik-carousel-image',
            'loading' => $this->slides === [] ? null : 'lazy',
        ]);
        $caption = $caption === null || $caption === ''
            ? ''
            : Html::tag('figcaption', ['class' => 'celerik-carousel-caption'], Html::escape($caption));
        $this->slides[] = [
            'content' => Html::tag('figure', ['class' => 'celerik-carousel-figure'], $image . $caption),
            'label' => $label,
        ];

        return $this;
    }

    /**
     * Shows the next slide every $seconds (2 to 60), with a Stop button; 0 turns it off.
     */
    public function autoplay(int $seconds = 5): self
    {
        if ($seconds !== 0 && ($seconds < 2 || $seconds > 60)) {
            throw new InvalidArgumentException('A carousel shows a slide for 2 to 60 seconds.');
        }
        $this->autoplay = $seconds;

        return $this;
    }

    /**
     * After the last slide comes the first (true by default); otherwise the buttons stop at the ends.
     */
    public function loop(bool $loop = true): self
    {
        $this->loop = $loop;

        return $this;
    }

    /**
     * A button per slide (true by default).
     */
    public function picker(bool $picker = true): self
    {
        $this->picker = $picker;

        return $this;
    }

    /**
     * The pictures' shape: 16x9 (default), 4x3, 3x2, 21x9 or 1x1.
     */
    public function ratio(string $ratio): self
    {
        if (!in_array($ratio, self::RATIOS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown carousel ratio "%s".', $ratio));
        }
        $this->ratio = $ratio;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown carousel label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'autoplay' => $this->autoplay,
            'loop' => $this->loop,
            'labels' => ['start' => $this->labels['start'], 'stop' => $this->labels['stop']],
        ];
    }

    public function render(): string
    {
        if ($this->slides === []) {
            throw new LogicException(sprintf('Carousel "%s" has no slide.', $this->id));
        }
        $count = count($this->slides);
        $slidesId = $this->id . '-slides';

        $slides = '';
        $dots = '';
        foreach ($this->slides as $index => $slide) {
            $position = ['{index}' => (string) ($index + 1), '{count}' => (string) $count];
            $slides .= Html::tag('div', [
                'class' => Html::classes(['celerik-carousel-slide', $index === 0 ? 'is-active' : null]),
                'id' => $this->id . '-slide-' . ($index + 1),
                'role' => 'group',
                'aria-roledescription' => 'slide',
                'aria-label' => $slide['label'] ?? strtr($this->labels['slide'], $position),
            ], $slide['content']);
            $dots .= Html::tag('button', [
                'type' => 'button',
                'class' => 'celerik-carousel-dot',
                'aria-controls' => $this->id . '-slide-' . ($index + 1),
                'aria-label' => $slide['label'] ?? strtr($this->labels['show'], $position),
                'aria-current' => $index === 0 ? 'true' : null,
            ], '');
        }

        $controls = '';
        if ($count > 1) {
            if ($this->autoplay > 0) {
                $controls .= $this->button('rotate', $this->labels['stop'], self::PAUSE . self::PLAY, $slidesId);
            }
            $controls .= $this->button(
                'previous',
                $this->labels['previous'],
                self::CHEVRON_START,
                $slidesId,
                !$this->loop,
            );
            if ($this->picker) {
                $controls .= Html::tag('div', [
                    'class' => 'celerik-carousel-picker',
                    'role' => 'group',
                    'aria-label' => $this->labels['slides'],
                ], $dots);
            }
            $controls .= $this->button('next', $this->labels['next'], self::CHEVRON_END, $slidesId);
            $controls = Html::tag('div', ['class' => 'celerik-carousel-controls'], $controls);
        }

        $attributes = $this->rootAttributes([
            'celerik-carousel',
            'celerik-carousel-ratio-' . $this->ratio,
            $this->autoplay > 0 ? 'is-playing' : null,
        ]);
        $attributes['aria-roledescription'] = 'carousel';
        $attributes['aria-label'] = $this->label;

        return Html::tag('section', $attributes, $controls . Html::tag('div', [
            'class' => 'celerik-carousel-slides',
            'id' => $slidesId,
            'aria-live' => $this->autoplay > 0 ? 'off' : 'polite',
        ], $slides));
    }

    private function button(string $role, string $label, string $icon, string $controls, bool $disabled = false): string
    {
        return Html::tag('button', [
            'type' => 'button',
            'class' => 'btn btn-sm celerik-action-button btn-outline-secondary celerik-carousel-' . $role,
            'aria-label' => $label,
            'aria-controls' => $controls,
            'aria-disabled' => $disabled ? 'true' : null,
        ], $icon);
    }
}
