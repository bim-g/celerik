<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Components;

use Celerik\Ui\Components\Carousel;
use Celerik\Ui\Components\Timeline;
use Celerik\Ui\Support\Html;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimelineCarouselTest extends TestCase
{
    public function testATimelineIsANamedOrderedList(): void
    {
        $html = Timeline::make('order')->label('Order <history>')
            ->event('Order placed', new DateTimeImmutable('2026-11-01T09:30:00+00:00'), 'Paid <by> card.', color: 'success')
            ->event('Shipped', 'Yesterday', Html::raw('<p>By <b>post</b></p>'), marker: '✓', current: true)
            ->render();

        self::assertSame(
            '<ol id="order" class="celerik-timeline" data-celerik="timeline" data-celerik-options="{}" '
            . 'aria-label="Order &lt;history&gt;">'
            . '<li class="celerik-timeline-event"><span class="celerik-timeline-marker celerik-timeline-marker-success" '
            . 'aria-hidden="true"></span><div class="celerik-timeline-body"><p class="celerik-timeline-title">Order placed</p>'
            . '<time class="celerik-timeline-time" datetime="2026-11-01T09:30:00+00:00">Nov 1, 2026</time>'
            . '<div class="celerik-timeline-content">Paid &lt;by&gt; card.</div></div></li>'
            . '<li class="celerik-timeline-event is-current" aria-current="step"><span class="celerik-timeline-marker has-text" '
            . 'aria-hidden="true">✓</span><div class="celerik-timeline-body"><p class="celerik-timeline-title">Shipped</p>'
            . '<p class="celerik-timeline-time">Yesterday</p>'
            . '<div class="celerik-timeline-content"><p>By <b>post</b></p></div></div></li></ol>',
            $html,
        );
    }

    public function testTimelineOptions(): void
    {
        $html = Timeline::make('t')->headingLevel(3)->alternate()->dateFormat('Y-m-d')
            ->event('A', new DateTimeImmutable('2026-01-02'), url: '/a', current: true)
            ->event('B', url: 'javascript:alert(1)', current: true)
            ->render();

        self::assertStringContainsString('class="celerik-timeline celerik-timeline-alternate"', $html);
        self::assertStringContainsString('<h3 class="celerik-timeline-title"><a href="/a">A</a></h3>', $html);
        self::assertStringContainsString('<h3 class="celerik-timeline-title">B</h3>', $html);
        self::assertStringContainsString('>2026-01-02</time>', $html);
        // Only one current step: the last one marked.
        self::assertSame(1, substr_count($html, 'aria-current="step"'));
        self::assertStringContainsString('<li class="celerik-timeline-event is-current" aria-current="step"><span class="celerik-timeline-marker" aria-hidden="true"></span><div class="celerik-timeline-body"><h3 class="celerik-timeline-title">B', $html);
    }

    public function testACarouselFollowsTheWaiAriaPattern(): void
    {
        $html = Carousel::make('news')->label('News')
            ->image('/img/lake.jpg', 'Lake Kivu at dawn', caption: 'Our <new> office')
            ->slide('Spring <sale>', label: 'Spring sale')
            ->slide(Html::raw('<p>Third</p>'))
            ->render();

        self::assertStringStartsWith(
            '<section id="news" class="celerik-carousel celerik-carousel-ratio-16x9" data-celerik="carousel" '
            . 'data-celerik-options="{&quot;autoplay&quot;:0,&quot;loop&quot;:true,&quot;labels&quot;:'
            . '{&quot;start&quot;:&quot;Start automatic slide show&quot;,&quot;stop&quot;:&quot;Stop automatic slide show&quot;}}" '
            . 'aria-roledescription="carousel" aria-label="News"><div class="celerik-carousel-controls">'
            . '<button type="button" class="btn btn-sm celerik-action-button btn-outline-secondary celerik-carousel-previous" '
            . 'aria-label="Previous slide" aria-controls="news-slides"><svg',
            $html,
        );
        self::assertStringNotContainsString('celerik-carousel-rotate', $html);
        self::assertStringContainsString(
            '<div class="celerik-carousel-picker" role="group" aria-label="Choose a slide">'
            . '<button type="button" class="celerik-carousel-dot" aria-controls="news-slide-1" aria-label="Slide 1" '
            . 'aria-current="true"></button>'
            . '<button type="button" class="celerik-carousel-dot" aria-controls="news-slide-2" aria-label="Spring sale"></button>',
            $html,
        );
        self::assertStringContainsString(
            '<div class="celerik-carousel-slides" id="news-slides" aria-live="polite">'
            . '<div class="celerik-carousel-slide is-active" id="news-slide-1" role="group" aria-roledescription="slide" '
            . 'aria-label="1 of 3"><figure class="celerik-carousel-figure"><img src="/img/lake.jpg" alt="Lake Kivu at dawn" '
            . 'class="celerik-carousel-image"><figcaption class="celerik-carousel-caption">Our &lt;new&gt; office</figcaption>'
            . '</figure></div>'
            . '<div class="celerik-carousel-slide" id="news-slide-2" role="group" aria-roledescription="slide" '
            . 'aria-label="Spring sale">Spring &lt;sale&gt;</div>',
            $html,
        );
        self::assertStringContainsString('aria-label="3 of 3"><p>Third</p></div>', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testCarouselOptions(): void
    {
        $html = Carousel::make('c')->autoplay(6)->loop(false)->picker(false)->ratio('4x3')
            ->labels(['stop' => 'Arrêter', 'slide' => 'Diapositive {index} sur {count}'])
            ->image('/a.jpg', 'A')->image('/b.jpg', 'B')
            ->render();

        self::assertStringContainsString('class="celerik-carousel celerik-carousel-ratio-4x3 is-playing"', $html);
        self::assertStringContainsString('&quot;autoplay&quot;:6,&quot;loop&quot;:false', $html);
        self::assertStringContainsString('celerik-carousel-rotate" aria-label="Arrêter" aria-controls="c-slides">', $html);
        self::assertStringContainsString(
            'celerik-carousel-previous" aria-label="Previous slide" aria-controls="c-slides" aria-disabled="true">',
            $html,
        );
        self::assertStringNotContainsString('celerik-carousel-picker', $html);
        self::assertStringContainsString('aria-live="off"', $html);
        self::assertStringContainsString('aria-label="Diapositive 2 sur 2"', $html);
        // Pictures after the first load when they are near.
        self::assertStringContainsString('<img src="/b.jpg" alt="B" class="celerik-carousel-image" loading="lazy">', $html);

        // One slide: nothing to move between.
        self::assertStringNotContainsString('<button', Carousel::make('one')->slide('Only')->autoplay()->render());
    }

    public function testRequiredParts(): void
    {
        $renders = [
            static fn () => Timeline::make()->render(),
            static fn () => Carousel::make()->render(),
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
        yield 'timeline heading' => [static fn () => Timeline::make()->headingLevel(1)];
        yield 'timeline color' => [static fn () => Timeline::make()->event('A', color: 'pink')];
        yield 'carousel autoplay' => [static fn () => Carousel::make()->autoplay(1)];
        yield 'carousel ratio' => [static fn () => Carousel::make()->ratio('2x1')];
        yield 'carousel label' => [static fn () => Carousel::make()->labels(['nope' => 'x'])];
        yield 'carousel image url' => [static fn () => Carousel::make()->image('javascript:x', 'X')];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationsAreRefused(callable $configure): void
    {
        $this->expectException(InvalidArgumentException::class);
        $configure();
    }
}
