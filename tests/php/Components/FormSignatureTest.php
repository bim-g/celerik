<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Form\Repeater;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormSignatureTest extends TestCase
{
    /** A 1×1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    public function testAFieldsetWithThePadTheNameInputAndTheImage(): void
    {
        $html = Form::make('f')->submit(null)->floatingLabels()->add(
            Field::signature('signature', 'Your signature')->required()->help('It is stored with the contract.'),
        )->render();

        self::assertStringContainsString(
            '<div class="mb-3"><fieldset id="f-signature-group" class="caelrik-signature" '
            . 'data-caelrik-signature="{&quot;empty&quot;:&quot;Signature pad, empty&quot;,&quot;signed&quot;:&quot;Signature pad, signed&quot;}">'
            . '<legend class="form-label fs-6">Your signature <span class="caelrik-required" aria-hidden="true">*</span></legend>'
            . '<div class="caelrik-signature-pad" hidden>'
            . '<p id="f-signature-draw" class="form-text mt-0 mb-1">Draw your signature with a mouse, a finger or a pen, or type your name.</p>'
            . '<div class="caelrik-signature-surface"><canvas class="caelrik-signature-canvas" role="img" aria-label="Signature pad, empty"></canvas></div>'
            . '<button type="button" class="btn btn-sm caelrik-action-button btn-outline-secondary caelrik-signature-clear">Clear the signature</button></div>'
            . '<label for="f-signature" class="form-label">Type your name</label>'
            . '<input type="text" id="f-signature" name="signature" required autocomplete="name" spellcheck="false" '
            . 'aria-describedby="f-signature-help" data-parsley-error-message="Draw or type your signature." '
            . 'class="form-control caelrik-signature-typed">'
            . '<input type="hidden" id="f-signature-image" class="caelrik-signature-value"></fieldset>'
            . '<div id="f-signature-help" class="form-text">It is stored with the contract.</div></div>',
            $html,
        );
        // No inline style: a strict Content Security Policy blocks style attributes.
        self::assertStringNotContainsString('style=', $html);
    }

    public function testASubmittedImageGoesToTheHiddenInputAndATypedNameToTheNameInput(): void
    {
        $image = 'data:image/png;base64,' . self::PNG;
        $drawn = Form::make('f')->submit(null)->add(Field::signature('signature'))
            ->values(['signature' => $image])->errors(['signature' => 'Sign again, please.'])->render();

        self::assertStringContainsString(
            '<input type="hidden" id="f-signature-image" class="caelrik-signature-value" value="' . $image . '">',
            $drawn,
        );
        self::assertStringContainsString(
            'aria-describedby="f-signature-error" aria-invalid="true" data-parsley-error-message="Draw or type your signature." '
            . 'class="form-control caelrik-signature-typed is-invalid">',
            $drawn,
        );
        self::assertStringContainsString(
            '<div id="f-signature-error" class="invalid-feedback d-block caelrik-server-error">Sign again, please.</div>',
            $drawn,
        );

        $typed = Form::make('f')->submit(null)->add(Field::signature('signature'))
            ->values(['signature' => 'Ada <Lovelace>'])->render();
        self::assertStringContainsString('name="signature" value="Ada &lt;Lovelace&gt;"', $typed);
        self::assertStringContainsString('<input type="hidden" id="f-signature-image" class="caelrik-signature-value">', $typed);
    }

    public function testLabelsAndMessage(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::signature('signature', 'Signature')->signatureLabels([
                'draw' => 'Signez avec la souris, le doigt ou un stylet, ou tapez votre nom.',
                'type' => 'Tapez votre nom',
                'clear' => 'Effacer la signature',
                'empty' => 'Zone de signature, vide',
                'signed' => 'Zone de signature, signée',
            ])->message('Signez ou tapez votre nom.'),
        )->render();

        foreach (
            [
                'Signez avec la souris, le doigt ou un stylet, ou tapez votre nom.</p>',
                '<label for="f-signature" class="form-label">Tapez votre nom</label>',
                'caelrik-signature-clear">Effacer la signature</button>',
                'aria-label="Zone de signature, vide"',
                '&quot;signed&quot;:&quot;Zone de signature, signée&quot;',
                'data-parsley-error-message="Signez ou tapez votre nom."',
            ] as $text
        ) {
            self::assertStringContainsString($text, $html);
        }
    }

    public function testSignatureImageDecodesOnlyAPng(): void
    {
        self::assertSame(base64_decode(self::PNG), Field::signatureImage('data:image/png;base64,' . self::PNG));

        self::assertNull(Field::signatureImage(null));
        self::assertNull(Field::signatureImage('Ada Lovelace'));
        self::assertNull(Field::signatureImage(['data:image/png;base64,' . self::PNG]));
        // Not a PNG, whatever the data URL says.
        self::assertNull(Field::signatureImage('data:image/png;base64,' . base64_encode('<svg onload="alert(1)"/>')));
        self::assertNull(Field::signatureImage('data:image/svg+xml;base64,' . self::PNG));
        // Not base64.
        self::assertNull(Field::signatureImage('data:image/png;base64,***'));
        // Too large.
        self::assertNull(Field::signatureImage('data:image/png;base64,' . self::PNG, 50));
    }

    public function testErrors(): void
    {
        foreach (
            [
                static fn () => Field::signature('s')->signatureLabels(['title' => 'x']),
                static fn () => Field::text('s')->signatureLabels([]),
                static fn () => Repeater::make('rows')->schema(Field::signature('s')),
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
