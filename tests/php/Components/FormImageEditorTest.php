<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Upload\UploadRules;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class FormImageEditorTest extends TestCase
{
    public function testAFileInputWithTheEditorOptions(): void
    {
        $html = Form::make('f')->submit(null)->add(
            Field::file('avatar', 'Profile picture')->imageEditor(['1:1', 'free'], 512)
                ->imageEditorLabels(['editor' => 'Modifier la photo', 'free' => 'Libre']),
        )->render();

        self::assertStringContainsString('enctype="multipart/form-data"', $html);
        self::assertStringContainsString(
            'type="file" accept="image/jpeg,.jpg,.jpeg,image/png,.png,image/gif,.gif,image/webp,.webp" class="form-control" '
            . 'data-celerik-image-editor="{&quot;ratios&quot;:[&quot;1:1&quot;,&quot;free&quot;],&quot;maxSize&quot;:512,'
            . '&quot;labels&quot;:{&quot;editor&quot;:&quot;Modifier la photo&quot;,',
            $html,
        );
        self::assertStringContainsString('&quot;free&quot;:&quot;Libre&quot;', $html);
        self::assertStringContainsString('&quot;result&quot;:&quot;The picture sent: {width} × {height} pixels.&quot;', $html);
    }

    public function testDefaults(): void
    {
        $html = Form::make('f')->submit(null)->add(Field::file('photo')->imageEditor())->render();

        self::assertStringContainsString(
            '{&quot;ratios&quot;:[&quot;free&quot;,&quot;1:1&quot;,&quot;4:3&quot;,&quot;16:9&quot;],&quot;labels&quot;:',
            $html,
        );
    }

    public function testOnePictureSentWithTheForm(): void
    {
        foreach (
            [
                Field::file('a')->imageEditor()->upload('/uploads'),
                Field::file('b')->imageEditor()->rules(UploadRules::images()->maxFiles(3)),
            ] as $field
        ) {
            try {
                Form::make('f')->add($field)->render();
                self::fail('An image editor was rendered on a field it cannot edit.');
            } catch (LogicException $exception) {
                self::assertStringContainsString('edits one picture sent with the form', $exception->getMessage());
            }
        }
    }

    public function testInvalidOptions(): void
    {
        foreach (
            [
                static fn () => Field::file('p')->imageEditor([]),
                static fn () => Field::file('p')->imageEditor(['square']),
                static fn () => Field::file('p')->imageEditor(['0:1']),
                static fn () => Field::file('p')->imageEditor(['free'], 8),
                static fn () => Field::file('p')->imageEditorLabels(['crop2' => 'x']),
                static fn () => Field::text('p')->imageEditor(),
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
