<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use Caelrik\Ui\Support\QrMatrix;
use Caelrik\Ui\Support\Url;
use InvalidArgumentException;
use LengthException;
use LogicException;

/**
 * A QR code drawn by PHP as an SVG (no JavaScript, no inline styles), with its content written
 * under it, so that nobody needs a phone to read it.
 * - The drawing is an image named "QR code: {label}" (the label, or else the value).
 * - The value is written in the caption, as a link when it is a web address; hideValue() keeps
 *   it out of the caption (a long token), and the label then says what the code is for.
 * - Dark modules on a light tile, in dark mode and in forced colors too: scanners need them.
 *
 *     QrCode::make('site')->value('https://example.com')->label('Our website');
 */
final class QrCode extends Component
{
    public const LABELS = ['image' => 'QR code: {label}'];

    /** The light margin around the code, in modules (four, as the standard requires). */
    private const QUIET_ZONE = 4;

    private string $value = '';
    private string $label = '';
    private string $level = 'M';
    private int $size = 160;
    private bool $showValue = true;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'qrcode';
    }

    /**
     * The text the code holds: a web address, a phone number, a Wi-Fi setup…
     */
    public function value(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * What the code is for ("Our website"), written above the value and naming the image.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The error correction: L (7%), M (15%, default), Q (25%) or H (30%). A higher level makes a
     * denser code that still scans when damaged or partly covered.
     */
    public function level(string $level): self
    {
        if (!in_array($level, QrMatrix::LEVELS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown QR code level "%s".', $level));
        }
        $this->level = $level;

        return $this;
    }

    /**
     * The width and height in pixels (160 by default); the code shrinks to a narrower container.
     */
    public function size(int $pixels): self
    {
        if ($pixels < 48) {
            throw new InvalidArgumentException('A QR code is at least 48 pixels wide.');
        }
        $this->size = $pixels;

        return $this;
    }

    public function showValue(bool $show = true): self
    {
        $this->showValue = $show;

        return $this;
    }

    public function hideValue(): self
    {
        return $this->showValue(false);
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown QR code label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->value === '') {
            throw new LogicException(sprintf('QR code "%s" needs a value.', $this->id));
        }
        if (!$this->showValue && trim($this->label) === '') {
            throw new LogicException(sprintf('QR code "%s" hides its value, so it needs a label.', $this->id));
        }
        try {
            $matrix = QrMatrix::encode($this->value, $this->level);
        } catch (LengthException $exception) {
            throw new LogicException(sprintf('QR code "%s": %s', $this->id, $exception->getMessage()), 0, $exception);
        }

        $caption = '';
        if (trim($this->label) !== '') {
            $caption .= Html::tag('span', ['class' => 'caelrik-qrcode-label'], Html::escape($this->label));
        }
        if ($this->showValue) {
            $url = preg_match('~^https?://~i', $this->value) === 1 ? Url::safe($this->value) : null;
            $caption .= $url === null
                ? Html::tag('span', ['class' => 'caelrik-qrcode-value'], Html::escape($this->value))
                : Html::tag('a', ['class' => 'caelrik-qrcode-value', 'href' => $url], Html::escape($this->value));
        }

        return Html::tag(
            'figure',
            $this->rootAttributes(['caelrik-qrcode']),
            $this->svg($matrix) . Html::tag('figcaption', ['class' => 'caelrik-qrcode-caption'], $caption),
        );
    }

    /** @param list<list<bool>> $matrix */
    private function svg(array $matrix): string
    {
        $side = count($matrix) + self::QUIET_ZONE * 2;
        // One subpath per run of dark modules in a row.
        $path = '';
        foreach ($matrix as $y => $row) {
            $run = 0;
            foreach ([...$row, false] as $x => $dark) {
                if ($dark) {
                    $run++;
                } elseif ($run > 0) {
                    $left = $x - $run + self::QUIET_ZONE;
                    $path .= sprintf('M%d %dh%dv1h-%dz', $left, $y + self::QUIET_ZONE, $run, $run);
                    $run = 0;
                }
            }
        }
        $name = strtr($this->labels['image'], ['{label}' => trim($this->label) !== '' ? $this->label : $this->value]);

        return Html::tag('svg', [
            'class' => 'caelrik-qrcode-image',
            'role' => 'img',
            'aria-label' => $name,
            'viewBox' => '0 0 ' . $side . ' ' . $side,
            'width' => $this->size,
            'height' => $this->size,
            'shape-rendering' => 'crispEdges',
            'focusable' => 'false',
        ], Html::tag('rect', ['class' => 'caelrik-qrcode-light', 'width' => $side, 'height' => $side], '')
            . Html::tag('path', ['class' => 'caelrik-qrcode-dark', 'd' => $path], ''));
    }
}
