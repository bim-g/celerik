<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Diagram;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DiagramTest extends TestCase
{
    private function signup(): Diagram
    {
        return Diagram::make('signup')->label('Sign-up <flow>')
            ->node('visit', 'Visit the site', 'terminal')
            ->node('form', 'Fill in the sign-up form')
            ->node('valid', 'Is the form valid?', 'decision')
            ->node('done', 'Account <created>', 'terminal', 'success')
            ->edge('visit', 'form')
            ->edge('form', 'valid')
            ->edge('valid', 'done', 'Yes')
            ->edge('valid', 'form', 'No');
    }

    public function testAnImageNamedByItsCaptionAndItsStepsAsText(): void
    {
        $html = $this->signup()->render();

        self::assertStringStartsWith(
            '<figure id="signup" class="caelrik-diagram" data-caelrik="diagram" data-caelrik-options="{}">'
            . '<div class="caelrik-diagram-scroll"><svg class="caelrik-diagram-svg" width="',
            $html,
        );
        self::assertStringContainsString(
            'role="img" aria-label="Sign-up &lt;flow&gt;. The steps are written as text below."><defs>'
            . '<marker id="signup-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" '
            . 'orient="auto-start-reverse"><path class="caelrik-diagram-arrowhead" d="M0 0L10 5L0 10z"></path></marker></defs>',
            $html,
        );
        self::assertStringContainsString(
            '<figcaption class="caelrik-diagram-caption">Sign-up &lt;flow&gt;</figcaption>'
            . '<details class="caelrik-diagram-details"><summary>Steps as text</summary><ol class="caelrik-diagram-steps">'
            . '<li id="signup-step-visit"><span class="caelrik-diagram-step">Visit the site</span> '
            . '<span class="caelrik-diagram-tag">(Start)</span><span class="caelrik-diagram-links">Next: '
            . '<a href="#signup-step-form">Fill in the sign-up form</a></span></li>',
            $html,
        );
        self::assertStringContainsString(
            '<li id="signup-step-valid"><span class="caelrik-diagram-step">Is the form valid?</span>'
            . '<span class="caelrik-diagram-links">Yes: <a href="#signup-step-done">Account &lt;created&gt;</a> · '
            . 'No: <a href="#signup-step-form">Fill in the sign-up form</a></span></li>',
            $html,
        );
        self::assertStringContainsString(
            '<li id="signup-step-done"><span class="caelrik-diagram-step">Account &lt;created&gt;</span> '
            . '<span class="caelrik-diagram-tag">(End)</span></li>',
            $html,
        );
        self::assertStringNotContainsString('style=', $html);
    }

    public function testShapesRanksAndLoops(): void
    {
        $html = $this->signup()->render();

        // Steps go down by rank: visit, form, valid, done.
        preg_match_all('/data-step="(\w+)"><(?:rect|polygon)[^>]* (?:y="([\d.]+)"|points="[\d.]+,([\d.]+))/', $html, $steps);
        $tops = array_combine($steps[1], array_map(
            static fn (string $y, string $point): float => (float) ($y !== '' ? $y : $point),
            $steps[2],
            $steps[3],
        ));
        self::assertTrue($tops['visit'] < $tops['form'] && $tops['form'] < $tops['valid'] && $tops['valid'] < $tops['done']);

        // A terminal is a pill, a decision a diamond; a long text wraps.
        self::assertMatchesRegularExpression('/data-step="visit"><rect class="caelrik-diagram-shape caelrik-diagram-default"[^>]*rx="18"/', $html);
        self::assertStringContainsString('data-step="valid"><polygon class="caelrik-diagram-shape caelrik-diagram-default"', $html);
        self::assertStringContainsString('class="caelrik-diagram-shape caelrik-diagram-success"', $html);
        self::assertMatchesRegularExpression('/<tspan[^>]*>Fill in the sign-up<\/tspan><tspan[^>]*>form<\/tspan>/', $html);

        // The loop back to the form is drawn around the side, dashed by its class; one arrow has it.
        self::assertSame(1, substr_count($html, 'caelrik-diagram-edge is-loop'));
        self::assertSame(4, substr_count($html, 'marker-end="url(#signup-arrow)"'));
        // Edge labels: a background and the text.
        self::assertMatchesRegularExpression('/<g class="caelrik-diagram-edge-label"><rect [^>]*><\/rect><text [^>]*>Yes<\/text><\/g>/', $html);
    }

    public function testAnArrowAcrossRanksGoesAroundTheStepsBetween(): void
    {
        $html = Diagram::make('order')->label('Order')->direction('right')
            ->node('stock', 'In stock?', 'decision')
            ->node('wait', 'Order from the supplier')
            ->node('ship', 'Ship it')
            ->edge('stock', 'ship', 'Yes')
            ->edge('stock', 'wait', 'No')
            ->edge('wait', 'ship')
            ->render();

        preg_match('/data-step="wait"><rect [^>]*y="([\d.]+)" width="[\d.]+" height="([\d.]+)"/', $html, $wait);
        preg_match_all('/<path class="caelrik-diagram-edge" d="([^"]+)"/', $html, $paths);
        // The first arrow (stock to ship) passes through a waypoint: the end of its first curve.
        preg_match('/^M[\d.]+ [\d.]+C(?:[\d.]+ ){5}([\d.]+)C/', $paths[1][0], $waypoint);
        self::assertNotEmpty($waypoint);
        $y = (float) $waypoint[1];
        self::assertTrue(
            $y < (float) $wait[1] || $y > (float) $wait[1] + (float) $wait[2],
            sprintf('The arrow crosses the step: %s within %s + %s.', $y, $wait[1], $wait[2]),
        );
        // Waypoints are neither drawn as steps nor written as text.
        self::assertSame(3, substr_count($html, 'class="caelrik-diagram-node"'));
        self::assertSame(3, substr_count($html, '<li id="order-step-'));
    }

    public function testTranslatedTextsAndHashedIds(): void
    {
        $html = Diagram::make('d')->label('Flux')
            ->labels(['image' => '{label}, décrit plus bas.', 'text' => 'Étapes', 'next' => 'Ensuite', 'start' => 'Début', 'end' => 'Fin'])
            ->node('a b', 'A')->node('c', 'C')->edge('a b', 'c')->render();
        self::assertStringContainsString('aria-label="Flux, décrit plus bas."', $html);
        self::assertStringContainsString('<summary>Étapes</summary>', $html);
        self::assertMatchesRegularExpression('/<li id="d-step-h[0-9a-f]{10}">.*\(Début\).*Ensuite: <a href="#d-step-c">C<\/a>/', $html);
        self::assertStringContainsString('(Fin)', $html);
    }

    public function testBrokenDiagrams(): void
    {
        $renders = [
            static fn () => Diagram::make()->node('a', 'A')->render(),
            static fn () => Diagram::make()->label('A')->render(),
            static fn () => Diagram::make()->label('A')->node('a', 'A')->edge('a', 'b')->render(),
        ];
        foreach ($renders as $render) {
            try {
                $render();
                self::fail('A broken diagram was drawn.');
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
        yield 'direction' => [static fn () => Diagram::make()->direction('up')];
        yield 'duplicate' => [static fn () => Diagram::make()->node('a', 'A')->node('a', 'B')];
        yield 'no text' => [static fn () => Diagram::make()->node('a', ' ')];
        yield 'shape' => [static fn () => Diagram::make()->node('a', 'A', 'circle')];
        yield 'color' => [static fn () => Diagram::make()->node('a', 'A', color: 'pink')];
        yield 'to itself' => [static fn () => Diagram::make()->edge('a', 'a')];
        yield 'label' => [static fn () => Diagram::make()->labels(['nope' => 'x'])];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
