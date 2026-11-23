<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormMarkdownTest extends TestCase
{
    public function testANativeTextareaWithItsTexts(): void
    {
        $html = Form::make('f')->submit(null)->floatingLabels()->add(
            Field::markdown('body', 'Body')->required()->value("# Title\n\n<b>bold</b>")->help('Markdown.'),
        )->render();

        self::assertStringContainsString(
            '<div class="mb-3"><label for="f-body" class="form-label">Body <span class="caelrik-required" aria-hidden="true">*</span></label>'
            . '<textarea id="f-body" name="body" required aria-describedby="f-body-help" '
            . 'class="form-control caelrik-markdown-source" rows="8" data-caelrik-markdown="{'
            . '&quot;toolbar&quot;:&quot;Formatting&quot;,&quot;bold&quot;:&quot;Bold&quot;,&quot;italic&quot;:&quot;Italic&quot;,'
            . '&quot;heading&quot;:&quot;Heading&quot;,&quot;link&quot;:&quot;Link&quot;,&quot;bulletList&quot;:&quot;Bulleted list&quot;,'
            . '&quot;numberedList&quot;:&quot;Numbered list&quot;,&quot;quote&quot;:&quot;Quote&quot;,&quot;code&quot;:&quot;Code&quot;,'
            . '&quot;write&quot;:&quot;Write&quot;,&quot;preview&quot;:&quot;Preview&quot;,&quot;empty&quot;:&quot;Nothing to preview.&quot;}">'
            . "# Title\n\n&lt;b&gt;bold&lt;/b&gt;</textarea>"
            . '<div id="f-body-help" class="form-text">Markdown.</div></div>',
            $html,
        );
    }

    public function testLabelsErrorsAndOldInput(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::markdown('body')->markdownLabels(['bold' => 'Gras', 'preview' => 'Aperçu']),
        )->values(['body' => '**Old**'])->errors(['body' => 'Too short.'])->render();

        self::assertStringContainsString('&quot;bold&quot;:&quot;Gras&quot;', $html);
        self::assertStringContainsString('&quot;preview&quot;:&quot;Aperçu&quot;', $html);
        self::assertStringContainsString('class="form-control caelrik-markdown-source is-invalid"', $html);
        self::assertStringContainsString('>**Old**</textarea>', $html);
        self::assertStringContainsString('<div id="f-body-error" class="invalid-feedback caelrik-server-error">Too short.</div>', $html);
    }

    public function testErrors(): void
    {
        foreach (
            [
                static fn () => Field::markdown('b')->markdownLabels(['underline' => 'U']),
                static fn () => Field::textarea('b')->markdownLabels([]),
            ] as $invalid
        ) {
            try {
                $invalid();
                self::fail('An invalid use was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
