<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormCodeTest extends TestCase
{
    public function testATextareaForTheEditor(): void
    {
        $html = Form::make('f')->submit(null)->floatingLabels()->add(
            Field::code('source', 'Source')->language('php')->required()->value("<?php\necho 'Hi';")->help('PHP 8.'),
        )->render();

        self::assertStringContainsString(
            '<div class="mb-3"><label for="f-source" id="f-source-label" class="form-label">Source '
            . '<span class="celerik-required" aria-hidden="true">*</span></label>'
            . '<textarea id="f-source" name="source" required aria-describedby="f-source-help" '
            . 'class="form-control font-monospace celerik-code-source" rows="10" spellcheck="false" autocapitalize="off" '
            . 'autocomplete="off" wrap="off" data-celerik-code="{&quot;language&quot;:&quot;php&quot;,&quot;tabIndents&quot;:false,'
            . '&quot;keys&quot;:&quot;Tab moves to the next field; Ctrl+] indents and Ctrl+[ outdents.&quot;}">'
            . "&lt;?php\necho &apos;Hi&apos;;</textarea>"
            . '<div id="f-source-help" class="form-text">PHP 8.</div></div>',
            $html,
        );
    }

    public function testTabIndentsLabelsAndErrors(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::code('config')->language('json')->tabIndents()
                ->codeLabels(['tabKeys' => 'Tab indente ; Échap puis Tab pour sortir.']),
        )->errors(['config' => 'Invalid JSON.'])->render();

        self::assertStringContainsString(
            'data-celerik-code="{&quot;language&quot;:&quot;json&quot;,&quot;tabIndents&quot;:true,'
            . '&quot;keys&quot;:&quot;Tab indente ; Échap puis Tab pour sortir.&quot;}"',
            $html,
        );
        self::assertStringContainsString('celerik-code-source is-invalid"', $html);
        self::assertStringContainsString('<div id="f-config-error" class="invalid-feedback celerik-server-error">Invalid JSON.</div>', $html);
        // Text by default.
        self::assertStringContainsString(
            '&quot;language&quot;:&quot;text&quot;',
            Form::make('g')->submit(null)->add(Field::code('notes'))->render(),
        );
    }

    public function testErrors(): void
    {
        foreach (
            [
                static fn () => Field::code('c')->language('cobol'),
                static fn () => Field::code('c')->codeLabels(['title' => 'x']),
                static fn () => Field::textarea('c')->language('php'),
                static fn () => Field::textarea('c')->tabIndents(),
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
