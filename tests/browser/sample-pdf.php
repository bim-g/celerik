<?php

/**
 * Writes a small PDF of text pages (Helvetica, A4), for the PDF viewer's tests and demo:
 *
 *     php tests/browser/sample-pdf.php site/public/files/sample.pdf
 */

declare(strict_types=1);

/**
 * @param list<array{title: string, lines: list<string>}> $pages
 */
function caelrik_sample_pdf(string $title, array $pages): string
{
    $escape = static fn (string $text): string => strtr($text, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']);
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objects[5] = sprintf('<< /Title (%s) /Producer (Caelrik UI tests) >>', $escape($title));
    $kids = [];
    $next = 6;
    foreach ($pages as $page) {
        $content = sprintf("BT /F2 24 Tf 72 760 Td (%s) Tj ET\n", $escape($page['title']));
        $y = 720;
        foreach ($page['lines'] as $line) {
            $content .= sprintf("BT /F1 12 Tf 72 %d Td (%s) Tj ET\n", $y, $escape($line));
            $y -= 18;
        }
        $content .= "0.2 0.4 0.8 rg 72 120 451 4 re f\n";
        [$pageId, $contentId] = [$next, $next + 1];
        $next += 2;
        $objects[$pageId] = sprintf(
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> '
            . '/Contents %d 0 R >>',
            $contentId,
        );
        $objects[$contentId] = sprintf("<< /Length %d >>\nstream\n%sendstream", strlen($content), $content);
        $kids[] = $pageId . ' 0 R';
    }
    $objects[2] = sprintf('<< /Type /Pages /Kids [%s] /Count %d >>', implode(' ', $kids), count($kids));
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $id, $object);
    }
    $xref = strlen($pdf);
    $pdf .= sprintf("xref\n0 %d\n0000000000 65535 f \n", count($objects) + 1);
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf . sprintf(
        "trailer\n<< /Size %d /Root 1 0 R /Info 5 0 R >>\nstartxref\n%d\n%%%%EOF\n",
        count($objects) + 1,
        $xref,
    );
}

function caelrik_sample_report(): string
{
    $pages = [];
    $topics = ['Summary', 'Sales by region', 'Outlook'];
    foreach ($topics as $index => $topic) {
        $lines = [];
        for ($line = 1; $line <= 24; $line++) {
            $lines[] = sprintf('%s, line %d: Caelrik UI draws this page with PDF.js.', $topic, $line);
        }
        $pages[] = ['title' => sprintf('Page %d. %s', $index + 1, $topic), 'lines' => $lines];
    }

    return caelrik_sample_pdf('Annual report 2026', $pages);
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $target = $argv[1] ?? 'sample.pdf';
    file_put_contents($target, caelrik_sample_report());
    echo "Wrote $target\n";
}
