<?php

declare(strict_types=1);

namespace Celerik\Ui\Tests\Support;

use Celerik\Ui\Support\Url;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function urls(): iterable
    {
        yield 'https' => ['https://example.com/a?b=1#c', 'https://example.com/a?b=1#c'];
        yield 'http, upper case' => ['HTTP://example.com', 'HTTP://example.com'];
        yield 'relative path' => ['/offices/goma', '/offices/goma'];
        yield 'relative with a colon later' => ['/a:1', '/a:1'];
        yield 'relative file' => ['img/logo.png', 'img/logo.png'];
        yield 'colon after a slash' => ['a/b:1', 'a/b:1'];
        yield 'query with a colon' => ['?next=a:b', '?next=a:b'];
        yield 'fragment' => ['#top', '#top'];
        yield 'scheme-relative' => ['//cdn.example.com/x.png', '//cdn.example.com/x.png'];
        yield 'trimmed' => ['  /a  ', '/a'];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'javascript with a number' => ['javascript:0', null];
        yield 'javascript, mixed case' => ['JavaScript:1', null];
        yield 'data' => ['data:text/html,<b>x</b>', null];
        yield 'data with a number' => ['data:1', null];
        yield 'vbscript' => ['vbscript:msgbox(1)', null];
        yield 'mailto' => ['mailto:ada@example.com', null];
        yield 'host and port read as a scheme' => ['localhost:8080', null];
        yield 'tab inside' => ["java\tscript:alert(1)", null];
        yield 'empty' => ['', null];
    }

    #[DataProvider('urls')]
    public function testOnlyRelativeHttpAndHttpsUrlsAreKept(string $url, ?string $expected): void
    {
        self::assertSame($expected, Url::safe($url));
    }
}
