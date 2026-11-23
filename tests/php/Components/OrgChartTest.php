<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\OrgChart;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrgChartTest extends TestCase
{
    public function testPeopleInNestedListsNamedByTheChart(): void
    {
        $html = OrgChart::make('team')->label('Engineering <R&D>')
            ->node('ada', 'Ada Lovelace', 'CTO', url: '/people/ada')
            ->node('alan', 'Alan <Turing>', parent: 'ada', image: '/alan.jpg')
            ->render();

        self::assertStringStartsWith(
            '<div id="team" class="caelrik-orgchart" data-caelrik="orgchart" data-caelrik-options="{}">'
            . '<p id="team-label" class="caelrik-orgchart-label">Engineering &lt;R&amp;D&gt;</p>'
            . '<div class="caelrik-orgchart-scroll" role="region" aria-labelledby="team-label" tabindex="0">'
            . '<ul class="caelrik-orgchart-tree" aria-labelledby="team-label"><li class="caelrik-orgchart-item" data-level="1">'
            . '<div class="caelrik-orgchart-node"><span class="caelrik-avatar caelrik-avatar-md text-bg-',
            $html,
        );
        self::assertStringContainsString(
            '<div class="caelrik-orgchart-text"><p class="caelrik-orgchart-name"><a href="/people/ada">Ada Lovelace</a></p>'
            . '<p class="caelrik-orgchart-title">CTO</p></div></div>'
            . '<ul class="caelrik-orgchart-team" id="team-team-ada"><li class="caelrik-orgchart-item" data-level="2">',
            $html,
        );
        // The picture is decorative: the name is written next to it.
        self::assertStringContainsString('aria-hidden="true"><img src="/alan.jpg" alt=""', $html);
        self::assertStringContainsString('<p class="caelrik-orgchart-name">Alan &lt;Turing&gt;</p></div></div></li></ul>', $html);
        // Not collapsible: no buttons.
        self::assertStringNotContainsString('<button', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testCollapsibleTeamsAndRows(): void
    {
        $rows = [
            ['id' => 1, 'parent_id' => null, 'name' => 'Grace', 'title' => 'CEO'],
            (object) ['id' => 2, 'parent_id' => 1, 'name' => 'Ada', 'title' => ''],
            ['id' => 3, 'parent_id' => 2, 'name' => 'Alan', 'title' => 'Engineer'],
            ['id' => 4, 'parent_id' => 1, 'name' => 'Katherine', 'title' => 'CFO'],
            ['id' => 'x y', 'parent_id' => 4, 'name' => 'Dorothy'],
        ];
        $chart = OrgChart::make('co')->label('Company')->fromRows($rows)->expandLevels(2)
            ->labels(['team' => 'Équipe de {name} : {count}', 'teamOne' => 'Équipe de {name} : une personne']);
        self::assertSame(['expandLevels' => 2], $chart->toArray());
        $html = $chart->render();

        self::assertStringContainsString('class="caelrik-orgchart is-collapsible"', $html);
        // Everyone under Grace counts: 4 people.
        self::assertStringContainsString(
            '<button type="button" class="caelrik-orgchart-toggle" aria-expanded="true" aria-controls="co-team-1" '
            . 'aria-label="Équipe de Grace : 4"><span class="caelrik-orgchart-count">4</span>'
            . '<span class="caelrik-orgchart-chevron" aria-hidden="true"></span></button>',
            $html,
        );
        self::assertStringContainsString('aria-label="Équipe de Ada : une personne"', $html);
        // An empty title writes nothing; people without a team have no button.
        self::assertStringContainsString('<p class="caelrik-orgchart-name">Ada</p></div><button', $html);
        self::assertSame(3, substr_count($html, '<button'));
        self::assertStringContainsString('id="co-team-4"', $html);
        // An id that cannot be part of an HTML id is hashed.
        $hashed = OrgChart::make('h')->label('H')->node('a b', 'A')->node('c', 'C', parent: 'a b')->render();
        self::assertMatchesRegularExpression('/<ul class="caelrik-orgchart-team" id="h-team-h[0-9a-f]{10}">/', $hashed);
        self::assertSame(1, preg_match('/<li class="caelrik-orgchart-item" data-level="3">/', $html));
    }

    public function testBrokenHierarchies(): void
    {
        $renders = [
            'no label' => static fn () => OrgChart::make()->node('a', 'A')->render(),
            'nobody' => static fn () => OrgChart::make()->label('A')->render(),
            'unknown manager' => static fn () => OrgChart::make()->label('A')->node('a', 'A', parent: 'b')->render(),
            'loop' => static fn () => OrgChart::make()->label('A')->node('r', 'Root')
                ->node('a', 'A', parent: 'b')->node('b', 'B', parent: 'a')->render(),
        ];
        foreach ($renders as $case => $render) {
            try {
                $render();
                self::fail(sprintf('%s: no error.', $case));
            } catch (LogicException $exception) {
                if ($case === 'loop') {
                    self::assertStringContainsString('has a loop: a, b.', $exception->getMessage());
                }
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'duplicate' => [static fn () => OrgChart::make()->node('a', 'A')->node('a', 'B')];
        yield 'no name' => [static fn () => OrgChart::make()->node('a', ' ')];
        yield 'levels' => [static fn () => OrgChart::make()->expandLevels(0)];
        yield 'label' => [static fn () => OrgChart::make()->labels(['nope' => 'x'])];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
