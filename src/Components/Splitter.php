<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;
use Stringable;

/**
 * Panes side by side (or one above the other) that the user resizes: a file list and a
 * preview, a form and its help (the WAI-ARIA window splitter).
 *
 * Between two panes, a divider is a focusable separator whose value is the first pane's
 * size: Left and Right (Up and Down for panes one above the other) resize it, Home and End
 * go to its limits, Enter collapses it to its minimum and back; it can also be dragged.
 * Sizes are set by JavaScript (no inline styles, for a strict CSP); without it the panes
 * share the space.
 *
 *     Splitter::make('mail')->pane($folders, size: 25, min: 15, label: 'Folders')->pane($message, label: 'Message');
 */
final class Splitter extends Component
{
    public const LABELS = ['resize' => 'Resize {pane}'];

    private string $orientation = 'horizontal';
    private bool $remember = false;

    /** @var list<array{content: string|Stringable, size: ?float, min: float, max: float, label: string}> */
    private array $panes = [];

    /** @var array<string, string> */
    private array $labels = self::LABELS;

    public static function name(): string
    {
        return 'splitter';
    }

    /**
     * 'horizontal': panes side by side; 'vertical': one above the other.
     */
    public function orientation(string $orientation): self
    {
        if (!in_array($orientation, ['horizontal', 'vertical'], true)) {
            throw new InvalidArgumentException('A splitter is horizontal or vertical.');
        }
        $this->orientation = $orientation;

        return $this;
    }

    /**
     * A pane: its content, its size and limits in percent of the splitter, and its name (the
     * divider after it is named after it). A pane without a size shares what is left.
     */
    public function pane(
        string|Stringable $content,
        ?float $size = null,
        float $min = 10,
        float $max = 90,
        string $label = '',
    ): self {
        if ($min < 0 || $max > 100 || $min > $max || ($size !== null && ($size < $min || $size > $max))) {
            throw new InvalidArgumentException('A pane\'s size is between its min and max, within 0 to 100.');
        }
        $this->panes[] = ['content' => $content, 'size' => $size, 'min' => $min, 'max' => $max, 'label' => $label];

        return $this;
    }

    /**
     * Keeps the user's sizes in the browser (localStorage), per splitter id.
     */
    public function remember(bool $remember = true): self
    {
        $this->remember = $remember;

        return $this;
    }

    /**
     * @param array<string, string> $labels
     */
    public function labels(array $labels): self
    {
        foreach ($labels as $key => $text) {
            if (!array_key_exists($key, self::LABELS)) {
                throw new InvalidArgumentException(sprintf('Unknown splitter label "%s".', $key));
            }
            $this->labels[$key] = $text;
        }

        return $this;
    }

    public function toArray(): array
    {
        return array_filter([
            'orientation' => $this->orientation,
            'remember' => $this->remember ?: null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function render(): string
    {
        if (count($this->panes) < 2) {
            throw new LogicException(sprintf('Splitter "%s" needs two panes or more.', $this->id));
        }
        $html = '';
        $last = count($this->panes) - 1;
        foreach ($this->panes as $index => $pane) {
            $id = $this->id . '-pane-' . ($index + 1);
            $html .= Html::tag('div', [
                'class' => 'caelrik-splitter-pane',
                'id' => $id,
                'data-size' => $pane['size'] === null ? null : (string) $pane['size'],
                'data-min' => (string) $pane['min'],
                'data-max' => (string) $pane['max'],
            ], Html::content($pane['content']));
            if ($index === $last) {
                continue;
            }
            // A focusable divider needs a name: the pane's, or its number.
            $paneName = $pane['label'] === '' ? 'pane ' . ($index + 1) : $pane['label'];
            $name = strtr($this->labels['resize'], ['{pane}' => $paneName]);
            $html .= Html::tag('div', [
                'class' => 'caelrik-splitter-handle',
                'role' => 'separator',
                'tabindex' => '0',
                // The divider between side-by-side panes is a vertical line.
                'aria-orientation' => $this->orientation === 'horizontal' ? 'vertical' : 'horizontal',
                'aria-controls' => $id,
                'aria-valuemin' => (string) $pane['min'],
                'aria-valuemax' => (string) $pane['max'],
                'aria-valuenow' => (string) ($pane['size'] ?? round(100 / count($this->panes))),
                'aria-label' => $name,
            ], '');
        }

        return Html::tag(
            'div',
            $this->rootAttributes(['caelrik-splitter', 'caelrik-splitter-' . $this->orientation]),
            $html,
        );
    }
}
