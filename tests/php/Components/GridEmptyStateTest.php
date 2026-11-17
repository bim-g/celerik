<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Grid;
use PHPUnit\Framework\TestCase;

final class GridEmptyStateTest extends TestCase
{
    public function testDefaultsSayThereIsNothingAndOfferToClearFilters(): void
    {
        $options = Grid::make()->column('name')->toArray();

        self::assertSame(
            '<div class="celerik-grid-empty"><p class="celerik-grid-empty-title">No rows to show</p></div>',
            $options['emptyHtml'],
        );
        self::assertSame(
            '<div class="celerik-grid-empty"><p class="celerik-grid-empty-title">No rows match your search or filters</p>'
            . '<div class="celerik-grid-empty-action"><button type="button" class="btn btn-sm btn-outline-secondary'
            . ' celerik-action-button" data-celerik-grid-reset>Clear filters and search</button></div></div>',
            $options['noMatchesHtml'],
        );
    }

    public function testAnEmptyStateWithADescriptionAndAnActionIsEscaped(): void
    {
        $options = Grid::make()->column('name')
            ->emptyState('No <members> yet', 'Invite your team & start.', Action::link('invite', 'Invite', '/invite')->solid()->variant('primary'))
            ->labels(['noMatches' => 'Aucun résultat', 'reset' => 'Tout effacer'])
            ->toArray();

        self::assertSame(
            '<div class="celerik-grid-empty"><p class="celerik-grid-empty-title">No &lt;members&gt; yet</p>'
            . '<p class="celerik-grid-empty-description">Invite your team &amp; start.</p>'
            . '<div class="celerik-grid-empty-action"><a href="/invite" class="btn celerik-action-button btn-primary btn-sm">Invite</a></div></div>',
            $options['emptyHtml'],
        );
        self::assertStringContainsString('Aucun résultat', $options['noMatchesHtml']);
        self::assertStringContainsString('>Tout effacer</button>', $options['noMatchesHtml']);
    }
}
