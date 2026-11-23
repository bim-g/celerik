<?php

declare(strict_types=1);

namespace Caelrik\Ui\Support;

use InvalidArgumentException;
use LengthException;

/**
 * A QR code encoder (ISO/IEC 18004, model 2, versions 1 to 40): the text is encoded as one
 * segment (numeric, alphanumeric or UTF-8 bytes, whichever is shortest) in the smallest version
 * that holds it, with Reed-Solomon error correction and the mask of the lowest penalty.
 * It follows the structure of Project Nayuki's QR Code generator (MIT).
 *
 *     QrMatrix::encode('https://example.com', 'M'); // list of rows of booleans (true = dark)
 */
final class QrMatrix
{
    public const LEVELS = ['L', 'M', 'Q', 'H'];

    private const ALPHANUMERIC = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /** The two format bits of each level. */
    private const FORMAT_BITS = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    /** Error correction codewords per block, by level and version (index 0 is unused). */
    private const ECC_PER_BLOCK = [
        'L' => [0, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30,
            26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28,
            28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [0, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30,
            30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [0, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30,
            30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Error correction blocks, by level and version (index 0 is unused). */
    private const BLOCKS = [
        'L' => [0, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15,
            16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25,
            26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [0, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34,
            35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [0, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37,
            40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $size;

    /** @var list<list<bool>> */
    private array $modules = [];

    /** @var list<list<bool>> */
    private array $function = [];

    private function __construct(private readonly int $version, private readonly string $level)
    {
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->function = array_fill(0, $this->size, $row);
    }

    /**
     * The modules of the QR code of $text, row by row (true = dark), without the quiet zone.
     *
     * @param int|null $version a fixed version (1 to 40); by default the smallest that holds the text
     * @param int|null $mask a fixed mask (0 to 7); by default the one of the lowest penalty
     * @return list<list<bool>>
     * @throws LengthException when the text is too long for a QR code at this level
     */
    public static function encode(string $text, string $level = 'M', ?int $version = null, ?int $mask = null): array
    {
        if (!in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown QR code level "%s".', $level));
        }
        if ($version !== null && ($version < 1 || $version > 40)) {
            throw new InvalidArgumentException('A QR code version is between 1 and 40.');
        }
        if ($mask !== null && ($mask < 0 || $mask > 7)) {
            throw new InvalidArgumentException('A QR code mask is between 0 and 7.');
        }

        [$mode, $count, $bits] = self::segment($text);
        $chosen = null;
        foreach ($version === null ? range(1, 40) : [$version] as $candidate) {
            $countBits = self::countBits($mode, $candidate);
            $length = 4 + $countBits + strlen($bits);
            if ($count < 1 << $countBits && $length <= self::dataCodewords($candidate, $level) * 8) {
                $chosen = $candidate;
                break;
            }
        }
        if ($chosen === null) {
            throw new LengthException(sprintf(
                'The text is too long for a QR code at level %s (%d bytes at most).',
                $level,
                self::dataCodewords(40, $level) - 3,
            ));
        }

        $capacity = self::dataCodewords($chosen, $level) * 8;
        $data = str_pad(decbin($mode), 4, '0', STR_PAD_LEFT)
            . str_pad(decbin($count), self::countBits($mode, $chosen), '0', STR_PAD_LEFT) . $bits;
        $data .= str_repeat('0', min(4, $capacity - strlen($data)));
        $data .= str_repeat('0', (8 - strlen($data) % 8) % 8);
        for ($pad = 0xEC; strlen($data) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $data .= str_pad(decbin($pad), 8, '0', STR_PAD_LEFT);
        }
        $codewords = array_map('bindec', str_split($data, 8));

        $qr = new self($chosen, $level);
        $qr->drawFunctionPatterns();
        $qr->drawCodewords($qr->interleave(array_map('intval', $codewords)));

        if ($mask === null) {
            $best = PHP_INT_MAX;
            foreach (range(0, 7) as $candidate) {
                $qr->applyMask($candidate);
                $qr->drawFormatBits($candidate);
                $penalty = $qr->penalty();
                if ($penalty < $best) {
                    [$best, $mask] = [$penalty, $candidate];
                }
                $qr->applyMask($candidate);
            }
        }
        $qr->applyMask((int) $mask);
        $qr->drawFormatBits((int) $mask);

        return $qr->modules;
    }

    /**
     * The mode (1 numeric, 2 alphanumeric, 4 bytes), the character count and the data bits.
     *
     * @return array{int, int, string}
     */
    private static function segment(string $text): array
    {
        if (preg_match('/^[0-9]*$/', $text) === 1) {
            $bits = '';
            foreach (str_split($text, 3) as $group) {
                if ($group !== '') {
                    $bits .= str_pad(decbin((int) $group), strlen($group) * 3 + 1, '0', STR_PAD_LEFT);
                }
            }

            return [1, strlen($text), $bits];
        }
        if (strspn($text, self::ALPHANUMERIC) === strlen($text)) {
            $bits = '';
            foreach (str_split($text, 2) as $pair) {
                $value = strpos(self::ALPHANUMERIC, $pair[0]);
                $bits .= strlen($pair) === 2
                    ? str_pad(decbin($value * 45 + strpos(self::ALPHANUMERIC, $pair[1])), 11, '0', STR_PAD_LEFT)
                    : str_pad(decbin($value), 6, '0', STR_PAD_LEFT);
            }

            return [2, strlen($text), $bits];
        }
        $bits = '';
        foreach (str_split($text) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return [4, strlen($text), $bits];
    }

    private static function countBits(int $mode, int $version): int
    {
        $range = $version < 10 ? 0 : ($version < 27 ? 1 : 2);

        return match ($mode) {
            1 => [10, 12, 14][$range],
            2 => [9, 11, 13][$range],
            default => [8, 16, 16][$range],
        };
    }

    /** The modules that hold data and error correction, after the function patterns. */
    private static function rawModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $align = intdiv($version, 7) + 2;
            $result -= (25 * $align - 10) * $align - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }

        return $result;
    }

    private static function dataCodewords(int $version, string $level): int
    {
        return intdiv(self::rawModules($version), 8)
            - self::ECC_PER_BLOCK[$level][$version] * self::BLOCKS[$level][$version];
    }

    /**
     * The centers of the alignment patterns on each axis.
     *
     * @return list<int>
     */
    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $count = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $count * 3 + 5, $count * 4 - 4) * 2;
        $positions = [];
        for ($i = 0, $position = $this->size - 7; $i < $count - 1; $i++, $position -= $step) {
            array_unshift($positions, $position);
        }
        array_unshift($positions, 6);

        return $positions;
    }

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->function[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$this->size - 4, 3], [3, $this->size - 4]] as [$x, $y]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $distance = max(abs($dx), abs($dy));
                    if ($x + $dx >= 0 && $x + $dx < $this->size && $y + $dy >= 0 && $y + $dy < $this->size) {
                        $this->set($x + $dx, $y + $dy, $distance !== 2 && $distance !== 4);
                    }
                }
            }
        }
        $positions = $this->alignmentPositions();
        $last = count($positions) - 1;
        foreach ($positions as $i => $x) {
            foreach ($positions as $j => $y) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($x + $dx, $y + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0);
        $this->drawVersion();
    }

    private function drawFormatBits(int $mask): void
    {
        $data = self::FORMAT_BITS[$this->level] << 3 | $mask;
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        $bits = ($data << 10 | $remainder) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $this->size - 15 + $i, $bit($i));
        }
        $this->set(8, $this->size - 8, true);
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $remainder = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
        }
        $bits = $this->version << 12 | $remainder;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->set($a, $b, $dark);
            $this->set($b, $a, $dark);
        }
    }

    /**
     * Splits the data into blocks, adds each block's error correction and interleaves them.
     *
     * @param list<int> $data
     * @return list<int>
     */
    private function interleave(array $data): array
    {
        $blocks = self::BLOCKS[$this->level][$this->version];
        $eccLength = self::ECC_PER_BLOCK[$this->level][$this->version];
        $raw = intdiv(self::rawModules($this->version), 8);
        $short = $blocks - $raw % $blocks;
        $shortLength = intdiv($raw, $blocks);
        $divisor = self::divisor($eccLength);

        $all = [];
        for ($i = 0, $k = 0; $i < $blocks; $i++) {
            $length = $shortLength - $eccLength + ($i < $short ? 0 : 1);
            $block = array_slice($data, $k, $length);
            $k += $length;
            $ecc = self::remainder($block, $divisor);
            if ($i < $short) {
                $block[] = 0;
            }
            $all[] = array_merge($block, $ecc);
        }

        $result = [];
        for ($i = 0, $width = count($all[0]); $i < $width; $i++) {
            foreach ($all as $j => $block) {
                if ($i !== $shortLength - $eccLength || $j >= $short) {
                    $result[] = $block[$i];
                }
            }
        }

        return $result;
    }

    /** @return list<int> */
    private static function divisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::multiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::multiply($root, 0x02);
        }

        return $result;
    }

    /**
     * @param list<int> $data
     * @param list<int> $divisor
     * @return list<int>
     */
    private static function remainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::multiply($coefficient, $factor);
            }
        }

        return $result;
    }

    /** Multiplication in GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1. */
    private static function multiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }

        return $z;
    }

    /** @param list<int> $data */
    private function drawCodewords(array $data): void
    {
        $i = 0;
        $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vertical = 0; $vertical < $this->size; $vertical++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vertical : $vertical;
                    if (!$this->function[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    /** Applying a mask twice undoes it. */
    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert && !$this->function[$y][$x]) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function penalty(): int
    {
        $result = 0;
        $columns = [];
        for ($x = 0; $x < $this->size; $x++) {
            $columns[] = array_column($this->modules, $x);
        }
        foreach ([$this->modules, $columns] as $lines) {
            foreach ($lines as $line) {
                $color = false;
                $run = 0;
                $history = array_fill(0, 7, 0);
                foreach ($line as $dark) {
                    if ($dark === $color) {
                        $run++;
                        $result += $run === 5 ? 3 : ($run > 5 ? 1 : 0);
                    } else {
                        $this->addHistory($run, $history);
                        if (!$color) {
                            $result += $this->finderLike($history) * 40;
                        }
                        $color = $dark;
                        $run = 1;
                    }
                }
                if ($color) {
                    $this->addHistory($run, $history);
                    $run = 0;
                }
                $this->addHistory($run + $this->size, $history);
                $result += $this->finderLike($history) * 40;
            }
        }

        $dark = 0;
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $color = $this->modules[$y][$x];
                $dark += $color ? 1 : 0;
                if (
                    $x < $this->size - 1 && $y < $this->size - 1 && $color === $this->modules[$y][$x + 1]
                    && $color === $this->modules[$y + 1][$x] && $color === $this->modules[$y + 1][$x + 1]
                ) {
                    $result += 3;
                }
            }
        }
        $total = $this->size ** 2;
        $result += ((int) ceil(abs($dark * 20 - $total * 10) / $total) - 1) * 10;

        return $result;
    }

    /** @param list<int> $history */
    private function addHistory(int $run, array &$history): void
    {
        if ($history[0] === 0) {
            $run += $this->size;
        }
        array_pop($history);
        array_unshift($history, $run);
    }

    /**
     * Patterns like a finder (dark-light-dark-light-dark in 1:1:3:1:1 with 4 light modules on one side).
     *
     * @param list<int> $history
     */
    private function finderLike(array $history): int
    {
        $n = $history[1];
        $core = $n > 0 && $history[2] === $n && $history[3] === $n * 3 && $history[4] === $n && $history[5] === $n;

        return ($core && $history[0] >= $n * 4 && $history[6] >= $n ? 1 : 0)
            + ($core && $history[6] >= $n * 4 && $history[0] >= $n ? 1 : 0);
    }
}
