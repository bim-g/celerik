<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Barcode;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * Every type is also decoded by ZXing, an independent reader, in tests/js/codes.test.js.
 */
final class BarcodeTest extends TestCase
{
    private const START_B = '11010010000';
    private const START_C = '11010011100';
    private const STOP = '1100011101011';

    public function testCode128(): void
    {
        [$modules, $text] = Barcode::modules('Hi');

        self::assertSame('Hi', $text);
        // Start B, H (40), i (73), the check symbol ((104 + 40 + 73 × 2) % 103 = 84) and the stop.
        self::assertSame(self::START_B . '11000101000' . '10000110100' . '10011110100' . self::STOP, $modules);
    }

    public function testCode128PacksDigitsTwoByTwo(): void
    {
        // Start C, five pairs, the check symbol and the stop.
        [$digits] = Barcode::modules('1234567890');
        self::assertStringStartsWith(self::START_C, $digits);
        self::assertSame(11 * 7 + 13, strlen($digits));

        // Start B, A B C 1, Code C, 23 45 67 89, Code B, x y z, the check symbol and the stop.
        [$mixed] = Barcode::modules('ABC123456789xyz');
        self::assertStringStartsWith(self::START_B, $mixed);
        self::assertSame(11 * 15 + 13, strlen($mixed));

        // A short run inside the text stays in set B: start B, 1 2 3 4 5 and a space, the check
        // symbol and the stop.
        self::assertSame(11 * 8 + 13, strlen(Barcode::modules('12345 ')[0]));
    }

    public function testEveryCode128SymbolIsElevenModules(): void
    {
        $patterns = (new ReflectionClassConstant(Barcode::class, 'CODE128'))->getValue();
        self::assertIsArray($patterns);
        self::assertCount(107, $patterns);
        self::assertCount(107, array_unique($patterns));
        foreach ($patterns as $code => $pattern) {
            self::assertSame($code === 106 ? 13 : 11, array_sum(array_map('intval', str_split($pattern))), "symbol $code");
        }
    }

    public function testCode128HoldsAsciiOnly(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Code 128 holds ASCII characters only.');
        Barcode::modules('café');
    }

    public function testEan13AddsTheCheckDigit(): void
    {
        [$modules, $text] = Barcode::modules('978020137962', 'ean13');

        self::assertSame('9780201379624', $text);
        self::assertSame(95, strlen($modules));
        self::assertStringStartsWith('101', $modules);
        self::assertSame('01010', substr($modules, 45, 5));
        self::assertStringEndsWith('101', $modules);
        // The first digit (9) sets the left digits to L G G L G L: 7 in L, 8 in G.
        self::assertSame('0111011' . '0001001', substr($modules, 3, 14));
        // The check digit 4, in R.
        self::assertSame('1011100', substr($modules, 85, 7));
        self::assertSame($modules, Barcode::modules('9780201379624', 'ean13')[0]);
    }

    public function testEan13ChecksTheCheckDigit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The check digit of EAN-13 "9780201379620" is wrong: it should be 4.');
        Barcode::modules('9780201379620', 'ean13');
    }

    public function testEan13IsDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An EAN-13 is 12 digits, or 13 with its check digit.');
        Barcode::modules('97802013796X', 'ean13');
    }

    public function testRender(): void
    {
        $html = Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN <book>')->render();

        // 11 + 95 + 7 modules, two pixels each.
        self::assertStringStartsWith(
            '<figure id="isbn" class="caelrik-barcode" data-caelrik="barcode" data-caelrik-options="{}">'
            . '<svg class="caelrik-barcode-image" role="img" aria-label="Barcode: ISBN &lt;book&gt;" '
            . 'viewBox="0 0 113 40" width="226" height="80" preserveAspectRatio="none" shape-rendering="crispEdges" '
            . 'focusable="false"><rect class="caelrik-barcode-light" width="113" height="40"></rect>'
            . '<path class="caelrik-barcode-dark" d="M11 0h1v40h-1zM13 0h1v40h-1z',
            $html,
        );
        self::assertStringEndsWith(
            '</svg><figcaption class="caelrik-barcode-caption"><span class="caelrik-barcode-label">ISBN &lt;book&gt;</span>'
            . '<span class="caelrik-barcode-value">9780201379624</span></figcaption></figure>',
            $html,
        );
    }

    public function testHeightScaleAndLabels(): void
    {
        $html = Barcode::make('order')->value('A-42')->height(50)->scale(3)
            ->labels(['image' => 'Code-barres : {label}'])->render();

        // 10 + 11 × 6 + 13 + 10 modules (start, A, -, 4, 2, check, stop), three pixels each.
        self::assertStringContainsString(
            'aria-label="Code-barres : A-42" viewBox="0 0 99 16.67" width="297" height="50"',
            $html,
        );
    }

    public function testAHiddenValue(): void
    {
        $html = Barcode::make('ticket')->value('TICKET-0001')->label('Your ticket')->hideValue()->render();

        self::assertStringContainsString('aria-label="Barcode: Your ticket"', $html);
        self::assertStringNotContainsString('caelrik-barcode-value', $html);
    }

    public function testAValueIsNeeded(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Barcode "empty" needs a value.');
        Barcode::make('empty')->render();
    }

    public function testAHiddenValueNeedsALabel(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Barcode "code" hides its value, so it needs a label.');
        Barcode::make('code')->value('1')->hideValue()->render();
    }

    public function testInvalidOptions(): void
    {
        foreach (
            [
                static fn () => Barcode::make()->type('qr'),
                static fn () => Barcode::make()->height(10),
                static fn () => Barcode::make()->scale(5),
                static fn () => Barcode::make()->scale(0),
                static fn () => Barcode::make()->labels(['caption' => 'x']),
                static fn () => Barcode::modules('1', 'upc'),
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
