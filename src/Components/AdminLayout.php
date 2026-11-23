<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use Stringable;

/**
 * The frame of an admin screen: a skip link, an app bar (brand, your items, the user
 * menu), a sidebar with the navigation, and the main content with its breadcrumb and title.
 *
 * On large screens the sidebar stays beside the content; on small ones it is a panel
 * (Bootstrap's offcanvas, a dialog) opened by the app bar's menu button. Without JavaScript,
 * the sidebar is simply shown above the content.
 *
 *     echo AdminLayout::make('app')
 *         ->brand('Acme', '/')
 *         ->navigation(Navigation::make('main')->label('Main')->item('Dashboard', '/')->current($path))
 *         ->userMenu(UserMenu::make('account')->user('Ada Lovelace')->link('Profile', '/profile'))
 *         ->breadcrumb(Breadcrumb::make()->item('Home', '/')->item('Dashboard'))
 *         ->title('Dashboard')
 *         ->content(Html::raw($page));
 */
final class AdminLayout extends Component
{
    public const LABELS = [
        'skip' => 'Skip to main content',
        'openNavigation' => 'Open navigation',
        'navigation' => 'Navigation',
        'close' => 'Close',
    ];

    private string $brand = '';
    private ?string $brandUrl = null;
    private ?Navigation $navigation = null;
    private ?UserMenu $userMenu = null;
    private ?Breadcrumb $breadcrumb = null;
    private ?string $title = null;

    /** @var list<string|Stringable> */
    private array $appBarItems = [];

    private string|Stringable $content = '';

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'adminlayout';
    }

    /**
     * The application's name in the app bar, a link to `$url`.
     */
    public function brand(string $name, ?string $url = null): self
    {
        $this->brand = $name;
        $this->brandUrl = $url;

        return $this;
    }

    public function navigation(?Navigation $navigation): self
    {
        $this->navigation = $navigation;

        return $this;
    }

    public function userMenu(?UserMenu $userMenu): self
    {
        $this->userMenu = $userMenu;

        return $this;
    }

    public function breadcrumb(?Breadcrumb $breadcrumb): self
    {
        $this->breadcrumb = $breadcrumb;

        return $this;
    }

    /**
     * The page's heading (h1), under the breadcrumb.
     */
    public function title(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * More in the app bar, before the user menu: components or Html::raw() (a string is text).
     */
    public function appBar(string|Stringable ...$items): self
    {
        array_push($this->appBarItems, ...$items);

        return $this;
    }

    /**
     * The page's content: components or Html::raw() (a string is escaped text).
     */
    public function content(string|Stringable $content): self
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown layout label "%s". Available: %s.',
                    $key,
                    implode(', ', array_keys(self::LABELS)),
                ));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $main = $this->id . '-main';
        $sidebar = $this->id . '-sidebar';

        $skip = Html::tag(
            'a',
            ['class' => 'visually-hidden-focusable caelrik-skip-link', 'href' => '#' . $main],
            Html::escape($this->labels['skip']),
        );

        $toggle = $this->navigation === null ? '' : Html::tag('button', [
            'type' => 'button',
            'class' => 'btn caelrik-appbar-toggle d-lg-none',
            'data-bs-toggle' => 'offcanvas',
            'data-bs-target' => '#' . $sidebar,
            'aria-controls' => $sidebar,
            'aria-label' => $this->labels['openNavigation'],
        ], Html::tag('span', ['class' => 'navbar-toggler-icon', 'aria-hidden' => 'true'], ''));
        $brand = $this->brand === '' ? '' : ($this->brandUrl === null
            ? Html::tag('span', ['class' => 'navbar-brand mb-0 h1 fs-5'], Html::escape($this->brand))
            : Html::tag('a', ['class' => 'navbar-brand', 'href' => $this->brandUrl], Html::escape($this->brand)));
        $end = '';
        foreach ($this->appBarItems as $item) {
            $end .= Html::content($item);
        }
        $end .= $this->userMenu?->render() ?? '';
        $appBar = Html::tag('header', ['class' => 'caelrik-appbar navbar border-bottom bg-body'], Html::tag(
            'div',
            ['class' => 'container-fluid gap-2'],
            $toggle . $brand . Html::tag(
                'div',
                ['class' => 'caelrik-appbar-end ms-auto d-flex align-items-center gap-2'],
                $end,
            ),
        ));

        $aside = '';
        if ($this->navigation !== null) {
            $title = $sidebar . '-title';
            $aside = Html::tag('aside', [
                'class' => 'caelrik-admin-sidebar offcanvas-lg offcanvas-start',
                'id' => $sidebar,
                'tabindex' => '-1',
                'aria-labelledby' => $title,
            ], Html::tag('div', ['class' => 'offcanvas-header d-lg-none'], Html::tag(
                'h2',
                ['class' => 'offcanvas-title fs-5', 'id' => $title],
                Html::escape($this->labels['navigation']),
            ) . Html::tag('button', [
                'type' => 'button',
                'class' => 'btn-close',
                'data-bs-dismiss' => 'offcanvas',
                'data-bs-target' => '#' . $sidebar,
                'aria-label' => $this->labels['close'],
            ], '')) . Html::tag('div', ['class' => 'offcanvas-body'], $this->navigation->render()));
        }

        $heading = $this->title === null
            ? ''
            : Html::tag('h1', ['class' => 'caelrik-admin-title h3'], Html::escape($this->title));
        $content = Html::tag(
            'main',
            ['class' => 'caelrik-admin-main', 'id' => $main, 'tabindex' => '-1'],
            ($this->breadcrumb?->render() ?? '') . $heading . Html::content($this->content),
        );

        return $skip . Html::tag('div', $this->rootAttributes(['caelrik-admin']), $appBar . Html::tag(
            'div',
            ['class' => 'caelrik-admin-body'],
            $aside . $content,
        ));
    }
}
