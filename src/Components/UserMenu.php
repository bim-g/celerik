<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Support\Url;
use LogicException;

/**
 * The signed-in user's menu, in an app bar: their name and picture (or initials), and
 * links such as Profile, plus actions such as Sign out (an Action::request()).
 *
 * It is a disclosure (<details>), not an ARIA menu: it opens and closes without
 * JavaScript, and its links are read and reached as links. With JavaScript, Escape closes
 * it and returns focus to its button, and it closes when focus or a click leaves it.
 *
 *     UserMenu::make('account')->user('Ada Lovelace', 'ada@example.com', '/avatars/ada.jpg')
 *         ->link('Profile', '/profile')->link('Settings', '/settings')
 *         ->divider()->action(Action::request('logout', 'Sign out', '/logout')->csrf($token));
 */
final class UserMenu extends Component
{
    private string $userName = '';
    private ?string $detail = null;
    private ?string $avatar = null;

    /** @var list<array{type: string, label?: string, url?: string, action?: Action}> */
    private array $items = [];

    public static function name(): string
    {
        return 'usermenu';
    }

    /**
     * The user's name, a detail under it in the menu (e.g. their e-mail), and their picture's
     * URL (relative, http or https; otherwise their initials are shown).
     */
    public function user(string $name, ?string $detail = null, ?string $avatar = null): self
    {
        $this->userName = $name;
        $this->detail = $detail;
        $this->avatar = $avatar === null ? null : Url::safe($avatar);

        return $this;
    }

    public function link(string $label, string $url): self
    {
        $this->items[] = ['type' => 'link', 'label' => $label, 'url' => $url];

        return $this;
    }

    /**
     * An action in the menu, e.g. Sign out: Action::request('logout', 'Sign out', '/logout').
     */
    public function action(Action $action): self
    {
        $this->items[] = ['type' => 'action', 'action' => $action];

        return $this;
    }

    public function divider(): self
    {
        $this->items[] = ['type' => 'divider'];

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->userName === '') {
            throw new LogicException(sprintf('User menu "%s" has no user(): name the signed-in user.', $this->id));
        }

        $picture = $this->avatar !== null
            ? Html::tag('img', ['class' => 'celerik-usermenu-avatar', 'src' => $this->avatar, 'alt' => ''])
            : Html::tag(
                'span',
                ['class' => 'celerik-usermenu-avatar celerik-usermenu-initials', 'aria-hidden' => 'true'],
                Html::escape(self::initials($this->userName)),
            );
        $summary = Html::tag('summary', ['class' => 'btn celerik-usermenu-toggle'], $picture . Html::tag(
            'span',
            ['class' => 'celerik-usermenu-name'],
            Html::escape($this->userName),
        ));

        $items = '';
        foreach ($this->items as $item) {
            $items .= match ($item['type']) {
                'divider' => Html::tag('li', ['class' => 'celerik-usermenu-divider', 'aria-hidden' => 'true'], ''),
                'action' => Html::tag('li', [], $item['action']->render()),
                default => Html::tag('li', [], Html::tag('a', [
                    'class' => 'celerik-usermenu-item',
                    'href' => $item['url'],
                ], Html::escape($item['label']))),
            };
        }
        $detail = $this->detail === null
            ? ''
            : Html::tag('p', ['class' => 'celerik-usermenu-detail'], Html::escape($this->detail));
        $panel = Html::tag('div', ['class' => 'celerik-usermenu-panel'], $detail . Html::tag(
            'ul',
            ['class' => 'list-unstyled mb-0'],
            $items,
        ));

        return Html::tag('details', $this->rootAttributes(['celerik-usermenu']), $summary . $panel);
    }

    /**
     * "Ada Lovelace" → "AL"; one letter for one name.
     */
    private static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name)) ?: [];
        $first = mb_substr($words[0] ?? '', 0, 1);
        $last = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';

        return mb_strtoupper($first . $last);
    }
}
