<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use Caelrik\Ui\Support\Url;
use InvalidArgumentException;
use LogicException;

/**
 * A PDF viewer (PDF.js, loaded only by pages that use it): the pages drawn in a scrolling
 * area, with a text layer that screen readers read and the browser's search finds, a toolbar
 * (previous and next page, a page number, zoom) and a download link, always there.
 * - The viewer is a region named by its label; the download link is the way to open the
 *   document in another reader (a screen reader's own PDF support, a tagged PDF), and the
 *   fallback without JavaScript.
 * - No inline styles: the pages are sized by the CSSOM.
 *
 *     PdfViewer::make('report')->url('/files/report.pdf')->label('Annual report 2026');
 */
final class PdfViewer extends Component
{
    public const SIZES = ['sm', 'md', 'lg'];
    public const ZOOMS = ['fit', 50, 75, 100, 125, 150, 200, 300];

    public const LABELS = [
        'toolbar' => 'Pages and zoom',
        'previous' => 'Previous page',
        'next' => 'Next page',
        'page' => 'Page',
        'of' => 'of {total}',
        'zoomOut' => 'Zoom out',
        'zoomIn' => 'Zoom in',
        'zoom' => 'Zoom',
        'fit' => 'Fit width',
        'pages' => '{label}, pages',
        'pageName' => 'Page {page} of {total}',
        'download' => 'Download the PDF',
        'loading' => 'Loading the document…',
        'error' => 'This document could not be opened. Download it instead.',
        'noscript' => 'The document is shown here with JavaScript. Download it instead.',
    ];

    private ?string $url = null;
    private string $label = '';
    private int $page = 1;
    private string|int $zoom = 'fit';
    private string $size = 'md';
    private bool $download = true;

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'pdfviewer';
    }

    /**
     * The document: a relative, http or https URL, on the same origin or served with CORS.
     */
    public function url(string $url): self
    {
        $safe = Url::safe($url);
        if ($safe === null) {
            throw new InvalidArgumentException('A PDF viewer takes a relative, http or https URL.');
        }
        $this->url = $safe;

        return $this;
    }

    /**
     * The document's title ("Annual report 2026"): it names the viewer.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * The page shown first.
     */
    public function page(int $page): self
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Pages are numbered from 1.');
        }
        $this->page = $page;

        return $this;
    }

    /**
     * "fit" (the width of the viewer, default) or a percentage: 50, 75, 100, 125, 150, 200, 300.
     */
    public function zoom(string|int $zoom): self
    {
        if (!in_array($zoom, self::ZOOMS, true)) {
            throw new InvalidArgumentException(sprintf('Unknown zoom "%s".', $zoom));
        }
        $this->zoom = $zoom;

        return $this;
    }

    /**
     * The height of the viewer: sm (24rem), md (40rem, default) or lg (60rem), never more than
     * the screen.
     */
    public function size(string $size): self
    {
        if (!in_array($size, self::SIZES, true)) {
            throw new InvalidArgumentException(sprintf('Unknown PDF viewer size "%s".', $size));
        }
        $this->size = $size;

        return $this;
    }

    /**
     * Whether the download link is shown (yes by default). Without it, the document still
     * downloads from the error and no-JavaScript messages.
     */
    public function download(bool $download = true): self
    {
        $this->download = $download;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown PDF viewer label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'page' => $this->page,
            'zoom' => $this->zoom,
            'labels' => array_intersect_key($this->labels, array_flip([
                'pageName', 'of', 'loading', 'error', 'download',
            ])),
        ];
    }

    public function render(): string
    {
        if ($this->url === null) {
            throw new LogicException(sprintf('PDF viewer "%s" needs a url().', $this->id));
        }
        if (trim($this->label) === '') {
            throw new LogicException(sprintf('PDF viewer "%s" needs a label.', $this->id));
        }
        $labels = $this->labels;
        $titleId = $this->id . '-title';
        $button = fn (string $part, string $text, string $icon): string => Html::tag('button', [
            'type' => 'button',
            'class' => 'btn btn-sm caelrik-action-button btn-outline-secondary caelrik-pdf-' . $part,
            'aria-label' => $text,
        ], Html::tag('span', ['aria-hidden' => 'true'], $icon));

        $header = Html::tag('div', ['class' => 'caelrik-pdf-header'], Html::tag(
            'p',
            ['id' => $titleId, 'class' => 'caelrik-pdf-title'],
            Html::escape($this->label),
        ) . ($this->download ? $this->downloadLink('btn btn-sm caelrik-action-button btn-outline-secondary') : ''));

        $zooms = '';
        foreach (self::ZOOMS as $zoom) {
            $zooms .= Html::tag('option', [
                'value' => (string) $zoom,
                'selected' => $zoom === $this->zoom,
            ], $zoom === 'fit' ? Html::escape($labels['fit']) : $zoom . '%');
        }
        $pageId = $this->id . '-page';
        $toolbar = Html::tag('div', [
            'class' => 'caelrik-pdf-toolbar',
            'role' => 'group',
            'aria-label' => $labels['toolbar'],
            'hidden' => true,
        ], Html::tag('div', ['class' => 'caelrik-pdf-tools'], $button('previous', $labels['previous'], '‹')
            . Html::tag('label', ['for' => $pageId, 'class' => 'caelrik-pdf-page-label'], Html::escape($labels['page']))
            . Html::tag('input', [
                'type' => 'number',
                'id' => $pageId,
                'class' => 'form-control form-control-sm caelrik-pdf-page-input',
                'min' => 1,
                'value' => $this->page,
                'inputmode' => 'numeric',
            ])
            . Html::tag('span', ['class' => 'caelrik-pdf-total'], '')
            . $button('next', $labels['next'], '›'))
            . Html::tag('div', ['class' => 'caelrik-pdf-tools'], $button('zoom-out', $labels['zoomOut'], '−')
            . Html::tag('select', [
                'class' => 'form-select form-select-sm caelrik-pdf-zoom',
                'aria-label' => $labels['zoom'],
            ], $zooms)
            . $button('zoom-in', $labels['zoomIn'], '+')));

        $viewport = Html::tag('div', [
            'class' => 'caelrik-pdf-viewport',
            'role' => 'region',
            'aria-label' => strtr($labels['pages'], ['{label}' => $this->label]),
            'tabindex' => 0,
        ], Html::tag('p', ['class' => 'caelrik-pdf-message'], Html::escape($labels['noscript']) . ' '
            . $this->downloadLink('')));

        return Html::tag(
            'section',
            array_merge($this->rootAttributes(['caelrik-pdf', 'caelrik-pdf-' . $this->size]), [
                'aria-labelledby' => $titleId,
            ]),
            $header . $toolbar . $viewport
                . Html::tag('p', ['class' => 'visually-hidden caelrik-pdf-status', 'role' => 'status'], ''),
        );
    }

    private function downloadLink(string $class): string
    {
        return Html::tag('a', [
            'class' => Html::classes(['caelrik-pdf-download', $class]),
            'href' => $this->url,
            'download' => true,
        ], Html::escape($this->labels['download']));
    }
}
