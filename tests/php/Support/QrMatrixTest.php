<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Support;

use Caelrik\Ui\Support\QrMatrix;
use InvalidArgumentException;
use LengthException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The structure of the codes. Every version and level is also decoded by ZXing, an independent
 * reader, in tests/js/codes.test.js.
 */
final class QrMatrixTest extends TestCase
{
    public function testFinderTimingAndDarkModule(): void
    {
        $matrix = QrMatrix::encode('HELLO WORLD', 'Q');
        $size = count($matrix);
        self::assertSame(21, $size);

        // The three finders: a dark ring, a light ring and a dark 3×3 core, with a light separator.
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$left, $top]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    if (!isset($matrix[$top + $y][$left + $x])) {
                        continue;
                    }
                    $distance = max(abs($x - 3), abs($y - 3));
                    self::assertSame($distance !== 2 && $distance !== 4, $matrix[$top + $y][$left + $x]);
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            self::assertSame($i % 2 === 0, $matrix[6][$i]);
            self::assertSame($i % 2 === 0, $matrix[$i][6]);
        }
        self::assertTrue($matrix[$size - 8][8]);
    }

    public function testTheFormatBitsGiveTheLevelAndTheMask(): void
    {
        foreach (['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2] as $level => $bits) {
            foreach (range(0, 7) as $mask) {
                $matrix = QrMatrix::encode('caelrik', $level, mask: $mask);
                $format = 0;
                // The first copy, bits 0 to 14, around the top-left finder.
                $cells = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8],
                    [3, 8], [2, 8], [1, 8], [0, 8]];
                foreach ($cells as $i => [$x, $y]) {
                    $format |= ($matrix[$y][$x] ? 1 : 0) << $i;
                }
                self::assertSame($bits << 3 | $mask, ($format ^ 0x5412) >> 10, "$level, mask $mask");
            }
        }
    }

    public function testTheVersionInformation(): void
    {
        // Version 7 is written as 000111 110010 010100 (ISO/IEC 18004, annex D).
        $matrix = QrMatrix::encode('caelrik', 'M', 7);
        $size = count($matrix);
        self::assertSame(45, $size);
        $bits = 0;
        for ($i = 0; $i < 18; $i++) {
            $bits |= ($matrix[intdiv($i, 3)][$size - 11 + $i % 3] ? 1 : 0) << $i;
            self::assertSame($matrix[intdiv($i, 3)][$size - 11 + $i % 3], $matrix[$size - 11 + $i % 3][intdiv($i, 3)]);
        }
        self::assertSame(0x07C94, $bits);
    }

    public function testAlignmentPatterns(): void
    {
        // Version 32: centers at 6, 34, 60, 86, 112 and 138.
        $matrix = QrMatrix::encode('caelrik', 'L', 32);
        foreach ([[34, 60], [86, 112], [138, 138], [60, 6]] as [$x, $y]) {
            self::assertTrue($matrix[$y][$x]);
            self::assertFalse($matrix[$y][$x + 1]);
            self::assertFalse($matrix[$y - 1][$x - 1]);
            self::assertTrue($matrix[$y + 2][$x - 2]);
        }
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function capacities(): iterable
    {
        // The capacities of version 1 and the next character.
        yield 'numeric, L' => [str_repeat('7', 41), 'L', 1];
        yield 'numeric, L, one more' => [str_repeat('7', 42), 'L', 2];
        yield 'alphanumeric, M' => [str_repeat('A', 20), 'M', 1];
        yield 'alphanumeric, M, one more' => [str_repeat('A', 21), 'M', 2];
        yield 'bytes, Q' => [str_repeat('a', 11), 'Q', 1];
        yield 'bytes, Q, one more' => [str_repeat('a', 12), 'Q', 2];
        yield 'UTF-8, H' => [str_repeat('é', 3), 'H', 1];
        yield 'UTF-8, H, one more' => [str_repeat('é', 4), 'H', 2];
        yield 'bytes, L, version 40' => [str_repeat('a', 2953), 'L', 40];
        yield 'bytes, H, version 40' => [str_repeat('a', 1273), 'H', 40];
    }

    #[DataProvider('capacities')]
    public function testTheSmallestVersionThatHoldsTheText(string $text, string $level, int $version): void
    {
        self::assertCount($version * 4 + 17, QrMatrix::encode($text, $level));
    }

    public function testATextTooLong(): void
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('The text is too long for a QR code at level H (1273 bytes at most).');
        QrMatrix::encode(str_repeat('a', 1274), 'H');
    }

    public function testATextTooLongForAFixedVersion(): void
    {
        $this->expectException(LengthException::class);
        QrMatrix::encode(str_repeat('a', 18), 'L', 1);
    }

    public function testTheSameTextGivesTheSameCode(): void
    {
        self::assertSame(QrMatrix::encode('https://example.com'), QrMatrix::encode('https://example.com'));
        self::assertNotSame(QrMatrix::encode('https://example.com'), QrMatrix::encode('https://example.org'));
    }

    /**
     * @return iterable<string, array{string, ?int, ?int}>
     */
    public static function invalid(): iterable
    {
        yield 'level' => ['X', null, null];
        yield 'version 0' => ['M', 0, null];
        yield 'version 41' => ['M', 41, null];
        yield 'mask 8' => ['M', null, 8];
    }

    #[DataProvider('invalid')]
    public function testInvalidOptions(string $level, ?int $version, ?int $mask): void
    {
        $this->expectException(InvalidArgumentException::class);
        QrMatrix::encode('a', $level, $version, $mask);
    }
}
