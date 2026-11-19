<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Accordion;
use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Card;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Splitter;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class AccordionCardSplitterTest extends TestCase
{
    public function testAccordionHeadingsHoldButtonsThatControlNamedRegions(): void
    {
        $accordion = Accordion::make('faq')->headingLevel(4)
            ->panel('Shipping <fast>', 'Two days.', open: true)
            ->panel('Returns', Html::raw('<p>Thirty days.</p>'));
        $html = $accordion->render();

        self::assertStringContainsString('<div id="faq" class="accordion celerik-accordion" data-celerik="accordion"', $html);
        self::assertStringContainsString(
            '<h4 class="accordion-header"><button type="button" class="accordion-button" id="faq-button-1" '
            . 'aria-expanded="true" aria-controls="faq-panel-1">Shipping &lt;fast&gt;</button></h4>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="accordion-collapse" id="faq-panel-2" role="region" aria-labelledby="faq-button-2" data-open="false">'
            . '<div class="accordion-body"><p>Thirty days.</p></div></div>',
            $html,
        );
        self::assertSame([], $accordion->toArray());
        self::assertSame(['multiple' => true], $accordion->multiple()->toArray());
    }

    public function testCardHasATitleLinkCoveringItAndActionsAboveIt(): void
    {
        $html = Card::make('revenue')
            ->title('Revenue <Q4>', level: 2)
            ->subtitle('November')
            ->text('Up 12%.')
            ->body(Html::raw('<canvas></canvas>'))
            ->image('javascript:alert(1)', 'Nope')
            ->link('/reports/revenue')
            ->actions(Action::request('rename', 'Rename', '/reports/1')->form(Form::make('rename-report')))
            ->footer('Updated today')
            ->render();

        self::assertStringContainsString('<div id="revenue" class="card celerik-card" data-celerik="card"', $html);
        self::assertStringContainsString(
            '<h2 class="card-title h5"><a class="stretched-link celerik-card-link" href="/reports/revenue">Revenue &lt;Q4&gt;</a></h2>',
            $html,
        );
        self::assertStringContainsString('<p class="card-subtitle mb-2 text-body-secondary">November</p><p class="card-text">Up 12%.</p><canvas></canvas>', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('<div class="celerik-card-actions">', $html);
        self::assertStringContainsString('<div class="card-footer text-body-secondary">Updated today</div>', $html);
        self::assertSame(1, substr_count($html, 'id="rename-report-modal"'));

        $picture = Card::make('p')->title('P')->image('/img/p.png')->render();
        self::assertStringContainsString('<img class="card-img-top" src="/img/p.png" alt="" loading="lazy">', $picture);
    }

    public function testSplitterPanesAreSeparatedByNamedFocusableDividers(): void
    {
        $splitter = Splitter::make('mail')->orientation('vertical')->remember()
            ->pane('Folders', size: 30, min: 20, max: 60, label: 'Folders')
            ->pane('Message')
            ->pane('Details', label: 'Details');
        $html = $splitter->render();

        self::assertStringContainsString('class="celerik-splitter celerik-splitter-vertical" data-celerik="splitter"', $html);
        self::assertStringContainsString('<div class="celerik-splitter-pane" id="mail-pane-1" data-size="30" data-min="20" data-max="60">Folders</div>', $html);
        self::assertStringContainsString(
            '<div class="celerik-splitter-handle" role="separator" tabindex="0" aria-orientation="horizontal" '
            . 'aria-controls="mail-pane-1" aria-valuemin="20" aria-valuemax="60" aria-valuenow="30" aria-label="Resize Folders"></div>',
            $html,
        );
        // An unnamed pane's divider is named by its number; the last pane has none after it.
        self::assertStringContainsString('aria-label="Resize pane 2"', $html);
        self::assertSame(2, substr_count($html, 'role="separator"'));
        self::assertStringNotContainsString('style=', $html, 'No inline styles: a strict CSP allows none.');
        self::assertSame(['orientation' => 'vertical', 'remember' => true], $splitter->toArray());
    }

    public function testRejectsBadConfiguration(): void
    {
        $invalid = [
            static fn () => Accordion::make('a')->headingLevel(1),
            static fn () => Card::make('c')->title('T', level: 7),
            static fn () => Splitter::make('s')->orientation('diagonal'),
            static fn () => Splitter::make('s')->pane('A', size: 5, min: 10),
            static fn () => Splitter::make('s')->pane('A', min: 60, max: 40),
            static fn () => Splitter::make('s')->labels(['nope' => 'x']),
        ];
        foreach ($invalid as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
        try {
            Accordion::make('a')->render();
            self::fail('An empty accordion was rendered.');
        } catch (LogicException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(LogicException::class);
        Splitter::make('s')->pane('Only one')->render();
    }
}
