<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Gantt;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GanttTest extends TestCase
{
    private function launch(): Gantt
    {
        return Gantt::make('launch')->label('Launch <v1>')->today('2026-11-20')
            ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100)
            ->task('build', 'Build <api>', new DateTimeImmutable('2026-11-16 15:30'), '2026-11-27', 40, ['design'], 'success')
            ->milestone('release', 'Release', '2026-11-30', depends: ['build', 'design']);
    }

    public function testATableNamedByItsLabelWithTheTimelineAsLastColumn(): void
    {
        $html = $this->launch()->render();

        self::assertStringStartsWith(
            '<div id="launch" class="celerik-gantt celerik-gantt-week" data-celerik="gantt" data-celerik-options="{}">'
            . '<p id="launch-label" class="celerik-gantt-label">Launch &lt;v1&gt;</p>'
            . '<div class="celerik-gantt-scroll" role="region" aria-labelledby="launch-label" tabindex="0">'
            . '<div class="celerik-gantt-canvas"><table class="celerik-gantt-table" aria-labelledby="launch-label"><thead><tr>'
            . '<th scope="col">Task</th><th scope="col">Start</th><th scope="col">End</th><th scope="col">Progress</th>'
            . '<th scope="col">Depends on</th><th scope="col" class="celerik-gantt-timeline"><span class="visually-hidden">'
            . 'Timeline</span><svg class="celerik-gantt-scale" width="350" height="40" viewBox="0 0 350 40" '
            . 'aria-hidden="true" focusable="false">',
            $html,
        );
        // Whole weeks: Monday Nov 2 to Sunday Dec 6, 10 px a day.
        self::assertStringContainsString('<text x="4" y="15">Nov 2026</text>', $html);
        self::assertStringContainsString('<text x="214" y="33">Nov 23</text>', $html);
        self::assertStringContainsString('<text x="294" y="15">Dec 2026</text>', $html);
        // A month name that would be cut at the right end is left out.
        self::assertStringNotContainsString(
            'Dec 2026',
            Gantt::make()->label('A')->task('a', 'A', '2026-11-02', '2026-12-01')->scale('day')->render(),
        );

        self::assertStringContainsString(
            '<tr data-task="build" data-depends="design"><th scope="row" class="celerik-gantt-name">Build &lt;api&gt;</th>'
            . '<td><time datetime="2026-11-16">Nov 16, 2026</time></td><td><time datetime="2026-11-27">Nov 27, 2026</time></td>'
            . '<td>40%</td><td>Design</td><td class="celerik-gantt-timeline"><svg class="celerik-gantt-row" width="350" '
            . 'height="28" viewBox="0 0 350 28" aria-hidden="true" focusable="false">',
            $html,
        );
        self::assertStringContainsString(
            '<line class="celerik-gantt-today" x1="185" y1="0" x2="185" y2="28"></line>'
            . '<rect class="celerik-gantt-bar celerik-gantt-success" x="141" y="6" width="118" height="16" rx="3" '
            . 'data-task="build"></rect><rect class="celerik-gantt-done celerik-gantt-success" x="141" y="6" '
            . 'width="47.2" height="16" rx="3"></rect>',
            $html,
        );
        // A milestone: a diamond, "Milestone" written, the names of what it waits for.
        self::assertStringContainsString('<tr class="is-milestone" data-task="release" data-depends="build design">', $html);
        self::assertStringContainsString('<td>Milestone</td><td>Build &lt;api&gt;, Design</td>', $html);
        self::assertStringContainsString(
            '<polygon class="celerik-gantt-milestone celerik-gantt-primary" points="285,6 293,14 285,22 277,14" '
            . 'data-task="release">',
            $html,
        );
        self::assertStringNotContainsString('style=', $html);
    }

    public function testScalesAndOptions(): void
    {
        $day = Gantt::make('d')->label('Sprint')->scale('day')->today(null)->dateFormat('D j')
            ->labels(['task' => 'Tâche', 'milestone' => 'Jalon'])
            ->task('a', 'A', '2026-11-20', '2026-11-21')
            ->milestone('m', 'M', '2026-11-23')
            ->render();
        // One day before and after; 28 px a day; weekends shaded; no today line.
        self::assertStringContainsString('width="168" height="40"', $day);
        self::assertStringContainsString('<rect class="celerik-gantt-weekend" x="56" y="0" width="28" height="28">', $day);
        self::assertStringContainsString('<text x="14" y="33" text-anchor="middle">19</text>', $day);
        self::assertStringNotContainsString('celerik-gantt-today', $day);
        self::assertStringContainsString('<th scope="col">Tâche</th>', $day);
        self::assertStringContainsString('<time datetime="2026-11-20">Fri 20</time>', $day);
        self::assertStringContainsString('<td>Jalon</td>', $day);
        // A task without a progress has no done part and an empty cell.
        self::assertStringNotContainsString('celerik-gantt-done', $day);

        $month = Gantt::make('m')->label('Roadmap')->scale('month')->today('2027-01-15')
            ->task('a', 'A', '2026-11-16', '2027-01-31')->render();
        // Nov 1 to Jan 31: 92 days, 3 px a day.
        self::assertStringContainsString('width="276" height="40"', $month);
        self::assertStringContainsString('<text x="4" y="15">2026</text>', $month);
        self::assertStringContainsString('<text x="187" y="15">2027</text>', $month);
        self::assertStringContainsString('<text x="187" y="33">Jan</text>', $month);
    }

    public function testRequiredParts(): void
    {
        $renders = [
            static fn () => Gantt::make()->label('A')->render(),
            static fn () => Gantt::make()->task('a', 'A', '2026-01-01', '2026-01-02')->render(),
            static fn () => Gantt::make()->label('A')->task('a', 'A', '2026-01-01', '2026-01-02', depends: ['x'])->render(),
        ];
        foreach ($renders as $render) {
            try {
                $render();
                self::fail('A required part was missing.');
            } catch (LogicException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'scale' => [static fn () => Gantt::make()->scale('year')];
        yield 'date' => [static fn () => Gantt::make()->task('a', 'A', '2026-02-30', '2026-03-01')];
        yield 'date format' => [static fn () => Gantt::make()->task('a', 'A', '01/02/2026', '2026-03-01')];
        yield 'ends before' => [static fn () => Gantt::make()->task('a', 'A', '2026-03-02', '2026-03-01')];
        yield 'progress' => [static fn () => Gantt::make()->task('a', 'A', '2026-03-01', '2026-03-02', 101)];
        yield 'color' => [static fn () => Gantt::make()->milestone('a', 'A', '2026-03-01', color: 'pink')];
        yield 'duplicate' => [static fn () => Gantt::make()->milestone('a', 'A', '2026-03-01')->milestone('a', 'B', '2026-03-02')];
        yield 'label' => [static fn () => Gantt::make()->labels(['nope' => 'x'])];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
