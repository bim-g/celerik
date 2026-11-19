<?php

declare(strict_types=1);

namespace Celerik\Ui\Components;

use Celerik\Ui\Component;
use Celerik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * Panels that open and close under their headings (the WAI-ARIA accordion; the Telerik
 * PanelBar): a FAQ, a settings page in sections, filters in a sidebar.
 *
 * Each heading holds a button (aria-expanded) that shows or hides its panel, a region named
 * by the heading. One panel is open at a time, unless multiple(); Up, Down, Home and End move
 * between the headings. Without JavaScript, every panel is shown.
 *
 *     Accordion::make('faq')
 *         ->panel('Shipping', 'We ship within two days.', open: true)
 *         ->panel('Returns', Html::raw('<p>Thirty days, <a href="/returns">free</a>.</p>'));
 */
final class Accordion extends Component
{
    /** @var list<array{title: string, content: string|Stringable, open: bool}> */
    private array $panels = [];

    private bool $multiple = false;
    private int $headingLevel = 3;

    public static function name(): string
    {
        return 'accordion';
    }

    /**
     * A panel: its heading, and its content (a component or Html::raw(); a string is text).
     */
    public function panel(string $title, string|Stringable $content, bool $open = false): self
    {
        $this->panels[] = ['title' => $title, 'content' => $content, 'open' => $open];

        return $this;
    }

    /**
     * Panels open and close on their own; by default, opening one closes the others.
     */
    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    /**
     * The headings' level, 2 to 6: one below the heading above the accordion.
     */
    public function headingLevel(int $level): self
    {
        if ($level < 2 || $level > 6) {
            throw new InvalidArgumentException('An accordion heading is h2 to h6.');
        }
        $this->headingLevel = $level;

        return $this;
    }

    public function toArray(): array
    {
        return $this->multiple ? ['multiple' => true] : [];
    }

    public function render(): string
    {
        if ($this->panels === []) {
            throw new LogicException(sprintf('Accordion "%s" has no panels.', $this->id));
        }
        $html = '';
        foreach ($this->panels as $index => $panel) {
            $button = $this->id . '-button-' . ($index + 1);
            $region = $this->id . '-panel-' . ($index + 1);
            // Without JavaScript every panel is shown: the buttons say so until the script
            // sets each panel's own state.
            $heading = Html::tag('h' . $this->headingLevel, ['class' => 'accordion-header'], Html::tag('button', [
                'type' => 'button',
                'class' => 'accordion-button',
                'id' => $button,
                'aria-expanded' => 'true',
                'aria-controls' => $region,
            ], Html::escape($panel['title'])));
            $body = Html::tag('div', [
                'class' => 'accordion-collapse',
                'id' => $region,
                'role' => 'region',
                'aria-labelledby' => $button,
                'data-open' => $panel['open'] ? 'true' : 'false',
            ], Html::tag('div', ['class' => 'accordion-body'], Html::content($panel['content'])));
            $html .= Html::tag('div', ['class' => 'accordion-item'], $heading . $body);
        }

        return Html::tag('div', $this->rootAttributes(['accordion', 'celerik-accordion']), $html);
    }
}
