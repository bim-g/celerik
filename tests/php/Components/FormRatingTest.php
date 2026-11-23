<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormRatingTest extends TestCase
{
    public function testRendersRadioButtonsLabelledByStarsInAFieldset(): void
    {
        $html = Form::make('f')->submit(null)->add(Field::rating('score')->required()->value(4))->render();

        self::assertStringContainsString(
            '<fieldset id="f-score-group" class="caelrik-rating"><legend class="form-label fs-6">Score <span class="caelrik-required" aria-hidden="true">*</span></legend>'
            . '<div class="caelrik-rating-stars">'
            . '<input type="radio" id="f-score" name="score" value="1" required class="visually-hidden caelrik-rating-input">'
            . '<label for="f-score" class="caelrik-rating-star"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">',
            $html,
        );
        self::assertStringContainsString('<span class="visually-hidden">1 of 5</span></label>', $html);
        self::assertStringContainsString('<input type="radio" id="f-score-4" name="score" value="4" checked required', $html);
        self::assertSame(5, substr_count($html, 'class="caelrik-rating-star"'));
        // Required: no "No rating" choice.
        self::assertStringNotContainsString('caelrik-rating-clear', $html);
    }

    public function testAnOptionalRatingCanBeCleared(): void
    {
        $html = Form::make('f')->submit(null)->add(Field::rating('score'))->render();

        self::assertStringContainsString(
            '<div class="caelrik-rating-clear"><input type="radio" id="f-score-none" name="score" value="" checked class="visually-hidden caelrik-rating-input">'
            . '<label for="f-score-none">No rating</label></div></fieldset>',
            $html,
        );
    }

    public function testStarsLabelsOldInputAndErrors(): void
    {
        $html = Form::make('f')->submit(null)
            ->add(
                Field::rating('score', 'Note')->stars(10)->ratingLabels('%d sur %d', 'Aucune note')->help('Ten is best.'),
            )
            ->values(['score' => '7'])
            ->errors(['score' => 'Too low.'])
            ->render();

        self::assertSame(10, substr_count($html, 'class="caelrik-rating-star"'));
        self::assertStringContainsString('<span class="visually-hidden">7 sur 10</span>', $html);
        self::assertStringContainsString('<label for="f-score-none">Aucune note</label>', $html);
        self::assertStringContainsString('id="f-score-7" name="score" value="7" checked aria-invalid="true" class="visually-hidden caelrik-rating-input is-invalid"', $html);
        self::assertStringContainsString('<fieldset id="f-score-group" class="caelrik-rating" aria-describedby="f-score-help f-score-error">', $html);
        self::assertStringContainsString('<div id="f-score-error" class="invalid-feedback d-block caelrik-server-error">Too low.</div>', $html);
        // The error summary links to the first star.
        self::assertStringContainsString('<a href="#f-score" class="alert-link">Too low.</a>', Form::make('f')->submit(null)
            ->add(Field::rating('score'))->errorSummary()->errors(['score' => 'Too low.'])->render());
    }

    public function testOptionsAreValidated(): void
    {
        $cases = [
            static fn () => Field::rating('score')->stars(2),
            static fn () => Field::rating('score')->stars(11),
            static fn () => Field::rating('score')->ratingLabels('stars'),
            static fn () => Field::text('score')->stars(5),
            static fn () => Field::rating('score')->inline(),
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
