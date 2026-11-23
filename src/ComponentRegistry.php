<?php

declare(strict_types=1);

namespace Caelrik\Ui;

use Caelrik\Ui\Components\Accordion;
use Caelrik\Ui\Components\AdminLayout;
use Caelrik\Ui\Components\Avatar;
use Caelrik\Ui\Components\Badge;
use Caelrik\Ui\Components\Barcode;
use Caelrik\Ui\Components\Breadcrumb;
use Caelrik\Ui\Components\Card;
use Caelrik\Ui\Components\Carousel;
use Caelrik\Ui\Components\Chart;
use Caelrik\Ui\Components\Chip;
use Caelrik\Ui\Components\ContextMenu;
use Caelrik\Ui\Components\Diagram;
use Caelrik\Ui\Components\FileManager;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Gantt;
use Caelrik\Ui\Components\Gauge;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\ListView;
use Caelrik\Ui\Components\Map;
use Caelrik\Ui\Components\Loader;
use Caelrik\Ui\Components\MediaPlayer;
use Caelrik\Ui\Components\MenuButton;
use Caelrik\Ui\Components\Modal;
use Caelrik\Ui\Components\Navigation;
use Caelrik\Ui\Components\Notification;
use Caelrik\Ui\Components\OrgChart;
use Caelrik\Ui\Components\PdfViewer;
use Caelrik\Ui\Components\Popover;
use Caelrik\Ui\Components\Progress;
use Caelrik\Ui\Components\QrCode;
use Caelrik\Ui\Components\RecordView;
use Caelrik\Ui\Components\Scheduler;
use Caelrik\Ui\Components\Skeleton;
use Caelrik\Ui\Components\Sparkline;
use Caelrik\Ui\Components\SplitButton;
use Caelrik\Ui\Components\Splitter;
use Caelrik\Ui\Components\Stat;
use Caelrik\Ui\Components\Stats;
use Caelrik\Ui\Components\Tabs;
use Caelrik\Ui\Components\TaskBoard;
use Caelrik\Ui\Components\ThemeBuilder;
use Caelrik\Ui\Components\TileLayout;
use Caelrik\Ui\Components\Timeline;
use Caelrik\Ui\Components\Toolbar;
use Caelrik\Ui\Components\Tooltip;
use Caelrik\Ui\Components\TreeList;
use Caelrik\Ui\Components\TreeView;
use Caelrik\Ui\Components\UserMenu;
use InvalidArgumentException;

/**
 * Maps component names to classes. Plugins register their own components here and
 * the matching initializer on the client with `Caelrik.register(name, fn)`.
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
            ->register(Map::class)
            ->register(OrgChart::class)
            ->register(Diagram::class)
            ->register(QrCode::class)
            ->register(Barcode::class)
            ->register(PdfViewer::class)
            ->register(MediaPlayer::class);
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
