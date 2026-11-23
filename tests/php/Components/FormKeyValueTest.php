<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Form\Repeater;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormKeyValueTest extends TestCase
{
    public function testRendersARowPerPairInAFieldset(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::keyValue('headers')->value(['Accept' => 'application/json', 'X-Team' => 'billing']),
        )->render();

        self::assertStringContainsString(
            '<fieldset id="f-headers-group"><legend class="form-label fs-6">Headers</legend>'
            . '<div class="caelrik-key-value-head" aria-hidden="true"><span>Key</span><span>Value</span></div>'
            . '<ol class="caelrik-key-value-rows"><li class="caelrik-key-value-row" data-caelrik-index="0">'
            . '<label for="f-headers" class="visually-hidden">Key 1</label>'
            . '<input type="text" id="f-headers" name="headers[0][key]" value="Accept" autocomplete="off"'
            . ' data-parsley-validate-if-empty="true" data-parsley-keyneeded="true"'
            . ' data-parsley-keyneeded-message="Enter a key for this value." data-parsley-keyunique="true"'
            . ' data-parsley-keyunique-message="This key is already used." class="form-control caelrik-key-value-key">'
            . '<label for="f-headers-0-value" class="visually-hidden">Value 1</label>'
            . '<input type="text" id="f-headers-0-value" name="headers[0][value]" value="application/json"'
            . ' class="form-control caelrik-key-value-value">'
            . '<button type="button" class="btn btn-outline-secondary caelrik-key-value-remove" data-caelrik-key-value-remove>',
            $html,
        );
        self::assertStringContainsString('<span class="visually-hidden">Remove row 1</span></button></li>', $html);
        self::assertStringContainsString(
            '<input type="text" id="f-headers-1-key" name="headers[1][key]" value="X-Team"',
            $html,
        );
        self::assertStringContainsString('<label for="f-headers-1-value" class="visually-hidden">Value 2</label>', $html);
        // The template of a new row, and the Add button.
        self::assertStringContainsString('<button type="button" class="btn btn-sm btn-outline-primary" data-caelrik-key-value-add>Add row</button>', $html);
        self::assertStringContainsString('<template><li class="caelrik-key-value-row" data-caelrik-index="__pair__">', $html);
        self::assertStringContainsString('name="headers[__pair__][key]"', $html);
        self::assertStringContainsString(
            '<div class="mb-3 caelrik-key-value" data-caelrik-key-value="{&quot;id&quot;:&quot;f-headers&quot;,'
            . '&quot;required&quot;:false,&quot;key&quot;:&quot;Key&quot;,&quot;value&quot;:&quot;Value&quot;,'
            . '&quot;remove&quot;:&quot;Remove row %d&quot;}">',
            $html,
        );
    }

    public function testANewFormStartsWithOneEmptyRow(): void
    {
        $html = Form::make('f')->submit(null)->add(Field::keyValue('meta')->required())->render();

        // One row, plus the template's.
        self::assertSame(2, substr_count($html, 'class="caelrik-key-value-row"'));
        self::assertStringContainsString('<legend class="form-label fs-6">Meta <span class="caelrik-required"', $html);
        // A required list needs a key in its first row.
        self::assertStringContainsString(
            'name="meta[0][key]" autocomplete="off" required data-parsley-required-message="Enter a key for this value."',
            $html,
        );
        self::assertStringContainsString('name="meta[__pair__][key]" autocomplete="off" data-parsley-required-message="Enter a key for this value." data-parsley-validate-if-empty', $html);
    }

    public function testOldInputSuggestionsLabelsAndErrors(): void
    {
        $html = Form::make('f')->submit(null)
            ->add(
                Field::keyValue('meta', 'Métadonnées')
                    ->keySuggestions(['env', 'team', 'env'])
                    ->keyValueLabels('Clé', 'Valeur', 'Ajouter', 'Retirer la ligne %d', 'Saisissez une clé.', 'Clé en double.')
                    ->help('Optional.'),
            )
            ->values(['meta' => [3 => ['key' => ' env ', 'value' => 'prod'], 7 => ['key' => 'env', 'value' => 'dev']]])
            ->errors(['meta.7.key' => 'The keys must be distinct.'])
            ->render();

        // Old input rows are numbered from 0, keys trimmed.
        self::assertStringContainsString('id="f-meta" name="meta[0][key]" value="env" list="f-meta-keys"', $html);
        self::assertStringContainsString('id="f-meta-1-key" name="meta[1][key]" value="env"', $html);
        self::assertStringContainsString('name="meta[1][value]" value="dev"', $html);
        self::assertStringContainsString('<datalist id="f-meta-keys"><option value="env"></option><option value="team"></option></datalist>', $html);
        self::assertStringContainsString('<label for="f-meta-1-key" class="visually-hidden">Clé 2</label>', $html);
        self::assertStringContainsString('<span class="visually-hidden">Retirer la ligne 2</span>', $html);
        self::assertStringContainsString('data-parsley-keyneeded-message="Saisissez une clé."', $html);
        self::assertStringContainsString('data-parsley-keyunique-message="Clé en double."', $html);
        self::assertStringContainsString('<div class="caelrik-key-value-head" aria-hidden="true"><span>Clé</span><span>Valeur</span></div>', $html);
        // An error on one row shows under the list.
        self::assertStringContainsString('<fieldset id="f-meta-group" aria-describedby="f-meta-help f-meta-error">', $html);
        self::assertStringContainsString('<div id="f-meta-error" class="invalid-feedback d-block caelrik-server-error">The keys must be distinct.</div>', $html);
        // The error summary links to the first key.
        self::assertStringContainsString('<a href="#f-meta" class="alert-link">Required.</a>', Form::make('f')->submit(null)
            ->add(Field::keyValue('meta'))->errorSummary()->errors(['meta' => 'Required.'])->render());
    }

    public function testKeyValuesTurnsTheRowsIntoAnArray(): void
    {
        self::assertSame(
            ['Accept' => 'text/html', 'X-Empty' => '', 'Content-Type' => 'json'],
            Field::keyValues([
                ['key' => 'Accept', 'value' => 'json'],
                ['key' => ' X-Empty ', 'value' => null],
                ['key' => '', 'value' => 'no key'],
                ['key' => 'Content-Type', 'value' => 'json'],
                ['key' => 'Accept', 'value' => 'text/html'],
            ]),
        );
        self::assertSame([], Field::keyValues(null));
        self::assertSame([], Field::keyValues('text'));
        self::assertSame(['a' => '1'], Field::keyValues(['a' => 1]));
    }

    public function testInARepeaterRow(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Repeater::make('services')->schema(Field::text('name'), Field::keyValue('env')),
        )->render();

        self::assertStringContainsString('id="f-services-0-env" name="services[0][env][0][key]"', $html);
        // The repeater's template keeps the list's own placeholder.
        self::assertStringContainsString('name="services[__index__][env][__pair__][key]"', $html);
    }

    public function testOptionsAreValidated(): void
    {
        $cases = [
            static fn () => Field::keyValue('meta')->keyValueLabels(remove: 'Remove'),
            static fn () => Field::text('meta')->keySuggestions(['a']),
            static fn () => Field::text('meta')->keyValueLabels('Name'),
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
