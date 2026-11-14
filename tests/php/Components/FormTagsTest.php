<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Components\Form\Repeater;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FormTagsTest extends TestCase
{
    public function testRendersAMultipleSelectOfTheSuggestionsWithTagOptions(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::tags('skills', ['PHP', 'Laravel', 'PHP'])->placeholder('Add a skill'),
        )->render();

        self::assertStringContainsString(
            '<label for="f-skills" class="form-label">Skills</label>'
            . '<select id="f-skills" name="skills[]" multiple data-celerik-select="{&quot;placeholder&quot;:&quot;Add a skill&quot;,&quot;tags&quot;:true}" class="form-select">'
            . '<option value="PHP">PHP</option><option value="Laravel">Laravel</option></select>',
            $html,
        );
    }

    public function testKeepsTypedTagsThatAreNotSuggestions(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::tags('skills', ['PHP', 'Laravel'])->value(['Laravel', 'Vue <3>', 'Vue <3>', ' ']),
        )->render();

        self::assertStringContainsString(
            '<option value="PHP">PHP</option><option value="Laravel" selected>Laravel</option>'
            . '<option value="Vue &lt;3&gt;" selected>Vue &lt;3&gt;</option></select>',
            $html,
        );
    }

    public function testOldInputAndArrayItemErrors(): void
    {
        $html = Form::make('f')->submit(null)
            ->add(Field::tags('skills'))
            ->values(['skills' => ['Go', 'Rust']])
            ->errors(['skills.1' => 'Rust is not allowed.'])
            ->render();

        self::assertStringContainsString('<option value="Go" selected>Go</option><option value="Rust" selected>Rust</option>', $html);
        self::assertStringContainsString('Rust is not allowed.', $html);
        self::assertTrue(Field::tags('skills')->submitsArray());
    }

    public function testCountsAndAddText(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::tags('skills')->minTags(2)->maxTags(5)->addText('Ajouter « %s »'),
        )->render();

        self::assertStringContainsString(
            'name="skills[]" required multiple data-celerik-select="{&quot;tags&quot;:true,&quot;max&quot;:5,&quot;add&quot;:&quot;Ajouter « %s »&quot;}"'
            . ' data-parsley-mincheck="2" data-parsley-maxcheck="5"',
            $html,
        );
        self::assertStringContainsString('Skills <span class="celerik-required"', $html);
    }

    public function testRemoteSuggestions(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::tags('topics')->remote('/topics/options', value: 'name'),
        )->render();

        self::assertStringContainsString('data-celerik-select="{&quot;url&quot;:&quot;/topics/options&quot;,&quot;tags&quot;:true}"', $html);
    }

    public function testInARepeaterRow(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Repeater::make('people')->schema(Field::text('name'), Field::tags('roles', ['Admin'])),
        )->render();

        self::assertStringContainsString('<select id="f-people-0-roles" name="people[0][roles][]" multiple', $html);
        self::assertStringContainsString('name="people[__index__][roles][]"', $html);
    }

    public function testOptionsAreValidated(): void
    {
        $cases = [
            static fn () => Field::tags('skills', ['PHP', '']),
            static fn () => Field::tags('skills', [['PHP']]),
            static fn () => Field::tags('skills')->multiple(false),
            static fn () => Field::tags('skills')->minTags(0),
            static fn () => Field::tags('skills')->maxTags(2)->minTags(3),
            static fn () => Field::tags('skills')->minTags(3)->maxTags(2),
            static fn () => Field::tags('skills')->addText('Add'),
            static fn () => Field::select('a', ['x' => 'X'])->multiple()->maxTags(2),
            static fn () => Field::text('a')->addText('Add %s'),
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
