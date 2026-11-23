<?php

declare(strict_types=1);

namespace Caelrik\Ui\Components;

use Caelrik\Ui\Component;
use Caelrik\Ui\Support\Html;
use InvalidArgumentException;
use LogicException;

/**
 * A row of stat tiles: a list, named by its label, in 1 to 6 columns (two on small screens,
 * one on phones).
 *
 *     Stats::make('kpis')->label('This month')->columns(4)->add($revenue, $orders, $visits, $churn);
 */
final class Stats extends Component
{
    private ?string $label = null;
    private int $columns = 4;

    /** @var list<Stat> */
    private array $stats = [];

    public static function name(): string
    {
        return 'stats';
    }

    /**
     * Names the list for screen readers ("Key figures, list, 4 items").
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function columns(int $columns): self
    {
        if ($columns < 1 || $columns > 6) {
            throw new InvalidArgumentException('A row of stats has 1 to 6 columns.');
        }
        $this->columns = $columns;

        return $this;
    }

    public function add(Stat ...$stats): self
    {
        foreach ($stats as $stat) {
            $this->stats[] = $stat;
        }

        return $this;
    }

    public function toArray(): array
    {
        return [];
    }

    public function render(): string
    {
        if ($this->stats === []) {
            throw new LogicException(sprintf('Stats "%s" has no stat.', $this->id));
        }
        $items = '';
        foreach ($this->stats as $stat) {
            $items .= Html::tag('li', [], $stat->render());
        }
        $attributes = $this->rootAttributes(['caelrik-stats', 'caelrik-stats-cols-' . $this->columns]);
        $attributes['aria-label'] = $this->label;

        return Html::tag('ul', $attributes, $items);
    }
}
