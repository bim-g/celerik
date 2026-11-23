<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Form\Repeater;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormOtpTest extends TestCase
{
    public function testRendersOneInputForTheWholeCode(): void
    {
        $html = Form::make('f')->submit(null)->floatingLabels()->add(
            Field::otp('code', 'Verification code')->required()->help('We sent it by SMS.'),
        )->render();

        self::assertStringContainsString(
            '<div class="mb-3"><label for="f-code" class="form-label">Verification code <span class="caelrik-required" aria-hidden="true">*</span></label>'
            . '<input id="f-code" name="code" required aria-describedby="f-code-help" data-parsley-error-message="Enter the 6-digit code."'
            . ' type="text" inputmode="numeric" autocomplete="one-time-code"'
            . ' autocapitalize="off" spellcheck="false" maxlength="6" pattern="[0-9]{6}" class="form-control caelrik-otp-input" size="6" data-caelrik-otp="6">',
            $html,
        );
        // No inline style: a strict Content Security Policy blocks style attributes.
        self::assertStringNotContainsString('style=', $html);
    }

    public function testLengthLettersOldInputAndErrors(): void
    {
        $html = Form::make('f')->submit(null)
            ->add(Field::otp('code')->codeLength(8)->alphanumeric())
            ->values(['code' => 'K7M2QX9A'])
            ->errors(['code' => 'This code has expired.'])
            ->render();

        self::assertStringContainsString(
            'value="K7M2QX9A" inputmode="text" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" maxlength="8" pattern="[A-Za-z0-9]{8}"'
            . ' class="form-control caelrik-otp-input is-invalid" size="8" data-caelrik-otp="8">',
            $html,
        );
        self::assertStringContainsString('<div id="f-code-error" class="invalid-feedback caelrik-server-error">This code has expired.</div>', $html);
        self::assertStringContainsString('data-parsley-error-message="Enter the 8-character code."', $html);

        // message() replaces the default message, e.g. to translate it.
        $french = Form::make('f')->submit(null)->add(Field::otp('code')->message('Saisissez le code à 6 chiffres.'))->render();
        self::assertStringContainsString('data-parsley-error-message="Saisissez le code à 6 chiffres."', $french);
    }

    public function testInARepeaterRow(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Repeater::make('devices')->schema(Field::text('name'), Field::otp('pin')->codeLength(4)),
        )->render();

        self::assertStringContainsString('id="f-devices-0-pin" name="devices[0][pin]"', $html);
        self::assertStringContainsString('pattern="[0-9]{4}"', $html);
    }

    public function testOptionsAreValidated(): void
    {
        $cases = [
            static fn () => Field::otp('code')->codeLength(3),
            static fn () => Field::otp('code')->codeLength(11),
            static fn () => Field::text('code')->codeLength(6),
            static fn () => Field::text('code')->alphanumeric(),
        ];
        foreach ($cases as $index => $case) {
            try {
                $case();
                self::fail(sprintf('Case %d did not throw.', $index));
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
