<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use LogicException;

/**
 * An application's navigation: links, and groups of links that open and close. The sidebar
 * of an AdminLayout (the Telerik Drawer), or a navigation anywhere.
 *
 * Groups are native disclosures (<details>): they open and close without JavaScript. The
 * link to the current page has aria-current="page"; when no link is the page itself, the
 * closest section (a link whose path starts the current one) has aria-current="true". A
 * group holding the current link starts open.
 *
 *     Navigation::make('main')->label('Main')
 *         ->item('Dashboard', '/')
 *         ->group('Members', static fn (Navigation $group) => $group
 *             ->item('All members', '/members')
 *             ->item('Invitations', '/members/invitations', badge: '3'))
 *         ->current($_SERVER['REQUEST_URI']);
 */
final class Navigation extends Component
{
    private ?string $label = null;

    /** @var list<array{type: 'item', label: string, url: string, badge: ?string}|array{type: 'group', label: string, nav: Navigation}> */
    private array $entries = [];

    private ?string $current = null;

    private bool $horizontal = false;

    public static function name(): string
    {
        return 'navigation';
    }

    /**
     * The landmark's name, e.g. 'Main'. Name each navigation when a page has more than one.
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * A link. `$badge` is a short text after it, e.g. a count ('3').
     */
    public function item(string $label, string $url, ?string $badge = null): self
    {
        $this->entries[] = ['type' => 'item', 'label' => $label, 'url' => $url, 'badge' => $badge];

        return $this;
    }

    /**
     * A group of links that opens and closes, e.g. a section's pages.
     *
     * @param callable(Navigation): mixed $build
     */
    public function group(string $label, callable $build): self
    {
        $group = new self($this->id . '-' . (count($this->entries) + 1));
        $build($group);
        $this->entries[] = ['type' => 'group', 'label' => $label, 'nav' => $group];

        return $this;
    }

    /**
     * A bar of links across the page (a site's menu), its groups opening below their names,
     * instead of a column. With JavaScript, an open group closes with Escape, when another
     * opens, and when focus or a click leaves it.
     */
    public function horizontal(bool $horizontal = true): self
    {
        $this->horizontal = $horizontal;

        return $this;
    }

    /**
     * The current page's URL or path, e.g. $_SERVER['REQUEST_URI'] (its query is ignored).
     */
    public function current(?string $url): self
    {
        $this->current = $url === null ? null : self::path($url);

        return $this;
    }

    public function toArray(): array
    {
        return $this->horizontal ? ['horizontal' => true] : [];
    }

    public function render(): string
    {
        if ($this->entries === []) {
            throw new LogicException(sprintf('Navigation "%s" has no links.', $this->id));
        }
        [$exact, $section] = $this->match();

        return Html::tag(
            'nav',
            array_merge(
                $this->rootAttributes($this->horizontal ? ['caelrik-nav', 'caelrik-nav-horizontal'] : ['caelrik-nav']),
                ['aria-label' => $this->label],
            ),
            $this->renderList($exact, $section),
        );
    }

    /**
     * @return array{0: ?string, 1: ?string} The URL of the current page's link, and of the
     *     closest section when no link is the page.
     */
    private function match(): array
    {
        if ($this->current === null) {
            return [null, null];
        }
        $urls = $this->urls();
        foreach ($urls as $url) {
            if (self::path($url) === $this->current) {
                return [$url, null];
            }
        }
        $best = null;
        foreach ($urls as $url) {
            $path = rtrim(self::path($url), '/');
            $longer = $best === null || strlen($path) > strlen(rtrim(self::path($best), '/'));
            if ($path !== '' && $longer && str_starts_with($this->current, $path . '/')) {
                $best = $url;
            }
        }

        return [null, $best];
    }

    /**
     * @return list<string>
     */
    private function urls(): array
    {
        $urls = [];
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'item') {
                $urls[] = $entry['url'];
            } else {
                array_push($urls, ...$entry['nav']->urls());
            }
        }

        return $urls;
    }

    private function renderList(?string $exact, ?string $section): string
    {
        $items = '';
        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'item') {
                $current = match (true) {
                    $exact !== null && $entry['url'] === $exact => 'page',
                    $section !== null && $entry['url'] === $section => 'true',
                    default => null,
                };
                $badge = $entry['badge'] === null ? '' : ' ' . Html::tag(
                    'span',
                    ['class' => 'badge rounded-pill text-bg-secondary caelrik-nav-badge'],
                    Html::escape($entry['badge']),
                );
                $items .= Html::tag('li', [], Html::tag('a', [
                    'class' => Html::classes(['caelrik-nav-link', $current !== null ? 'active' : null]),
                    'href' => $entry['url'],
                    'aria-current' => $current,
                ], Html::tag('span', [], Html::escape($entry['label'])) . $badge));
                continue;
            }
            $group = $entry['nav'];
            // A column opens the group holding the current page; a bar's groups drop down on demand.
            $open = !$this->horizontal && in_array($exact ?? $section, $group->urls(), true);
            $items .= Html::tag('li', [], Html::tag(
                'details',
                ['class' => 'caelrik-nav-group', 'open' => $open],
                Html::tag('summary', ['class' => 'caelrik-nav-link'], Html::escape($entry['label']))
                . $group->renderList($exact, $section),
            ));
        }

        return Html::tag('ul', ['class' => 'caelrik-nav-list list-unstyled'], $items);
    }

    private static function path(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '/';
        }

        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
