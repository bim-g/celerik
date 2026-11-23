<?php

declare(strict_types=1);

namespace Caelrik\Ui\Tests\Components;

use ArrayObject;
use Caelrik\Ui\Components\RecordView;
use Caelrik\Ui\Components\RecordView\Entry;
use Caelrik\Ui\Support\Html;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RecordViewTest extends TestCase
{
    private static function value(Entry $entry, mixed $record): string
    {
        $html = RecordView::make('r')->record($record)->section(null, [$entry])->render();
        preg_match('#<dd>(.*)</dd>#s', $html, $match);

        return $match[1];
    }

    public function testSectionsAreHeadingsWithDescriptionLists(): void
    {
        $html = RecordView::make('member')->headingLevel(3)
            ->record(['name' => 'Ada <L>', 'company' => ['name' => 'Engines']])
            ->section('Profile', [Entry::text('name', 'Name'), Entry::text('company.name', 'Company')->span(2)], 'Who.', 2)
            ->section(null, [Entry::text('name', 'Again')->help('As written.')])
            ->render();

        self::assertStringContainsString('<div id="member" class="caelrik-recordview" data-caelrik="recordview"', $html);
        self::assertStringContainsString(
            '<section class="caelrik-recordview-section" aria-labelledby="member-section-1-title">'
            . '<h3 class="caelrik-recordview-title" id="member-section-1-title">Profile</h3>'
            . '<p class="caelrik-recordview-description">Who.</p>'
            . '<dl class="caelrik-recordview-entries caelrik-recordview-cols-2">'
            . '<div class="caelrik-recordview-entry"><dt>Name</dt><dd>Ada &lt;L&gt;</dd></div>'
            . '<div class="caelrik-recordview-entry caelrik-recordview-span-2"><dt>Company</dt><dd>Engines</dd></div>'
            . '</dl></section>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="caelrik-recordview-section"><dl class="caelrik-recordview-entries caelrik-recordview-cols-1">'
            . '<div class="caelrik-recordview-entry"><dt>Again</dt><dd>Ada &lt;L&gt;<div class="form-text">As written.</div></dd>',
            $html,
        );
    }

    public function testASpanIsNoWiderThanItsSection(): void
    {
        $html = RecordView::make('r')->record([])->section('S', [Entry::text('a', 'A')->span(4)], columns: 2)->render();
        self::assertStringContainsString('caelrik-recordview-span-2', $html);
    }

    public function testRecordsAreArraysArrayAccessOrObjects(): void
    {
        $object = new \stdClass();
        $object->company = new \stdClass();
        $object->company->name = 'Engines';
        self::assertSame('Engines', self::value(Entry::text('company.name', 'C'), $object));
        self::assertSame('Engines', self::value(Entry::text('company.name', 'C'), new ArrayObject(['company' => ['name' => 'Engines']])));
        self::assertSame('Flat', self::value(Entry::text('company.name', 'C'), ['company.name' => 'Flat']));
    }

    public function testEmptyValuesShowAPlaceholder(): void
    {
        $default = '<span class="text-body-secondary" aria-hidden="true">—</span><span class="visually-hidden">Not set</span>';
        self::assertSame($default, self::value(Entry::text('missing', 'M'), []));
        self::assertSame($default, self::value(Entry::list('tags', 'T'), ['tags' => []]));
        self::assertSame(
            '<span class="text-body-secondary">None yet</span>',
            self::value(Entry::text('a', 'A')->placeholder('None yet'), ['a' => '']),
        );
        $html = RecordView::make('r')->labels(['empty' => 'Vide'])->record([])->section(null, [Entry::text('a', 'A')])->render();
        self::assertStringContainsString('>Vide</span>', $html);
    }

    public function testValuesByType(): void
    {
        self::assertSame('<a href="mailto:a@b.c">a@b.c</a>', self::value(Entry::email('e', 'E'), ['e' => 'a@b.c']));
        self::assertSame('<a href="tel:+243810000">+243 81 0000</a>', self::value(Entry::phone('p', 'P'), ['p' => '+243 81 0000']));
        self::assertSame('<a href="https://x.org">https://x.org</a>', self::value(Entry::url('u', 'U'), ['u' => 'https://x.org']));
        self::assertSame('<a href="/docs">Docs</a>', self::value(Entry::url('u', 'U', 'Docs'), ['u' => '/docs']));
        self::assertSame('javascript:alert(1)', self::value(Entry::url('u', 'U'), ['u' => 'javascript:alert(1)']));
        self::assertSame('<time datetime="2024-03-01">01/03/2024</time>', self::value(Entry::date('d', 'D', 'd/m/Y'), ['d' => '2024-03-01']));
        self::assertSame(
            '<time datetime="2024-03-01T14:30:00+00:00">2024-03-01 14:30</time>',
            self::value(Entry::dateTime('d', 'D'), ['d' => new DateTimeImmutable('2024-03-01 14:30', new \DateTimeZone('UTC'))]),
        );
        self::assertSame('soon', self::value(Entry::date('d', 'D'), ['d' => 'soon']));
        self::assertSame('1 234,50 €', self::value(Entry::number('n', 'N', 2, '', ' €', ',', ' '), ['n' => '1234.5']));
        self::assertSame('n/a', self::value(Entry::number('n', 'N'), ['n' => 'n/a']));
        self::assertSame(
            '<span class="caelrik-recordview-no"><span class="caelrik-recordview-mark" aria-hidden="true">✕</span> No</span>',
            self::value(Entry::boolean('b', 'B'), ['b' => '0']),
        );
        self::assertStringContainsString('✓</span> Yes', self::value(Entry::boolean('b', 'B'), ['b' => 1]));
        self::assertSame('<span class="badge text-bg-danger">banned</span>', self::value(Entry::badge('s', 'S', ['banned' => 'danger']), ['s' => 'banned']));
        self::assertSame('<span class="badge text-bg-secondary">new</span>', self::value(Entry::badge('s', 'S'), ['s' => 'new']));
        self::assertSame('<ul class="list-unstyled mb-0"><li>a</li><li>&lt;b&gt;</li></ul>', self::value(Entry::list('l', 'L'), ['l' => ['a', '<b>']]));
        self::assertSame(
            '<img src="/a.png" alt="Ada" class="caelrik-recordview-image img-fluid rounded" loading="lazy">',
            self::value(Entry::image('i', 'I', 'Ada'), ['i' => '/a.png']),
        );
        self::assertSame('', self::value(Entry::image('i', 'I'), ['i' => 'data:image/png;base64,xx']));
    }

    public function testFormatReturnsTextOrHtml(): void
    {
        $record = ['first' => 'Ada', 'last' => '<L>'];
        self::assertSame(
            'Ada &lt;L&gt;',
            self::value(Entry::text('first', 'Name')->format(static fn ($value, $row) => $value . ' ' . $row['last']), $record),
        );
        self::assertSame('<b>Ada</b>', self::value(Entry::text('first', 'N')->format(static fn ($v) => Html::raw("<b>$v</b>")), $record));
    }

    public function testAViewNeedsSections(): void
    {
        $this->expectException(LogicException::class);
        RecordView::make('r')->render();
    }

    /**
     * @return iterable<string, array{callable(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        yield 'no entries' => [static fn () => RecordView::make('r')->section('S', [])];
        yield 'not an entry' => [static fn () => RecordView::make('r')->section('S', ['name'])];
        yield 'five columns' => [static fn () => RecordView::make('r')->section('S', [Entry::text('a', 'A')], columns: 5)];
        yield 'h1' => [static fn () => RecordView::make('r')->headingLevel(1)];
        yield 'unknown label' => [static fn () => RecordView::make('r')->labels(['nope' => 'x'])];
        yield 'no label' => [static fn () => Entry::text('a', ' ')];
        yield 'bad color' => [static fn () => Entry::badge('a', 'A', ['x' => 'pink'])];
        yield 'bad span' => [static fn () => Entry::text('a', 'A')->span(5)];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
