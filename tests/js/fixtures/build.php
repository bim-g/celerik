<?php

/**
 * Renders the PHP components to HTML fixtures used by the Jest and axe-core tests,
 * so the JS wrappers are always tested against the markup PHP actually produces.
 */

declare(strict_types=1);

use Caelrik\Ui\Components\Barcode;
use Caelrik\Ui\Components\Chart;
use Caelrik\Ui\Components\MediaPlayer;
use Caelrik\Ui\Components\PdfViewer;
use Caelrik\Ui\Components\QrCode;
use Caelrik\Ui\Support\QrMatrix;
use Caelrik\Ui\Components\ContextMenu;
use Caelrik\Ui\Components\FileManager;
use Caelrik\Ui\Components\Form;
use Caelrik\Ui\Components\Form\Field;
use Caelrik\Ui\Components\Form\Repeater;
use Caelrik\Ui\Components\Form\Section;
use Caelrik\Ui\Components\Accordion;
use Caelrik\Ui\Components\Action;
use Caelrik\Ui\Components\AdminLayout;
use Caelrik\Ui\Components\Breadcrumb;
use Caelrik\Ui\Components\Card;
use Caelrik\Ui\Components\Navigation;
use Caelrik\Ui\Components\UserMenu;
use Caelrik\Ui\Components\Grid;
use Caelrik\Ui\Components\Grid\Column;
use Caelrik\Ui\Components\Grid\Editor;
use Caelrik\Ui\Components\Grid\Filter;
use Caelrik\Ui\Components\ListView;
use Caelrik\Ui\Components\MenuButton;
use Caelrik\Ui\Components\Modal;
use Caelrik\Ui\Components\Notification;
use Caelrik\Ui\Components\Scheduler;
use Caelrik\Ui\Components\SplitButton;
use Caelrik\Ui\Components\Splitter;
use Caelrik\Ui\Components\Avatar;
use Caelrik\Ui\Components\Badge;
use Caelrik\Ui\Components\Chip;
use Caelrik\Ui\Components\Loader;
use Caelrik\Ui\Components\Popover;
use Caelrik\Ui\Components\Progress;
use Caelrik\Ui\Components\Skeleton;
use Caelrik\Ui\Components\Tooltip;
use Caelrik\Ui\Components\Stat;
use Caelrik\Ui\Components\Stats;
use Caelrik\Ui\Components\Tabs;
use Caelrik\Ui\Components\TaskBoard;
use Caelrik\Ui\Components\TreeList;
use Caelrik\Ui\Components\TreeView;
use Caelrik\Ui\Components\RecordView;
use Caelrik\Ui\Components\RecordView\Entry;
use Caelrik\Ui\Components\ThemeBuilder;
use Caelrik\Ui\Components\TileLayout;
use Caelrik\Ui\Components\Toolbar;
use Caelrik\Ui\Components\Gantt;
use Caelrik\Ui\Components\Map;
use Caelrik\Ui\Components\Diagram;
use Caelrik\Ui\Components\OrgChart;
use Caelrik\Ui\Components\Gauge;
use Caelrik\Ui\Components\Sparkline;
use Caelrik\Ui\Components\Timeline;
use Caelrik\Ui\Components\Carousel;
use Caelrik\Ui\Support\Html;
use Caelrik\Ui\Theme\Theme;
use Caelrik\Ui\Upload\UploadRules;

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$fixtures = [
    'scheduler' => Scheduler::make('team-calendar')
        ->title('Team calendar')
        ->view('list')
        ->date('2026-11-05')
        ->timeZone('UTC')
        ->firstDay(1)
        ->events([
            ['id' => 1, 'title' => 'Kick-off', 'start' => '2026-11-03T09:00:00Z', 'end' => '2026-11-03T10:00:00Z'],
            ['id' => 2, 'title' => 'Design review', 'start' => '2026-11-05T14:00:00Z', 'end' => '2026-11-05T15:30:00Z'],
            ['id' => 3, 'title' => 'Holiday', 'start' => '2026-11-06'],
        ])
        ->updateUrl('/events/update')
        ->csrf('test-token'),

    'grid' => Grid::make('users')
        ->caption('Users')
        ->column('id', 'ID')
        ->column('name')
        ->column('email', 'E-mail')
        ->export('copy', 'csv')
        ->pageSize(2)
        ->pageSizes([2, 10])
        ->rows([
            ['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            ['id' => 2, 'name' => 'Alan Turing', 'email' => 'alan@example.com'],
            ['id' => 3, 'name' => 'Grace Hopper', 'email' => 'grace@example.com'],
        ]),

    'grid-filters' => Grid::make('people')
        ->caption('People')
        ->column('name', 'Name', static fn (Column $c) => $c->filter(Filter::text()))
        ->column('role', 'Role', static fn (Column $c) => $c->filter(Filter::select(['admin' => 'Admin', 'user' => 'User'])))
        ->column('active', 'Active', static fn (Column $c) => $c->filter(Filter::boolean()))
        ->column('joined', 'Joined', static fn (Column $c) => $c->filter(Filter::dateRange()))
        ->searchable(false)
        ->rows([
            ['name' => 'Ada Lovelace', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-31 18:30:00'],
            ['name' => 'Alan Turing', 'role' => 'user', 'active' => false, 'joined' => '2026-02-01'],
            ['name' => 'Grace Hopper', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-10'],
            ['name' => 'Linus Torvalds', 'role' => 'user', 'active' => true, 'joined' => null],
        ]),

    'grid-actions' => Grid::make('team')
        ->caption('Team')
        ->column('id', 'ID', static fn (Column $c) => $c->hidden())
        ->column('name', 'Name')
        ->column('team.slug', 'Team')
        ->actions(
            Action::link('edit', 'Edit', '/people/{id}/edit?team={team.slug}'),
            Action::request('delete', 'Delete', '/people/{id}')
                ->method('DELETE')
                ->variant('danger')
                ->confirm('Delete {name}?', 'This cannot be undone.')
                ->csrf('t0ken')
                ->labels(['cancel' => 'Keep']),
        )
        ->rows([
            ['id' => 1, 'name' => 'Ada <Lovelace> & "co"', 'team' => ['slug' => 'a/b c']],
            ['id' => 2, 'name' => 'Alan Turing', 'team' => ['slug' => 'ops']],
            ['id' => 3, 'name' => 'Grace Hopper', 'team' => ['slug' => 'navy']],
        ]),

    'grid-selection' => Grid::make('crew')
        ->caption('Crew')
        ->column('id', 'ID', static fn (Column $c) => $c->hidden())
        ->column('name', 'Name')
        ->selectable()
        ->bulkActions(
            Action::request('remove', 'Remove selected', '/crew/remove')
                ->method('DELETE')
                ->variant('danger')
                ->confirm('Remove {count} people?')
                ->csrf('t0ken'),
            Action::request('mail', 'E-mail them', '/crew/mail'),
        )
        ->pageSize(2)
        ->rows([
            ['id' => 1, 'name' => 'Ada'],
            ['id' => 2, 'name' => 'Alan'],
            ['id' => 3, 'name' => 'Grace'],
        ]),

    'grid-columns' => Grid::make('contacts')
        ->caption('Contacts')
        ->column('id', 'ID', static fn (Column $c) => $c->hidden())
        ->column('name', 'Name')
        ->column('email', 'E-mail')
        ->column('phone', 'Phone', static fn (Column $c) => $c->initiallyHidden())
        ->columnToggle()
        ->rows([['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '555-0100']]),

    'grid-empty' => Grid::make('invites')
        ->caption('Invitations')
        ->column('email', 'E-mail')
        ->emptyState('No invitations yet', 'Invite your team to get started.', Action::link('invite', 'Invite', '/invite'))
        ->rows([]),

    'grid-totals' => Grid::make('expenses')
        ->caption('Expenses')
        ->column('team', 'Team')
        ->column('item', 'Item')
        ->column('amount', 'Amount', static fn (Column $c) => $c->total())
        ->column('hours', 'Hours', static fn (Column $c) => $c->total('avg'))
        ->groupBy('team')
        ->pageSize(3)
        ->pageSizes([3, 10])
        ->rows([
            ['team' => 'Sales', 'item' => 'Flights', 'amount' => 1200.5, 'hours' => 3],
            ['team' => 'Design', 'item' => 'Fonts', 'amount' => 300, 'hours' => 2],
            ['team' => 'Sales', 'item' => 'Hotel', 'amount' => 800, 'hours' => 5],
            ['team' => 'Design', 'item' => 'Laptop', 'amount' => 2500, 'hours' => 4],
            ['team' => 'Support', 'item' => 'Headsets', 'amount' => 150, 'hours' => 1],
        ]),

    'grid-edit' => Grid::make('members')
        ->caption('Members')
        ->column('id', 'ID', static fn (Column $c) => $c->hidden())
        ->column('name', 'Name', static fn (Column $c) => $c->editable(Editor::text(20)->required()))
        ->column('role', 'Role', static fn (Column $c) => $c->editable(Editor::select(['eng' => 'Engineer', 'dir' => 'Director'])))
        ->column('remote', 'Remote', static fn (Column $c) => $c->editable(Editor::boolean()))
        ->column('salary', 'Salary', static fn (Column $c) => $c->editable(Editor::number(min: 0))->total())
        ->column('joined', 'Joined', static fn (Column $c) => $c->editable(Editor::date()))
        ->inlineEdit('/members/{id}')
        ->csrf('t0k3n')
        ->rows([
            ['id' => 1, 'name' => 'Ada', 'role' => 'eng', 'remote' => true, 'salary' => 100, 'joined' => '2024-03-12'],
            ['id' => 2, 'name' => 'Grace <3', 'role' => 'dir', 'remote' => false, 'salary' => 200, 'joined' => null],
        ]),

    'action-form' => Grid::make('people')
        ->caption('People')
        ->column('id', 'ID', static fn (Column $c) => $c->hidden())
        ->column('name', 'Name')
        ->column('role', 'Role')
        ->column('active', 'Active')
        ->actions(
            Action::request('edit', 'Edit', '/people/{id}')
                ->method('PATCH')
                ->csrf('t0k3n')
                ->form(
                    Form::make('person-form')->add(
                        Field::text('name')->required(),
                        Field::select('role', ['admin' => 'Admin', 'user' => 'User']),
                        Field::checkbox('active', 'Active'),
                    ),
                    'Edit {name}',
                    'Save',
                ),
        )
        ->rows([
            ['id' => 1, 'name' => 'Ada', 'role' => 'admin', 'active' => true],
            ['id' => 2, 'name' => 'Alan', 'role' => 'user', 'active' => false],
        ]),

    'listview' => ListView::make('articles')
        ->label('Articles')
        ->title('title')
        ->link('/articles/{id}')
        ->description('summary')
        ->meta('author', 'published')
        ->image('cover', 'coverAlt')
        ->searchable()
        ->sorts(['-published' => 'Newest first', 'title' => 'Title'])
        ->pageSize(3)
        ->actions(Action::request('delete', 'Delete', '/articles/{id}')->method('DELETE')->csrf('t0k3n'))
        ->rows(array_map(
            static fn (int $i): array => [
                'id' => $i,
                'title' => ['Notes on the Engine', 'Computable Numbers', 'A Mathematical Theory', 'The Art of Programming',
                    'Go To Statement', 'On Computing Machinery', 'Structured Programming'][$i - 1],
                'summary' => $i === 2 ? 'On <b>decidable</b> problems.' : 'An essay.',
                'author' => ['Ada', 'Alan', 'Claude', 'Donald', 'Edsger', 'Alan', 'Edsger'][$i - 1],
                'published' => sprintf('2026-0%d-01', $i),
                'cover' => $i === 1 ? '/covers/1.jpg' : ($i === 2 ? 'javascript:alert(1)' : null),
                'coverAlt' => $i === 1 ? 'An engine' : null,
            ],
            range(1, 7),
        )),

    'listview-empty' => ListView::make('notes')
        ->label('Notes')
        ->title('title')
        ->searchable()
        ->emptyState('No notes yet', 'Write your first note.')
        ->rows([]),

    'treeview' => TreeView::make('folders')
        ->label('Folders')
        ->checkable('folders')
        ->url('/folders/{id}/children')
        ->nodes([
            ['id' => 1, 'label' => 'Documents', 'expanded' => true, 'children' => [
                ['id' => 2, 'label' => 'Invoices', 'checked' => true],
                ['id' => 3, 'label' => 'Contracts', 'children' => [
                    ['id' => 4, 'label' => 'Signed'],
                    ['id' => 5, 'label' => 'Drafts'],
                ]],
            ]],
            ['id' => 6, 'label' => 'Pictures', 'hasChildren' => true],
            ['id' => 7, 'label' => 'Archive', 'disabled' => true],
        ]),

    'treeview-links' => TreeView::make('site')
        ->label('Site')
        ->nodes([
            ['label' => 'Guide', 'href' => '/guide', 'expanded' => true, 'children' => [
                ['label' => 'Install <now>', 'href' => '/guide/install'],
                ['label' => 'Bad', 'href' => 'javascript:alert(1)'],
            ]],
            ['label' => 'Components', 'children' => [['label' => 'Grid', 'href' => '/grid']]],
        ]),

    'treeview-select' => TreeView::make('places')
        ->label('Place')
        ->selectable('place')
        ->expandAll()
        ->fromRows([
            ['id' => 'eu', 'parent_id' => null, 'name' => 'Europe'],
            ['id' => 'fr', 'parent_id' => 'eu', 'name' => 'France'],
            ['id' => 'cd', 'parent_id' => 'af', 'name' => 'Congo'],
            ['id' => 'af', 'parent_id' => null, 'name' => 'Africa'],
        ]),

    'treelist' => TreeList::make('tasks')
        ->caption('Tasks')
        ->column('name', 'Task')
        ->column('owner', 'Owner')
        ->column('hours', 'Hours', static fn (Column $c) => $c->className('text-end'))
        ->expanded(1)
        ->actions(Action::request('delete', 'Delete', '/tasks/{id}')->method('DELETE')->csrf('t0k3n'))
        ->rows([
            ['id' => 1, 'name' => 'Design', 'owner' => 'Ada', 'hours' => 12, 'children' => [
                ['id' => 2, 'name' => 'Sketches <v1>', 'owner' => 'Ada', 'hours' => 5, 'children' => [
                    ['id' => 3, 'name' => 'Logo', 'owner' => 'Grace', 'hours' => 2],
                ]],
                ['id' => 4, 'name' => 'Review', 'owner' => 'Alan', 'hours' => 7],
            ]],
            ['id' => 5, 'name' => 'Build', 'owner' => 'Grace', 'hours' => 30, 'children' => [
                ['id' => 6, 'name' => 'API', 'owner' => 'Alan', 'hours' => 20],
            ]],
            ['id' => 7, 'name' => 'Launch', 'owner' => 'Ada', 'hours' => 4],
        ]),

    'taskboard' => TaskBoard::make('sprint')
        ->label('Sprint')
        ->columns(['todo' => 'To do', 'doing' => ['label' => 'In progress', 'limit' => 2], 'done' => 'Done'])
        ->cards([
            ['id' => 1, 'status' => 'todo', 'title' => 'Write <docs>', 'owner' => 'Ada'],
            ['id' => 2, 'status' => 'todo', 'title' => 'Fix login', 'owner' => 'Alan'],
            ['id' => 3, 'status' => 'doing', 'title' => 'Design board', 'owner' => 'Grace'],
            ['id' => 4, 'status' => 'doing', 'title' => 'API', 'owner' => 'Alan'],
            ['id' => 5, 'status' => 'done', 'title' => 'Plan', 'owner' => 'Ada'],
        ])
        ->meta('owner')
        ->link('/tasks/{id}')
        ->moveUrl('/tasks/{id}/move')
        ->csrf('t0k3n'),

    'admin-layout' => AdminLayout::make('app')
        ->brand('Acme', '/')
        ->navigation(Navigation::make('main')->label('Main')
            ->item('Dashboard', '/')
            ->group('Members', static fn (Navigation $group) => $group
                ->item('All members', '/members')
                ->item('Invitations', '/members/invitations', badge: '3'))
            ->item('Settings', '/settings')
            ->current('/members/42?tab=profile'))
        ->userMenu(UserMenu::make('account')->user('Ada Lovelace', 'ada@example.com')
            ->link('Profile', '/profile')
            ->divider()
            ->action(Action::request('logout', 'Sign out', '/logout')->csrf('t0k3n')))
        ->breadcrumb(Breadcrumb::make()->item('Home', '/')->item('Members', '/members')->item('Ada Lovelace'))
        ->title('Ada Lovelace')
        ->content(Html::raw('<p>Member since 2024.</p>')),

    'menus' => Toolbar::make('tools')
        ->label('Members')
        ->add(Action::link('new', 'New member', '/members/new')->variant('primary')->solid())
        ->separator()
        ->add(
            MenuButton::make('export')->label('Export')
                ->link('CSV', '/members.csv')
                ->link('Excel', '/members.xlsx')
                ->link('PDF (soon)', '/members.pdf', disabled: true)
                ->divider()
                ->action(Action::request('email', 'E-mail me the list', '/members/email')->csrf('t0k3n')),
            SplitButton::make('save')
                ->main(Action::request('save', 'Save', '/posts/1')->variant('primary')->solid())
                ->variant('primary', solid: true)
                ->link('Preview', '/posts/1/preview')
                ->action(Action::request('draft', 'Save as draft', '/posts/1/draft')),
        ),

    'contextmenu' => ContextMenu::make('file-menu')
        ->for('#files [data-id]')
        ->label('File actions')
        ->link('Open {name}', '/files/{id}')
        ->divider()
        ->action(Action::request('delete', 'Delete', '/files/{id}')->method('DELETE')->confirm('Delete {name}?')),

    'nav-horizontal' => Navigation::make('site')->label('Site')->horizontal()
        ->item('Home', '/')
        ->group('Products', static fn (Navigation $group) => $group->item('Grid', '/grid')->item('Charts', '/charts'))
        ->group('Company', static fn (Navigation $group) => $group->item('About', '/about'))
        ->current('/grid'),

    'accordion' => Accordion::make('faq')
        ->panel('Shipping', 'We ship within two days.', open: true)
        ->panel('Returns <policy>', Html::raw('<p>Thirty days, <a href="/returns">free</a>.</p>'))
        ->panel('Payment', 'Cards and transfers.'),

    'card' => Card::make('revenue')
        ->title('Revenue')
        ->subtitle('November')
        ->text('Up 12% on October.')
        ->image('/img/chart.png', 'Revenue by week')
        ->link('/reports/revenue')
        ->actions(Action::link('export', 'Export', '/reports/revenue.csv'))
        ->footer('Updated today'),

    'splitter' => Splitter::make('mail')
        ->pane('Inbox, Sent, Drafts', size: 30, min: 20, max: 60, label: 'Folders')
        ->pane('The message.', label: 'Message'),

    'tilelayout' => TileLayout::make('dashboard')->columns(4)
        ->tile('revenue', 'Revenue', 'Up 12% on October.', cols: 2)
        ->tile('orders', 'Orders', Html::raw('<p>48 new orders.</p>'), rows: 2)
        ->tile('visits', 'Visits <today>', '1,204 visits.')
        ->reorderable()->resizable()->remember(),

    'stats' => Stats::make('kpis')->label('This month')->columns(3)->add(
        Stat::make('revenue')->label('Revenue')->value('$12,400')
            ->trend(12.5, 'vs last month')->sparkline([8, 9, 12, 11, 14, 13, 16])->link('/reports/revenue'),
        Stat::make('churn')->label('Churn')->value('2.1%')->goodWhen('down')
            ->trend(0.4, 'vs last month', ' pts')->sparkline([1.6, 1.7, 1.9, 2.1])->color('danger'),
        Stat::make('tickets')->label('Open tickets <support>')->value(1204)->trend(0)->description('Answered within a day.'),
    ),

    'chips' => new class () {
        public function render(): string
        {
            return '<div class="chips">'
                . Chip::make('chip-php')->label('PHP')->removable('skills[]', 'php')
                . Chip::make('chip-js')->label('JavaScript')->removable('skills[]', 'js')
                . Chip::make('chip-go')->label('Go <lang>')->removable('skills[]', 'go')
                    ->avatar(Avatar::make()->person('Go Gopher'))
                . Chip::make('chip-filter')->label('Remote')->checkbox('remote', '1', checked: true)
                . Chip::make()->label('Plain')
                . '</div>';
        }
    },

    'tooltip' => Tooltip::make('save-tip')->text('Saves a draft you can finish later')
        ->trigger(Html::raw('<button type="button" class="btn btn-primary">Save</button>')),

    'popover' => Popover::make('pricing-help')->button('?', 'About pricing')->title('Pricing')
        ->content(Html::raw('<p>Prices include taxes. <a href="/pricing">See all prices</a>.</p>')),

    'display' => new class () {
        public function render(): string
        {
            return '<div>'
                . Badge::make()->text('3')->color('danger')->pill()->hiddenText('unread messages')
                . Avatar::make()->person('Ada Lovelace')->status('online')
                . Avatar::make()->person('Alan Turing')->image('/avatars/alan.jpg')->size('lg')
                . Avatar::make()->person('Grace Hopper')->decorative() . ' Grace Hopper'
                . Progress::make('upload')->label('Uploading report.pdf')->value(40)->showValue()
                . Progress::make('sync')->label('Syncing', visible: false)
                . Progress::make('profile')->label('Profile complete')->value(3)->max(4)->circle()->showValue()
                . Loader::make()->label('Loading orders')
                . Skeleton::make()->label('Loading profile')->avatar()->heading()->lines(3)
                . '</div>';
        }
    },

    'timeline' => Timeline::make('order-history')->label('Order history')
        ->event('Order placed', new DateTimeImmutable('2026-11-01T09:30:00+00:00'), 'Paid by card.', color: 'success')
        ->event('Packed <fast>', 'Nov 2', color: 'info', marker: '2')
        ->event('Shipped', 'Yesterday', Html::raw('<p>Tracking: <a href="/track/1">TR-1</a></p>'), current: true)
        ->event('Delivered', marker: '4', color: 'warning'),

    'carousel' => Carousel::make('news')->label('News')
        ->slide(Html::raw('<h2>Spring sale</h2><p>Up to <a href="/sale">40% off</a>.</p>'), label: 'Spring sale')
        ->slide('A new office <Goma>')
        ->image('/img/lake.jpg', 'Lake Kivu at dawn', caption: 'Our view'),

    'carousel-autoplay' => Carousel::make('promo')->label('Promotions')->autoplay(5)->loop(false)
        ->slide('First')->slide('Second')->slide('Third'),

    'diagram' => Diagram::make('signup')->label('Sign-up <flow>')
        ->node('form', 'Fill in the form')
        ->node('valid', 'Is it valid?', 'decision')
        ->node('done', 'Account created', 'terminal', 'success')
        ->edge('form', 'valid')
        ->edge('valid', 'done', 'Yes')
        ->edge('valid', 'form', 'No'),

    'mediaplayer' => MediaPlayer::make('podcast')->audio()->label('Episode 12')->source('/media/12.mp3', 'audio/mpeg')
        ->transcript([[0, 'Welcome back.', 'Ada'], [65, 'Today: Caelrik.'], [130, 'Goodbye.']]),
    'pdfviewer' => PdfViewer::make('report')->url('/files/report.pdf')->label('Annual report 2026'),
    'qrcode' => QrCode::make('site')->value('https://example.com/?a=1&b=2')->label('Our <website>'),
    'qrcode-hidden' => QrCode::make('wifi')->value('WIFI:T:WPA;S:Office;P:Grüße;;')->label('Office Wi-Fi')
        ->hideValue()->level('H'),
    'barcode' => Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN'),
    'barcode-code128' => Barcode::make('order')->value('Order ABC-123456789xyz'),

    'orgchart' => OrgChart::make('company')->label('Company')->expandLevels(2)
        ->node('grace', 'Grace Hopper', 'Chief executive')
        ->node('ada', 'Ada Lovelace', 'CTO', parent: 'grace', url: '/people/ada')
        ->node('alan', 'Alan Turing', 'Engineer', parent: 'ada')
        ->node('barbara', 'Barbara Liskov', 'Engineer', parent: 'alan')
        ->node('katherine', 'Katherine <Johnson>', 'CFO', parent: 'grace'),

    'map' => Map::make('offices')->label('Our offices')
        ->tiles('/tiles/{z}/{x}/{y}.svg', 'Test tiles', '/tiles/licence')
        ->labels(['zoomIn' => 'Zoom in (test)'])
        ->marker(-1.6792, 29.2228, 'Goma <HQ>', 'Avenue du Lac 12', url: '/offices/goma')
        ->marker(-2.5083, 28.8608, 'Bukavu', color: 'success'),

    'gantt' => Gantt::make('launch')->label('Product launch')->today('2026-11-20')
        ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100)
        ->task('build', 'Build <api>', '2026-11-16', '2026-11-27', progress: 40, depends: ['design'], color: 'success')
        ->task('docs', 'Docs', '2026-11-16', '2026-11-20', depends: ['design'], color: 'secondary')
        ->milestone('release', 'Release', '2026-11-30', depends: ['build', 'docs'], color: 'danger'),

    'gauges' => new class () {
        public function render(): string
        {
            return '<div>'
                . Gauge::make('cpu')->label('CPU <load>')->value(72)->format('{value}%')
                    ->band(0, 60, 'success', 'Normal')->band(60, 85, 'warning', 'High')->band(85, 100, 'danger', 'Critical')
                    ->target(90)
                . Gauge::make('quota')->label('Storage')->value(1250)->range(0, 2000)->format('{value} GB')->linear()
                . '<p>Revenue '
                . Sparkline::make()->label('Revenue')->values([8, 9, 12, 11, 14, 13, 16])->type('area')->markLast()
                . ' and profit '
                . Sparkline::make()->label('Profit')->values([3, -2, 0, 5])->type('bar')->color('success')
                . ' and '
                . Sparkline::make()->values([1, 2, 3])->decorative() . ' 1, 2, 3</p>'
                . '</div>';
        }
    },

    'recordview' => RecordView::make('member')
        ->record([
            'name' => 'Ada <Lovelace>',
            'email' => 'ada@example.com',
            'website' => 'javascript:alert(1)',
            'joined' => '2024-03-01',
            'balance' => 1234.5,
            'admin' => true,
            'status' => 'active',
            'teams' => ['Core', 'Docs'],
            'company' => ['name' => 'Analytical Engines'],
            'phone' => null,
        ])
        ->section('Profile', [
            Entry::text('name', 'Name'),
            Entry::email('email', 'E-mail'),
            Entry::url('website', 'Website'),
            Entry::phone('phone', 'Phone'),
            Entry::text('company.name', 'Company')->span(2),
        ], description: 'Who the member is.', columns: 2)
        ->section('Account', [
            Entry::date('joined', 'Member since', 'j F Y'),
            Entry::number('balance', 'Balance', 2, prefix: '$'),
            Entry::boolean('admin', 'Administrator'),
            Entry::badge('status', 'Status', ['active' => 'success']),
            Entry::list('teams', 'Teams', badges: true),
        ], columns: 3),

    'chart' => Chart::make('sales')
        ->type('bar')
        ->title('Monthly revenue')
        ->labels(['Jan', 'Feb', 'Mar'])
        ->dataset('Revenue', [1200, 1900, 1500]),

    'filemanager' => FileManager::make('files')
        ->title('Shared files')
        ->url('/files/connector')
        ->assetsUrl('/elfinder'),

    'modal' => Modal::make('contact-modal')
        ->title('Contact us')
        ->body(Form::make('modal-form')->add(Field::text('name')->required(), Field::email('email')->required())->submit(null))
        ->cancelButton()
        ->button('Send', 'primary', attributes: ['type' => 'submit', 'form' => 'modal-form'])
        ->staticBackdrop(),

    'tabs' => Tabs::make('account')
        ->label('Account settings')
        ->tab('profile', 'Profile', 'Your name and e-mail.')
        ->tab('security', 'Security', Html::raw('<p>Change your password.</p>'))
        ->tab('billing', 'Billing', 'Invoices and payment methods.')
        ->rememberInHash(),

    'notification' => Notification::make('flash')
        ->success('Profile saved.')
        ->error('Tom & Jerry <img src=x onerror="window.pwned=1">')
        ->warning('Disk almost full.'),

    'form-rich' => Form::make('article')
        ->action('/articles')
        ->csrf('test-token')
        ->add(
            Field::text('title')->required(),
            Field::editor('body', 'Article')
                ->required()
                ->help('Headings, lists and links are allowed.')
                ->value('<h2>Intro</h2><p>Hello <strong>world</strong></p><script>alert(1)</script>'),
            Field::file('cover', 'Cover image')->rules(UploadRules::images()->maxSize('2M'))->upload('/uploads')->required(),
            Field::file('attachments')->rules(UploadRules::documents()->maxFiles(3))->upload('/uploads'),
        )
        ->submit('Publish'),

    'form-summary' => Form::make('order')
        ->errorSummary()
        ->add(
            Field::text('name')->required(),
            Field::email('email')->required(),
            Field::editor('notes')->required(),
        )
        ->submit('Order'),

    'form-layout' => Form::make('signup')
        ->errorSummary()
        ->columns(2)
        ->add(
            Field::text('first_name')->required(),
            Field::text('last_name')->required(),
            Section::make('Address')
                ->description('Where we send the card.')
                ->columnSpan('full')
                ->columns(2)
                ->add(
                    Field::text('street')->columnSpan('full'),
                    Section::fieldset('City')->columnSpan('full')->columns(2)->add(
                        Field::text('city'),
                        Field::text('zip', 'ZIP code'),
                    ),
                ),
            Section::make('Company')
                ->collapsible(collapsed: true)
                ->columnSpan('full')
                ->add(Field::text('vat', 'VAT number')->required()),
        )
        ->submit('Sign up'),

    'form-wizard' => Form::make('join')
        ->wizard()
        ->add(
            Section::make('Account')->add(Field::text('name')->required(), Field::email('email')->required()),
            Section::make('Team')->add(Field::text('team')->required(), Field::text('role')),
            Section::make('Confirm')->add(Field::checkbox('terms', 'I accept the terms')->required()),
        )
        ->submit('Join'),

    'form-wizard-summary' => Form::make('join')
        ->wizard()
        ->errorSummary()
        ->add(
            Section::make('Account')->add(Field::text('name')->required()),
            Section::make('Team')->add(Field::text('team')->required()),
        )
        ->submit('Join'),

    'form-repeater' => Form::make('order')
        ->errorSummary()
        ->add(
            Repeater::make('lines', 'Order lines')
                ->schema(
                    Field::select('product', ['tea' => 'Tea', 'coffee' => 'Coffee'])->placeholder('Choose...')->searchable()->required(),
                    Field::number('qty', 'Quantity')->min(1)->required(),
                    Field::dateRange('delivery'),
                )
                ->min(1)
                ->max(3)
                ->reorderable()
                ->labels(add: 'Add a line', item: 'Line %d'),
        )
        ->submit('Order'),

    'form-wizard-repeater' => Form::make('trip')
        ->wizard()
        ->add(
            Section::make('Travellers')->add(
                Repeater::make('people')->schema(Field::text('name')->required())->defaultItems(0),
            ),
            Section::make('Confirm')->add(Field::checkbox('ok', 'Confirm')),
        )
        ->submit('Book'),

    'themebuilder' => ThemeBuilder::make('builder')
        ->theme(Theme::preset('bootstrap')->color('light', 'primary', '#6750a4')->color('light', 'danger', '#dd3545'))
        ->saveUrl('/theme')
        ->csrf('test-token')
        ->preview(Form::make('preview-form')->add(Field::text('name'))->submit('Send')),

    'form' => Form::make('signup')
        ->action('/signup')
        ->csrf('test-token')
        ->add(
            Field::text('name')->required()->maxLength(80),
            Field::email('email')->required()->help('We never share your e-mail.'),
            Field::tel('phone')->mask('(999) 999-9999'),
            Field::password('password')->required()->minLength(8),
            Field::password('password_confirmation', 'Confirm password')->required()->equalTo('password'),
            Field::select('country', ['fr' => 'France', 'ca' => 'Canada'])->placeholder('Choose...'),
            Field::checkbox('terms', 'I accept the terms')->required(),
        )
        ->errors(['email' => 'This e-mail is already registered.'])
        ->submit('Create account'),

    'form-choices' => Form::make('preferences')
        ->action('/preferences')
        ->csrf('test-token')
        ->add(
            Field::radio('plan', ['free' => 'Free', 'team' => 'Team', 'business' => 'Business'])->required(),
            Field::checkboxes('topics', ['news' => 'News', 'releases' => 'Releases', 'security' => 'Security'])
                ->inline()
                ->required()
                ->help('Choose at least one.'),
            Field::switch('newsletter', 'Send me the newsletter'),
            Field::range('volume')->min(0)->max(10)->value(4),
            Field::color('accent', 'Accent color')->value('#0d6efd'),
            Field::rating('score', 'Your rating')->required(),
            Field::rating('service')->stars(3)->value(2),
        )
        ->errors(['plan' => 'Choose a plan.'])
        ->submit('Save'),

    'form-dates' => Form::make('booking')
        ->action('/bookings')
        ->csrf('test-token')
        ->add(
            Field::date('day')->min('2026-01-01')->max('2026-12-31'),
            Field::time('arrival')->min('08:00')->max('18:00')->step(900),
            Field::datetime('reminder'),
            Field::dateRange('stay')->labels('Arrival', 'Departure')->min('2026-01-01')->required(),
            Field::dateRange('shift', 'Shift', 'time'),
        )
        ->submit('Book'),

    'form-otp' => Form::make('verify')
        ->action('/verify')
        ->csrf('test-token')
        ->add(
            Field::otp('code', 'Verification code')->required(),
            Field::otp('backup', 'Backup code')->codeLength(8)->alphanumeric()->value('ab12'),
        )
        ->errors(['backup' => 'This code has expired.'])
        ->submit('Verify'),

    'form-signature' => Form::make('contract')
        ->action('/sign')
        ->csrf('test-token')
        ->add(
            Field::signature('signature', 'Your signature')->required(),
            Field::signature('witness', 'Witness')->value('Grace Hopper'),
        )
        ->submit('Sign'),

    'form-markdown' => Form::make('post')
        ->action('/posts')
        ->csrf('test-token')
        ->add(
            Field::markdown('body', 'Body')->required(),
            Field::markdown('notes', 'Notes')->value("## Notes

**Hi** <img src=x onerror=alert(1)>"),
        )
        ->submit('Publish'),

    'form-code' => Form::make('snippet')
        ->action('/snippets')
        ->csrf('test-token')
        ->add(
            Field::code('source', 'Source')->language('javascript')->value("// Hi
const a = 1;")->help('JavaScript.'),
            Field::code('config', 'Configuration')->language('json')->required()->tabIndents(),
        )
        ->submit('Save'),

    'form-image' => Form::make('profile')
        ->action('/profile')
        ->csrf('test-token')
        ->add(Field::file('photo', 'Photo')->imageEditor(['free', '1:1'], 300))
        ->submit('Save'),

    'form-key-value' => Form::make('webhook')
        ->action('/webhook')
        ->csrf('test-token')
        ->add(
            Field::keyValue('headers', 'Headers')->required()->keySuggestions(['Accept', 'Authorization']),
            Field::keyValue('meta', 'Metadata')->value(['env' => 'prod', 'team' => 'billing']),
        )
        ->errors(['meta.1.key' => 'This key is reserved.'])
        ->submit('Save'),

    'form-select' => Form::make('assign')
        ->action('/assign')
        ->csrf('test-token')
        ->add(
            Field::select('country', ['fr' => 'France', 'ca' => 'Canada', 'cd' => 'DR Congo'])
                ->searchable()
                ->placeholder('Choose a country')
                ->required()
                ->help('Type to filter.'),
            Field::select('languages', ['en' => 'English', 'fr' => 'French', 'ln' => 'Lingala'])
                ->multiple()
                ->searchable()
                ->value(['fr', 'ln']),
            Field::select('author', ['7' => 'Grace Hopper'])
                ->remote('/authors/options')
                ->placeholder('Search people')
                ->value(7),
            Field::tags('skills', ['PHP', 'Laravel', 'Vue'])
                ->value(['Laravel', 'Go'])
                ->minTags(2)
                ->maxTags(3)
                ->addText('Add “%s”'),
        )
        ->submit('Assign'),
];

foreach ($fixtures as $name => $component) {
    file_put_contents(__DIR__ . '/' . $name . '.html', $component->render() . "\n");
}

// QR codes of every version and level, and barcodes, decoded by ZXing in codes.test.js.
$codes = ['qr' => [], 'barcodes' => []];
$texts = ['https://example.com/a?b=c', '0123456789012345', 'HELLO WORLD 42', 'Grüße — café ✓'];
foreach (QrMatrix::LEVELS as $level) {
    foreach (range(1, 40) as $version) {
        $text = $texts[$version % 4];
        $codes['qr'][] = ['text' => $text, 'level' => $level, 'version' => $version, 'rows' => array_map(
            static fn (array $row): string => implode('', array_map('intval', $row)),
            QrMatrix::encode($text, $level, $version),
        )];
    }
}
foreach (['Hello, World!', '1234567890', 'ABC123456789xyz', "AB	cd", '12345', '9', 'Order 2026-11-22'] as $text) {
    $codes['barcodes'][] = ['type' => 'code128', 'text' => $text, 'modules' => Barcode::modules($text)[0]];
}
foreach (['978020137962', '4006381333931', '001234567890'] as $text) {
    [$modules, $full] = Barcode::modules($text, 'ean13');
    $codes['barcodes'][] = ['type' => 'ean13', 'text' => $full, 'modules' => $modules];
}
file_put_contents(__DIR__ . '/codes.json', json_encode($codes, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

// The stylesheet of the bootstrap preset (presets.test.js), and the teal browser-test theme
// with a font and a whole-number radius: resources/js/theme-css.js must write the same text
// (theme-css.test.js).
file_put_contents(__DIR__ . '/theme-bootstrap.css', Theme::preset('bootstrap')->toCss());
$custom = Theme::fromJson((string) file_get_contents(dirname(__DIR__, 2) . '/browser/custom-theme.json'))
    ->fontFamily('"Inter", system-ui, sans-serif')
    ->radius(1.0);
file_put_contents(__DIR__ . '/theme-custom.css', $custom->toCss());

echo 'Wrote ' . count($fixtures) . " fixtures, 2 theme stylesheets and codes.json to tests/js/fixtures\n";
