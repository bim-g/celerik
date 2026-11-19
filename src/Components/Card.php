<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use Stringable;

/**
 * A card: a title, text, a picture, content, actions and a footer (Bootstrap's card). With
 * link(), the title is a link whose click area covers the card, while its actions keep
 * working; the card stays one link in the reading order, not a big one.
 *
 *     Card::make('revenue')->title('Revenue')->subtitle('November')
 *         ->text('Up 12% on October.')->link('/reports/revenue')
 *         ->actions(Action::link('export', 'Export', '/reports/revenue.csv'));
 */
final class Card extends Component
{
    private ?string $title = null;
    private int $headingLevel = 3;
    private ?string $subtitle = null;
    private ?string $text = null;
    private string|Stringable|null $body = null;
    private ?string $imageUrl = null;
    private string $imageAlt = '';
    private ?string $url = null;
    private string|Stringable|null $footer = null;

    /** @var list<Action> */
    private array $actions = [];

    public static function name(): string
    {
        return 'card';
    }

    public function title(string $title, int $level = 3): self
    {
        if ($level < 2 || $level > 6) {
            throw new InvalidArgumentException('A card title is h2 to h6.');
        }
        $this->title = $title;
        $this->headingLevel = $level;

        return $this;
    }

    public function subtitle(?string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    /**
     * A paragraph of text under the title.
     */
    public function text(?string $text): self
    {
        $this->text = $text;

        return $this;
    }

    /**
     * Content under the text: a component (a chart, a list) or Html::raw(); a string is text.
     */
    public function body(string|Stringable|null $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * A picture on top: relative, http or https (others are left out). `$alt` describes it;
     * empty, it is decorative.
     */
    public function image(?string $url, string $alt = ''): self
    {
        $this->imageUrl = $url === null ? null : self::safeUrl($url);
        $this->imageAlt = $alt;

        return $this;
    }

    /**
     * Makes the title a link, its click area covering the card.
     */
    public function link(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function actions(Action ...$actions): self
    {
        array_push($this->actions, ...$actions);

        return $this;
    }

    public function footer(string|Stringable|null $footer): self
    {
        $this->footer = $footer;

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        $body = '';
        if ($this->title !== null) {
            $title = Html::escape($this->title);
            if ($this->url !== null) {
                $title = Html::tag('a', ['class' => 'stretched-link celerik-card-link', 'href' => $this->url], $title);
            }
            $body .= Html::tag('h' . $this->headingLevel, ['class' => 'card-title h5'], $title);
        }
        if ($this->subtitle !== null) {
            $subtitle = Html::escape($this->subtitle);
            $body .= Html::tag('p', ['class' => 'card-subtitle mb-2 text-body-secondary'], $subtitle);
        }
        if ($this->text !== null) {
            $body .= Html::tag('p', ['class' => 'card-text'], Html::escape($this->text));
        }
        if ($this->body !== null) {
            $body .= Html::content($this->body);
        }
        $modals = '';
        if ($this->actions !== []) {
            $buttons = '';
            foreach ($this->actions as $action) {
                $buttons .= $action->render(small: true, modal: false);
                $modals .= $action->renderModal();
            }
            // Above the stretched link, so they can be clicked.
            $body .= Html::tag('div', ['class' => 'celerik-card-actions'], $buttons);
        }

        $image = $this->imageUrl === null ? '' : Html::tag('img', [
            'class' => 'card-img-top',
            'src' => $this->imageUrl,
            'alt' => $this->imageAlt,
            'loading' => 'lazy',
        ]);
        $footer = $this->footer === null ? '' : Html::tag(
            'div',
            ['class' => 'card-footer text-body-secondary'],
            Html::content($this->footer),
        );

        return Html::tag(
            'div',
            $this->rootAttributes(['card', 'celerik-card']),
            $image . Html::tag('div', ['class' => 'card-body'], $body) . $footer,
        ) . $modals;
    }

    private static function safeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return null;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === false) {
            return null;
        }

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https'], true) ? $url : null;
    }
}
