<?php

declare(strict_types=1);

namespace Celerik\Ui;

use Celerik\Ui\Components\Accordion;
use Celerik\Ui\Components\AdminLayout;
use Celerik\Ui\Components\Avatar;
use Celerik\Ui\Components\Badge;
use Celerik\Ui\Components\Breadcrumb;
use Celerik\Ui\Components\Card;
use Celerik\Ui\Components\Carousel;
use Celerik\Ui\Components\Chart;
use Celerik\Ui\Components\Chip;
use Celerik\Ui\Components\ContextMenu;
use Celerik\Ui\Components\FileManager;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Gantt;
use Celerik\Ui\Components\Gauge;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\ListView;
use Celerik\Ui\Components\Map;
use Celerik\Ui\Components\Loader;
use Celerik\Ui\Components\MenuButton;
use Celerik\Ui\Components\Modal;
use Celerik\Ui\Components\Navigation;
use Celerik\Ui\Components\Notification;
use Celerik\Ui\Components\Popover;
use Celerik\Ui\Components\Progress;
use Celerik\Ui\Components\RecordView;
use Celerik\Ui\Components\Scheduler;
use Celerik\Ui\Components\Skeleton;
use Celerik\Ui\Components\Sparkline;
use Celerik\Ui\Components\SplitButton;
use Celerik\Ui\Components\Splitter;
use Celerik\Ui\Components\Stat;
use Celerik\Ui\Components\Stats;
use Celerik\Ui\Components\Tabs;
use Celerik\Ui\Components\TaskBoard;
use Celerik\Ui\Components\ThemeBuilder;
use Celerik\Ui\Components\TileLayout;
use Celerik\Ui\Components\Timeline;
use Celerik\Ui\Components\Toolbar;
use Celerik\Ui\Components\Tooltip;
use Celerik\Ui\Components\TreeList;
use Celerik\Ui\Components\TreeView;
use Celerik\Ui\Components\UserMenu;
use InvalidArgumentException;

/**
 * Maps component names to classes. Plugins register their own components here and
 * the matching initializer on the client with `Celerik.register(name, fn)`.
 */
final class ComponentRegistry
{
    /** @var array<string, class-string<Component>> */
    private array $components = [];

    public static function withDefaults(): self
    {
        return (new self())
            ->register(Grid::class)
            ->register(ListView::class)
            ->register(TreeView::class)
            ->register(TreeList::class)
            ->register(TaskBoard::class)
            ->register(Chart::class)
            ->register(Form::class)
            ->register(Scheduler::class)
            ->register(Modal::class)
            ->register(Tabs::class)
            ->register(Notification::class)
            ->register(FileManager::class)
            ->register(ThemeBuilder::class)
            ->register(AdminLayout::class)
            ->register(Navigation::class)
            ->register(Breadcrumb::class)
            ->register(UserMenu::class)
            ->register(MenuButton::class)
            ->register(SplitButton::class)
            ->register(Toolbar::class)
            ->register(ContextMenu::class)
            ->register(Accordion::class)
            ->register(Card::class)
            ->register(Splitter::class)
            ->register(TileLayout::class)
            ->register(RecordView::class)
            ->register(Stat::class)
            ->register(Stats::class)
            ->register(Badge::class)
            ->register(Avatar::class)
            ->register(Chip::class)
            ->register(Tooltip::class)
            ->register(Popover::class)
            ->register(Progress::class)
            ->register(Loader::class)
            ->register(Skeleton::class)
            ->register(Timeline::class)
            ->register(Carousel::class)
            ->register(Sparkline::class)
            ->register(Gauge::class)
            ->register(Gantt::class)
            ->register(Map::class);
    }

    /**
     * @param class-string<Component> $class
     */
    public function register(string $class): self
    {
        if (!is_subclass_of($class, Component::class)) {
            throw new InvalidArgumentException(sprintf('%s must extend %s.', $class, Component::class));
        }

        $this->components[$class::name()] = $class;

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->components[$name]);
    }

    public function create(string $name, ?string $id = null): Component
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException(sprintf('Unknown component "%s".', $name));
        }

        return $this->components[$name]::make($id);
    }

    /**
     * @return array<string, class-string<Component>>
     */
    public function all(): array
    {
        return $this->components;
    }
}
