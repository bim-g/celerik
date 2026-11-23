<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\ListView;
use Caelrik\Ui\Data\ArrayDataSource;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ListViewTest extends TestCase
{
    private const ROWS = [
        ['id' => 1, 'title' => 'Ada <b>', 'summary' => 'An essay.', 'author' => 'Ada', 'year' => 1843,
            'cover' => '/c/1.jpg', 'alt' => 'An engine', 'secret' => 's3cret'],
        ['id' => 2, 'title' => 'Turing', 'summary' => null, 'author' => 'Alan', 'year' => 1936,
            'cover' => 'javascript:alert(1)', 'alt' => null, 'secret' => 's3cret'],
        ['id' => 3, 'title' => 'Hopper', 'summary' => 'Compilers.', 'author' => 'Grace', 'year' => 1952,
            'cover' => "java\tscript:alert(1)", 'alt' => null, 'secret' => 's3cret'],
    ];

    private function list(): ListView
    {
        return ListView::make('articles')
            ->label('Articles')
            ->title('title')
            ->link('/articles/{id}')
            ->description('summary')
            ->meta('author', 'year')
            ->image('cover', 'alt')
            ->searchable()
            ->sorts(['-year' => 'Newest first', 'title' => 'Title'])
            ->pageSize(2);
    }

    public function testRendersEachItemEscapedWithItsLinkMetaAndPicture(): void
    {
        $html = $this->list()->rows(self::ROWS)->render();

        self::assertStringContainsString('data-caelrik="listview"', $html);
        self::assertStringContainsString('<ul class="caelrik-listview-items list-unstyled" aria-label="Articles" tabindex="-1">', $html);
        self::assertStringContainsString(
            '<h3 class="caelrik-listview-title"><a href="/articles/1">Ada &lt;b&gt;</a></h3>'
            . '<p class="caelrik-listview-description">An essay.</p>'
            . '<p class="caelrik-listview-meta"><span>Ada</span><span>1843</span></p>',
            $html,
        );
        self::assertStringContainsString('<img class="caelrik-listview-image" src="/c/1.jpg" alt="An engine" loading="lazy">', $html);
        self::assertStringNotContainsString('javascript', $html);
        self::assertStringNotContainsString('script:', $html);
        self::assertStringContainsString('data-sort="{&quot;year&quot;:1843,&quot;title&quot;:&quot;Ada &lt;b&gt;&quot;}"', $html);
        self::assertStringNotContainsString('s3cret', $html);
        self::assertStringContainsString('<nav class="caelrik-listview-pager" aria-label="Pages of Articles" hidden></nav>', $html);
        self::assertStringContainsString('<label class="form-label" for="articles-search">Search</label>', $html);
        self::assertStringContainsString('<option value="-year">Newest first</option>', $html);
    }

    public function testCardsAndHeadingLevel(): void
    {
        $html = ListView::make('people')->title('name')->layout('cards', 4)->headingLevel(2)
            ->rows([['name' => 'Ada']])->render();

        self::assertStringContainsString('caelrik-listview caelrik-listview-cards', $html);
        self::assertStringContainsString('row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-3', $html);
        self::assertStringContainsString('<li class="col">', $html);
        self::assertStringContainsString('<h2 class="caelrik-listview-title">Ada</h2>', $html);
        self::assertStringNotContainsString('caelrik-listview-toolbar', $html);
    }

    public function testAnEmptyListShowsItsEmptyState(): void
    {
        $html = ListView::make('notes')->title('title')
            ->emptyState('No notes yet', 'Write one.', Action::link('new', 'New note', '/notes/new'))
            ->rows([])->render();

        self::assertStringContainsString('<ul class="caelrik-listview-items list-unstyled" tabindex="-1" hidden>', $html);
        self::assertStringContainsString('<div class="caelrik-listview-empty"><p class="caelrik-listview-empty-title">No notes yet</p>', $html);
        self::assertStringContainsString('href="/notes/new"', $html);
    }

    public function testActionsAreNamedByTheTitleAndFormModalsRenderOnce(): void
    {
        $html = ListView::make('people')->title('name')
            ->actions(
                Action::request('rename', 'Rename', '/people/{id}')->method('PATCH')
                    ->form(Form::make('rename-form')->add(Field::text('name')), 'Rename {name}'),
            )
            ->rows([['id' => 1, 'name' => 'Ada'], ['id' => 2, 'name' => 'Alan']])
            ->render();

        self::assertStringContainsString('aria-label="Rename Ada"', $html);
        self::assertSame(1, substr_count($html, 'id="rename-form-modal"'));
        self::assertStringContainsString('data-values="{&quot;name&quot;:&quot;Alan&quot;}"', $html);
    }

    public function testRespondPagesSearchesAndSortsOnlyWhatTheListAllows(): void
    {
        $list = $this->list()->url('/articles/data')
            ->actions(Action::request('delete', 'Delete', '/articles/{id}')->method('DELETE'));
        $source = new ArrayDataSource(self::ROWS);

        $result = $list->respond($source, ['page' => '1', 'per_page' => '2', 'sort' => '-year'])->toArray();
        self::assertSame(['Hopper', 'Turing'], array_column($result['data'], 'title'));
        self::assertSame(3, $result['meta']['total']);
        self::assertArrayNotHasKey('secret', $result['data'][0], 'Only the fields the list shows are sent.');
        self::assertSame(['title', 'summary', 'author', 'year', 'cover', 'alt', 'id'], array_keys($result['data'][0]));

        // Search reads titles, descriptions and meta; not the hidden fields.
        self::assertSame(['Hopper'], array_column($list->respond($source, ['search' => 'grace'])->rows, 'title'));
        self::assertSame([], $list->respond($source, ['search' => 's3cret'])->rows);
        // A sort the list does not offer is ignored.
        self::assertSame([], $list->respond($source, ['sort' => 'secret'])->request->sort);
    }

    public function testRespondIgnoresSearchWhenTheListIsNotSearchable(): void
    {
        $list = ListView::make('people')->title('name')->url('/people');
        $result = $list->respond(new ArrayDataSource([['name' => 'Ada'], ['name' => 'Alan']]), ['search' => 'Ada']);

        self::assertCount(2, $result->rows);
        self::assertNull($result->request->search);
    }

    public function testOptionsForTheBrowser(): void
    {
        $options = $this->list()->url('/articles/data')->labels(['next' => 'Suivant'])->toArray();

        self::assertSame('/articles/data', $options['url']);
        self::assertSame(2, $options['pageSize']);
        self::assertTrue($options['searchable']);
        self::assertSame([['-year', 'Newest first'], ['title', 'Title']], $options['sorts']);
        self::assertSame('/articles/{id}', $options['item']['link']);
        self::assertSame(['author', 'year'], $options['item']['meta']);
        self::assertSame('Suivant', $options['labels']['next']);
    }

    public function testRejectsBadConfiguration(): void
    {
        $cases = [
            static fn () => ListView::make('l')->title('bad field'),
            static fn () => ListView::make('l')->headingLevel(1),
            static fn () => ListView::make('l')->layout('table'),
            static fn () => ListView::make('l')->layout('cards', 7),
            static fn () => ListView::make('l')->pageSize(0),
            static fn () => ListView::make('l')->sorts(['-no way' => 'X']),
            static fn () => ListView::make('l')->labels(['nope' => 'X']),
        ];
        foreach ($cases as $case) {
            try {
                $case();
                self::fail('A bad argument was accepted.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectException(LogicException::class);
        ListView::make('l')->render();
    }
}
