<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A one-dimensional barcode drawn by PHP as an SVG (no JavaScript, no inline styles), with its
 * value written under it as text.
 * - code128 (default): any ASCII text; runs of digits are packed two by two.
 * - ean13: 12 digits (the check digit is added) or 13 (the check digit is checked), as on
 *   products and books (ISBN).
 * - The drawing is an image named "Barcode: {label}" (the label, or else the value). Dark bars
 *   on a light tile, in dark mode and in forced colors too: scanners need them.
 *
 *     Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN');
 */
final class Barcode extends Component
{
    public const TYPES = ['code128', 'ean13'];
    public const LABELS = ['image' => 'Barcode: {label}'];

    /** Code 128 symbols 0 to 106 (106 is the stop): bar, space, bar… widths in modules. */
    private const CODE128 = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    /** EAN-13 digits in the L set; R is its complement, G is R reversed. */
    private const EAN_L = [
        '0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011',
    ];

    /** The L/G sets of the six left digits, by the first digit. */
    private const EAN_PARITY = [
        'LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL',
    ];

    private string $value = '';
    private string $label = '';
    private string $type = 'code128';
    private int $height = 80;
    private int $scale = 2;
    private bool $showValue = true;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'barcode';
    }

    public function value(string $value): self
    {
        $this->value = $value;

        return $this;
    }

    /**
     * What the barcode is ("ISBN", "Order number"), written above the value and naming the image.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function type(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown barcode type "%s".', $type));
        }
        $this->type = $type;

        return $this;
    }

    /**
     * The height of the bars in pixels (80 by default).
     */
    public function height(int $pixels): self
    {
        if ($pixels < 24) {
            throw new InvalidArgumentException('Barcode bars are at least 24 pixels high.');
        }
        $this->height = $pixels;

        return $this;
    }

    /**
     * The width of the narrowest bar in pixels, 1 to 4 (2 by default); the barcode shrinks to a
     * narrower container.
     */
    public function scale(int $pixels): self
    {
        if ($pixels < 1 || $pixels > 4) {
            throw new InvalidArgumentException('A barcode scale is between 1 and 4 pixels.');
        }
        $this->scale = $pixels;

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
                throw new InvalidArgumentException(sprintf('Unknown barcode label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    /**
     * The modules of a barcode, without the quiet zones ("1" a bar, "0" a space), and the text it
     * holds (an EAN-13 with its check digit).
     *
     * @return array{string, string}
     * @throws InvalidArgumentException when the value cannot be written in this type
     */
    public static function modules(string $value, string $type = 'code128'): array
    {
        return match ($type) {
            'code128' => [self::code128($value), $value],
            'ean13' => self::ean13($value),
            default => throw new InvalidArgumentException(sprintf('Unknown barcode type "%s".', $type)),
        };
    }

    public function render(): string
    {
        if ($this->value === '') {
            throw new LogicException(sprintf('Barcode "%s" needs a value.', $this->id));
        }
        if (!$this->showValue && trim($this->label) === '') {
            throw new LogicException(sprintf('Barcode "%s" hides its value, so it needs a label.', $this->id));
        }
        [$modules, $text] = self::modules($this->value, $this->type);

        $caption = '';
        if (trim($this->label) !== '') {
            $caption .= Html::tag('span', ['class' => 'caelrik-barcode-label'], Html::escape($this->label));
        }
        if ($this->showValue) {
            $caption .= Html::tag('span', ['class' => 'caelrik-barcode-value'], Html::escape($text));
        }

        return Html::tag(
            'figure',
            $this->rootAttributes(['caelrik-barcode']),
            $this->svg($modules, $text) . Html::tag('figcaption', ['class' => 'caelrik-barcode-caption'], $caption),
        );
    }

    private function svg(string $modules, string $text): string
    {
        // EAN-13 wants 11 light modules on the left and 7 on the right; Code 128 wants 10.
        [$left, $right] = $this->type === 'ean13' ? [11, 7] : [10, 10];
        $width = $left + strlen($modules) + $right;
        $height = $this->height / $this->scale;
        $path = '';
        preg_match_all('/1+/', $modules, $bars, PREG_OFFSET_CAPTURE);
        foreach ($bars[0] as [$bar, $offset]) {
            $path .= sprintf('M%d 0h%dv%sh-%dz', $offset + $left, strlen($bar), self::number($height), strlen($bar));
        }
        $name = strtr($this->labels['image'], ['{label}' => trim($this->label) !== '' ? $this->label : $text]);

        return Html::tag('svg', [
            'class' => 'caelrik-barcode-image',
            'role' => 'img',
            'aria-label' => $name,
            'viewBox' => '0 0 ' . $width . ' ' . self::number($height),
            'width' => $width * $this->scale,
            'height' => $this->height,
            'preserveAspectRatio' => 'none',
            'shape-rendering' => 'crispEdges',
            'focusable' => 'false',
        ], Html::tag('rect', [
            'class' => 'caelrik-barcode-light',
            'width' => $width,
            'height' => self::number($height),
        ], '') . Html::tag('path', ['class' => 'caelrik-barcode-dark', 'd' => $path], ''));
    }

    private static function code128(string $value): string
    {
        if ($value === '' || preg_match('/[^\x00-\x7F]/', $value) === 1) {
            throw new InvalidArgumentException('Code 128 holds ASCII characters only.');
        }
        $length = strlen($value);
        $digits = static fn (int $at): int => strspn($value, '0123456789', $at);

        $first = $digits(0);
        if (($first >= 4 && $first % 2 === 0) || ($first === 2 && $length === 2)) {
            [$set, $codes] = ['C', [105]];
        } elseif (ord($value[0]) < 32) {
            [$set, $codes] = ['A', [103]];
        } else {
            [$set, $codes] = ['B', [104]];
        }

        for ($i = 0; $i < $length;) {
            if ($set === 'C') {
                if ($digits($i) >= 2) {
                    $codes[] = (int) substr($value, $i, 2);
                    $i += 2;
                } else {
                    // Code A or Code B.
                    $set = ord($value[$i]) < 32 ? 'A' : 'B';
                    $codes[] = $set === 'A' ? 101 : 100;
                }
                continue;
            }
            $run = $digits($i);
            if ($run >= 6 || ($run >= 4 && $i + $run === $length)) {
                if ($run % 2 === 0) {
                    // Code C.
                    [$set, $codes[]] = ['C', 99];
                    continue;
                }
                // An odd run: its first digit stays in this set.
            }
            $char = ord($value[$i]);
            if ($set === 'B' && $char < 32) {
                [$set, $codes[]] = ['A', 101];
                continue;
            }
            if ($set === 'A' && $char >= 96) {
                [$set, $codes[]] = ['B', 100];
                continue;
            }
            $codes[] = $set === 'A' && $char < 32 ? $char + 64 : $char - 32;
            $i++;
        }

        $sum = $codes[0];
        foreach (array_slice($codes, 1) as $position => $code) {
            $sum += $code * ($position + 1);
        }
        $codes[] = $sum % 103;
        $codes[] = 106;

        $modules = '';
        foreach ($codes as $code) {
            foreach (str_split(self::CODE128[$code]) as $index => $widthOf) {
                $modules .= str_repeat($index % 2 === 0 ? '1' : '0', (int) $widthOf);
            }
        }

        return $modules;
    }

    /** @return array{string, string} */
    private static function ean13(string $value): array
    {
        if (preg_match('/^\d{12,13}$/', $value) !== 1) {
            throw new InvalidArgumentException('An EAN-13 is 12 digits, or 13 with its check digit.');
        }
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $value[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $check = (string) ((10 - $sum % 10) % 10);
        if (strlen($value) === 13 && $value[12] !== $check) {
            throw new InvalidArgumentException(sprintf(
                'The check digit of EAN-13 "%s" is wrong: it should be %s.',
                $value,
                $check,
            ));
        }
        $value = substr($value, 0, 12) . $check;

        $modules = '101';
        $parity = self::EAN_PARITY[(int) $value[0]];
        for ($i = 1; $i <= 6; $i++) {
            $l = self::EAN_L[(int) $value[$i]];
            $modules .= $parity[$i - 1] === 'L' ? $l : strrev(strtr($l, '01', '10'));
        }
        $modules .= '01010';
        for ($i = 7; $i <= 12; $i++) {
            $modules .= strtr(self::EAN_L[(int) $value[$i]], '01', '10');
        }

        return [$modules . '101', $value];
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');
    }
}
