<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\PdfViewer;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class PdfViewerTest extends TestCase
{
    public function testARegionWithItsTitleToolbarPagesAndDownloadLink(): void
    {
        $html = PdfViewer::make('report')->url('/files/report.pdf?v=2&lang=en')->label('Annual <report>')->render();

        self::assertStringStartsWith(
            '<section id="report" class="caelrik-pdf caelrik-pdf-md" data-caelrik="pdfviewer" data-caelrik-options="{'
            . '&quot;url&quot;:&quot;/files/report.pdf?v=2&amp;lang=en&quot;,&quot;page&quot;:1,&quot;zoom&quot;:&quot;fit&quot;,'
            . '&quot;labels&quot;:{&quot;of&quot;:&quot;of {total}&quot;,&quot;pageName&quot;:&quot;Page {page} of {total}&quot;,'
            . '&quot;download&quot;:&quot;Download the PDF&quot;,&quot;loading&quot;:&quot;Loading the document…&quot;,'
            . '&quot;error&quot;:&quot;This document could not be opened. Download it instead.&quot;}}" '
            . 'aria-labelledby="report-title"><div class="caelrik-pdf-header">'
            . '<p id="report-title" class="caelrik-pdf-title">Annual &lt;report&gt;</p>'
            . '<a class="caelrik-pdf-download btn btn-sm caelrik-action-button btn-outline-secondary" '
            . 'href="/files/report.pdf?v=2&amp;lang=en" download>Download the PDF</a></div>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="caelrik-pdf-toolbar" role="group" aria-label="Pages and zoom" hidden><div class="caelrik-pdf-tools">'
            . '<button type="button" class="btn btn-sm caelrik-action-button btn-outline-secondary caelrik-pdf-previous" '
            . 'aria-label="Previous page"><span aria-hidden="true">‹</span></button>'
            . '<label for="report-page" class="caelrik-pdf-page-label">Page</label>'
            . '<input type="number" id="report-page" class="form-control form-control-sm caelrik-pdf-page-input" min="1" '
            . 'value="1" inputmode="numeric"><span class="caelrik-pdf-total"></span>',
            $html,
        );
        self::assertStringContainsString(
            '<select class="form-select form-select-sm caelrik-pdf-zoom" aria-label="Zoom">'
            . '<option value="fit" selected>Fit width</option><option value="50">50%</option>',
            $html,
        );
        self::assertStringEndsWith(
            '<div class="caelrik-pdf-viewport" role="region" aria-label="Annual &lt;report&gt;, pages" tabindex="0">'
            . '<p class="caelrik-pdf-message">The document is shown here with JavaScript. Download it instead. '
            . '<a class="caelrik-pdf-download" href="/files/report.pdf?v=2&amp;lang=en" download>Download the PDF</a></p></div>'
            . '<p class="visually-hidden caelrik-pdf-status" role="status"></p></section>',
            $html,
        );
        // No inline style: a strict Content Security Policy blocks style attributes.
        self::assertStringNotContainsString('style=', $html);
    }

    public function testOptions(): void
    {
        $html = PdfViewer::make('doc')->url('https://example.com/a.pdf')->label('Doc')->page(3)->zoom(150)->size('lg')
            ->download(false)->labels(['download' => 'Télécharger le PDF', 'zoom' => 'Agrandissement'])->render();

        self::assertStringContainsString('class="caelrik-pdf caelrik-pdf-lg"', $html);
        self::assertStringContainsString('&quot;page&quot;:3,&quot;zoom&quot;:150', $html);
        self::assertStringContainsString('<option value="150" selected>150%</option>', $html);
        self::assertStringContainsString('value="3" inputmode="numeric"', $html);
        self::assertStringContainsString('aria-label="Agrandissement"', $html);
        // Without the header link, the download stays in the messages.
        self::assertSame(1, substr_count($html, '>Télécharger le PDF</a>'));
        self::assertStringNotContainsString('btn-outline-secondary" href=', $html);
    }

    public function testOnlyWebAddresses(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PdfViewer::make()->url('javascript:alert(1)');
    }

    public function testAUrlAndALabelAreNeeded(): void
    {
        foreach (
            [
                [PdfViewer::make('a')->label('A'), 'PDF viewer "a" needs a url().'],
                [PdfViewer::make('b')->url('/b.pdf'), 'PDF viewer "b" needs a label.'],
            ] as [$viewer, $message]
        ) {
            try {
                $viewer->render();
                self::fail('A viewer rendered without what it needs.');
            } catch (LogicException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testInvalidOptions(): void
    {
        foreach (
            [
                static fn () => PdfViewer::make()->page(0),
                static fn () => PdfViewer::make()->zoom(110),
                static fn () => PdfViewer::make()->zoom('page'),
                static fn () => PdfViewer::make()->size('xl'),
                static fn () => PdfViewer::make()->labels(['title' => 'x']),
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
