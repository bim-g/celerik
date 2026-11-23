<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\QrCode;
use Caelrik\Ui\Support\QrMatrix;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class QrCodeTest extends TestCase
{
    public function testAnImageNamedByItsLabelAndTheValueAsALink(): void
    {
        $html = QrCode::make('site')->value('https://example.com/?a=1&b=2')->label('Our <website>')->render();

        self::assertStringStartsWith(
            '<figure id="site" class="caelrik-qrcode" data-caelrik="qrcode" data-caelrik-options="{}">'
            . '<svg class="caelrik-qrcode-image" role="img" aria-label="QR code: Our &lt;website&gt;" viewBox="0 0 37 37" '
            . 'width="160" height="160" shape-rendering="crispEdges" focusable="false">'
            . '<rect class="caelrik-qrcode-light" width="37" height="37"></rect>'
            // The top row of the top-left finder, inside the quiet zone of four modules.
            . '<path class="caelrik-qrcode-dark" d="M4 4h7v1h-7z',
            $html,
        );
        self::assertStringEndsWith(
            '</svg><figcaption class="caelrik-qrcode-caption"><span class="caelrik-qrcode-label">Our &lt;website&gt;</span>'
            . '<a class="caelrik-qrcode-value" href="https://example.com/?a=1&amp;b=2">https://example.com/?a=1&amp;b=2</a>'
            . '</figcaption></figure>',
            $html,
        );
    }

    public function testWithoutALabelTheValueNamesTheImage(): void
    {
        $html = QrCode::make('phone')->value('tel:+243999999999')->render();

        self::assertStringContainsString('aria-label="QR code: tel:+243999999999"', $html);
        // Only web addresses become links.
        self::assertStringContainsString(
            '<figcaption class="caelrik-qrcode-caption"><span class="caelrik-qrcode-value">tel:+243999999999</span>',
            $html,
        );
        self::assertStringNotContainsString('caelrik-qrcode-label', $html);
    }

    public function testAScriptIsNeverALink(): void
    {
        $html = QrCode::make('x')->value('javascript:alert(1)//https://a')->render();

        self::assertStringNotContainsString('href', $html);
        self::assertStringContainsString('<span class="caelrik-qrcode-value">javascript:alert(1)//https://a</span>', $html);
    }

    public function testAHiddenValue(): void
    {
        $html = QrCode::make('wifi')->value('WIFI:T:WPA;S:Office;P:secret;;')->label('Office Wi-Fi')->hideValue()
            ->render();

        self::assertStringContainsString('aria-label="QR code: Office Wi-Fi"', $html);
        self::assertStringNotContainsString('secret', $html);
        self::assertStringNotContainsString('caelrik-qrcode-value', $html);
    }

    public function testLevelSizeAndLabels(): void
    {
        $low = QrCode::make('a')->value(str_repeat('x', 30))->level('L')->size(240)->render();
        $high = QrCode::make('b')->value(str_repeat('x', 30))->level('H')->labels(['image' => 'Code QR : {label}'])
            ->render();

        // 30 bytes: version 2 at level L, version 4 at level H.
        self::assertStringContainsString('viewBox="0 0 33 33" width="240" height="240"', $low);
        self::assertStringContainsString(
            'aria-label="Code QR : xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" viewBox="0 0 41 41"',
            $high,
        );
    }

    public function testTheModulesAreDrawnAsRuns(): void
    {
        $html = QrCode::make('a')->value('caelrik')->render();
        preg_match('/ d="([^"]+)"/', $html, $path);
        preg_match_all('/h(\d+)v1/', $path[1], $runs);
        $matrix = QrMatrix::encode('caelrik');

        self::assertSame(
            array_sum(array_map(static fn (array $row): int => count(array_filter($row)), $matrix)),
            array_sum(array_map('intval', $runs[1])),
        );
    }

    public function testAValueIsNeeded(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('QR code "empty" needs a value.');
        QrCode::make('empty')->render();
    }

    public function testAHiddenValueNeedsALabel(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('QR code "token" hides its value, so it needs a label.');
        QrCode::make('token')->value('abc')->hideValue()->render();
    }

    public function testAValueTooLong(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'QR code "long": The text is too long for a QR code at level M (2331 bytes at most).',
        );
        QrCode::make('long')->value(str_repeat('a', 2400))->render();
    }

    public function testInvalidOptions(): void
    {
        foreach (
            [
                static fn () => QrCode::make()->level('X'),
                static fn () => QrCode::make()->size(20),
                static fn () => QrCode::make()->labels(['caption' => 'x']),
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
