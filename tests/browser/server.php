<?php

/**
 * Test page for the browser accessibility tests (tests/browser/*.spec.mjs), started by
 * Playwright's webServer:
 *
 *     php -S 127.0.0.1:8123 tests/browser/server.php
 *
 *   GET /?mode=dark&custom=1         every component on one page (custom: the teal theme)
 *   GET /assets/...                  the bundle in public/ (run "npm run build" first)
 *   GET /vendor/...                  Bootstrap, jQuery and axe-core from node_modules
 *   GET /elfinder/...                elFinder's assets from vendor/studio-42/elfinder
 *   GET|POST /files/connector        the File Manager connector, on a temporary folder
 *
 * Everything is served locally so results do not depend on a CDN.
 */

declare(strict_types=1);

use Celerik\Ui\Assets;
use Celerik\Ui\Components\Accordion;
use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\AdminLayout;
use Celerik\Ui\Components\Breadcrumb;
use Celerik\Ui\Components\Card;
use Celerik\Ui\Components\Chart;
use Celerik\Ui\Components\ContextMenu;
use Celerik\Ui\Components\FileManager;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Components\Form\Repeater;
use Celerik\Ui\Components\Form\Section;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Editor;
use Celerik\Ui\Components\Grid\Filter;
use Celerik\Ui\Components\ListView;
use Celerik\Ui\Components\MenuButton;
use Celerik\Ui\Components\Modal;
use Celerik\Ui\Components\Navigation;
use Celerik\Ui\Components\Notification;
use Celerik\Ui\Components\Scheduler;
use Celerik\Ui\Components\SplitButton;
use Celerik\Ui\Components\Splitter;
use Celerik\Ui\Components\Tabs;
use Celerik\Ui\Components\TaskBoard;
use Celerik\Ui\Components\TreeList;
use Celerik\Ui\Components\TreeView;
use Celerik\Ui\Components\UserMenu;
use Celerik\Ui\Components\ThemeBuilder;
use Celerik\Ui\Components\TileLayout;
use Celerik\Ui\Components\Toolbar;
use Celerik\Ui\Data\ArrayDataSource;
use Celerik\Ui\Data\InvalidCardMove;
use Celerik\Ui\Data\InvalidCellEdit;
use Celerik\Ui\FileManager\FileManagerConfig;
use Celerik\Ui\FileManager\FileManagerConnector;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Theme\Theme;
use Celerik\Ui\Upload\UploadRules;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$static = [
    '/assets/' => $root . '/public',
    '/elfinder/' => $root . '/vendor/studio-42/elfinder',
    '/vendor/bootstrap/' => $root . '/node_modules/bootstrap/dist',
    '/vendor/jquery/' => $root . '/node_modules/jquery/dist',
    '/vendor/axe-core/' => $root . '/node_modules/axe-core',
];
foreach ($static as $prefix => $directory) {
    if (!str_starts_with($path, $prefix)) {
        continue;
    }
    $base = realpath($directory);
    $file = realpath($directory . '/' . substr($path, strlen($prefix)));
    if ($base === false || $file === false || !is_file($file) || !str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        exit('Not found. Run "composer install", "npm install" and "npm run build".');
    }
    $types = [
        'css' => 'text/css', 'js' => 'text/javascript', 'map' => 'application/json', 'png' => 'image/png',
        'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'mp3' => 'audio/mpeg', 'wav' => 'audio/wav',
        'json' => 'application/json', 'woff' => 'font/woff', 'woff2' => 'font/woff2',
    ];
    header('Content-Type: ' . ($types[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($file);
    exit;
}

$filesRoot = sys_get_temp_dir() . '/celerik-browser-tests/files';
if (!is_dir($filesRoot)) {
    mkdir($filesRoot . '/Reports', 0700, true);
    file_put_contents($filesRoot . '/welcome.txt', "Test file.\n");
}

if ($path === '/files/connector') {
    FileManagerConnector::run(
        FileManagerConfig::local($filesRoot, UploadRules::images())->alias('Shared files'),
        authorized: true,
    );
}

// Scheduler endpoints: accept every change and echo the event, as a real endpoint would.
if (in_array($path, ['/events/create', '/events/update', '/events/delete'], true)) {
    $event = json_decode((string) file_get_contents('php://input'), true) ?? [];
    $event['id'] ??= 'new-' . bin2hex(random_bytes(4));
    header('Content-Type: application/json');
    echo json_encode(['data' => $event, 'meta' => []]);
    exit;
}

// A remote select and its endpoint, on the same field definition.
$reviewer = Field::select('reviewer', ['1' => 'Ada Lovelace'])->remote('/people/options', limit: 5)->value('1');
if ($path === '/people/options') {
    $people = new ArrayDataSource(array_map(
        static fn (int $id, string $name): array => ['id' => $id, 'name' => $name, 'email' => strtolower($name) . '@example.com'],
        range(1, 6),
        ['Ada Lovelace', 'Alan Turing', 'Grace Hopper', 'Barbara Liskov', 'Margaret Hamilton', 'Katherine Johnson'],
    ));
    header('Content-Type: application/json');
    echo json_encode($reviewer->respond($people, $_GET));
    exit;
}

// A bulk action: the selected rows arrive as ids[].
if ($path === '/staff/bulk' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    $ids = is_array($_POST['ids'] ?? null) ? $_POST['ids'] : [];
    $valid = ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') === 'test-token';
    http_response_code($valid ? 200 : 419);
    echo json_encode(['message' => $valid ? count($ids) . ' people were deactivated: ' . implode(', ', $ids) . '.' : 'Expired.']);
    exit;
}

// An action's form (Action::form()): a name already taken is refused on its field.
if (preg_match('#^/staff/([^/]+)/edit$#', $path, $match) === 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'test-token' || ($_POST['_method'] ?? '') !== 'PATCH') {
        http_response_code(419);
        echo json_encode(['message' => 'The page expired. Reload it and try again.']);
        exit;
    }
    $name = trim((string) ($_POST['name'] ?? ''));
    if (in_array($name, ['Alan Turing', 'Grace Hopper'], true)) {
        http_response_code(422);
        echo json_encode(['message' => 'Invalid.', 'errors' => ['name' => ['This name is taken.']]]);
        exit;
    }
    echo json_encode(['message' => "$name was updated."]);
    exit;
}

// Row actions: answer like a real endpoint, with a message (the request must carry the token).
$isPost = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'DELETE'], true);
if ($isPost && preg_match('#^/(users|staff)/([^/]+)(/deactivate)?$#', $path, $match) === 1) {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'test-token') {
        http_response_code(419);
        echo json_encode(['message' => 'The page expired. Reload it and try again.']);
        exit;
    }
    $who = rawurldecode($match[2]);
    echo json_encode(['message' => $match[1] === 'users' ? "User $who was deleted." : "$who was deactivated."]);
    exit;
}

// A server-side grid with column filters, and its endpoint, on the same grid definition.
$staff = Grid::make('staff')
    ->caption('Staff')
    ->url('/staff/data')
    ->pageSize(5)
    ->column('name', 'Name', static fn (Column $c) => $c->filter(Filter::text()))
    ->column('role', 'Role', static fn (Column $c) => $c->filter(Filter::select(['admin' => 'Admin', 'user' => 'User'])))
    ->column('active', 'Active', static fn (Column $c) => $c->filter(Filter::boolean()))
    ->column('joined', 'Joined', static fn (Column $c) => $c->filter(Filter::dateRange()))
    ->actions(
        Action::request('edit', 'Edit', '/staff/{name}/edit')
            ->method('PATCH')
            ->csrf('test-token')
            ->form(Form::make('staff-form')->add(
                Field::text('name', 'Name')->required(),
                Field::select('role', ['admin' => 'Admin', 'user' => 'User'], 'Role'),
            ), 'Edit {name}', 'Save'),
        Action::request('deactivate', 'Deactivate', '/staff/{name}/deactivate')->csrf('test-token'),
    )
    ->selectable('name')
    ->bulkActions(
        Action::request('deactivate', 'Deactivate selected', '/staff/bulk')
            ->confirm('Deactivate {count} people?')
            ->csrf('test-token'),
    );
if ($path === '/staff/data') {
    $rows = [
        ['name' => 'Ada Lovelace', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-31'],
        ['name' => 'Alan Turing', 'role' => 'user', 'active' => false, 'joined' => '2026-02-01'],
        ['name' => 'Grace Hopper', 'role' => 'admin', 'active' => true, 'joined' => '2026-01-10'],
        ['name' => 'Barbara Liskov', 'role' => 'user', 'active' => true, 'joined' => '2026-03-15'],
        ['name' => 'Margaret Hamilton', 'role' => 'admin', 'active' => false, 'joined' => '2025-12-24'],
        ['name' => 'Katherine Johnson', 'role' => 'user', 'active' => true, 'joined' => '2026-02-20'],
    ];
    header('Content-Type: application/json');
    echo json_encode($staff->respond(new ArrayDataSource($rows), $_GET));
    exit;
}

// A server-side grid grouped by team, with column totals, and its endpoint.
$budget = Grid::make('budget')
    ->caption('Budget')
    ->url('/budget/data')
    ->pageSize(4)
    ->column('team', 'Team')
    ->column('item', 'Item', static fn (Column $c) => $c->editable(Editor::text(30)->required()))
    ->column('amount', 'Amount', static fn (Column $c) => $c->total()->editable(Editor::number(min: 0)))
    ->column('hours', 'Hours', static fn (Column $c) => $c->total('avg', 1))
    ->groupBy('team')
    ->rowTitle('item')
    ->inlineEdit('/budget/{item}')
    ->csrf('test-token');
// An inline edit: checked by the grid, then refused when the item exists, as a database
// constraint would. Nothing is saved.
if (preg_match('#^/budget/([^/]+)$#', $path) === 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    if (($_POST['_token'] ?? '') !== 'test-token' || ($_POST['_method'] ?? '') !== 'PATCH') {
        http_response_code(419);
        echo json_encode(['message' => 'The page expired. Reload it and try again.']);
        exit;
    }
    try {
        $edit = $budget->cellEdit($_POST);
        if ($edit->field === 'item' && in_array($edit->value, ['Flights', 'Hotel', 'Laptop'], true)) {
            throw new InvalidCellEdit('item', 'This item already exists.');
        }
        echo json_encode($edit->answer());
    } catch (InvalidCellEdit $refusal) {
        http_response_code(422);
        echo json_encode($refusal->toArray());
    }
    exit;
}
if ($path === '/budget/data') {
    $rows = [
        ['team' => 'Sales', 'item' => 'Flights', 'amount' => 1200.5, 'hours' => 3],
        ['team' => 'Design', 'item' => 'Fonts', 'amount' => 300, 'hours' => 2],
        ['team' => 'Sales', 'item' => 'Hotel', 'amount' => 800, 'hours' => 5],
        ['team' => 'Design', 'item' => 'Laptop', 'amount' => 2500, 'hours' => 4],
        ['team' => 'Support', 'item' => 'Headsets', 'amount' => 150, 'hours' => 1],
        ['team' => 'Sales', 'item' => 'Dinner', 'amount' => 95, 'hours' => 2],
    ];
    header('Content-Type: application/json');
    echo json_encode($budget->respond(new ArrayDataSource($rows), $_GET));
    exit;
}

// A server-side list view with a pager, a search box and a sort choice, and its endpoint.
$team = ListView::make('team')
    ->label('Team')
    ->url('/team/data')
    ->title('name')
    ->link('/team/{id}')
    ->description('role')
    ->meta('city', 'since')
    ->searchable()
    ->sorts(['name' => 'Name', '-since' => 'Newest first'])
    ->pageSize(4);
if ($path === '/team/data') {
    $rows = [
        ['id' => 1, 'name' => 'Ada Lovelace', 'role' => 'Engineer', 'city' => 'London', 'since' => 2019],
        ['id' => 2, 'name' => 'Alan Turing', 'role' => 'Researcher', 'city' => 'Manchester', 'since' => 2021],
        ['id' => 3, 'name' => 'Grace Hopper', 'role' => 'Director', 'city' => 'New York', 'since' => 2018],
        ['id' => 4, 'name' => 'Katherine Johnson', 'role' => 'Researcher', 'city' => 'Hampton', 'since' => 2022],
        ['id' => 5, 'name' => 'Margaret Hamilton', 'role' => 'Engineer', 'city' => 'Boston', 'since' => 2020],
        ['id' => 6, 'name' => 'Barbara Liskov', 'role' => 'Engineer', 'city' => 'Boston', 'since' => 2023],
    ];
    header('Content-Type: application/json');
    echo json_encode($team->respond(new ArrayDataSource($rows), $_GET));
    exit;
}

// A checkable tree whose Pictures folder loads its children from an endpoint.
$folders = TreeView::make('folders')
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
    ]);
if ($path === '/folders/6/children') {
    header('Content-Type: application/json');
    echo json_encode(TreeView::children([
        ['id' => 61, 'label' => 'Holidays'],
        ['id' => 62, 'label' => 'Family'],
    ]));
    exit;
}

// A task board and its move endpoint: Plan cannot go back to To do, as a rule of the app.
$board = TaskBoard::make('sprint')
    ->label('Sprint')
    ->columns(['todo' => 'To do', 'doing' => ['label' => 'In progress', 'limit' => 2], 'done' => 'Done'])
    ->cards([
        ['id' => 1, 'status' => 'todo', 'title' => 'Write docs', 'owner' => 'Ada'],
        ['id' => 2, 'status' => 'todo', 'title' => 'Fix login', 'owner' => 'Alan'],
        ['id' => 3, 'status' => 'doing', 'title' => 'Design board', 'owner' => 'Grace'],
        ['id' => 4, 'status' => 'done', 'title' => 'Plan', 'owner' => 'Ada'],
    ])
    ->meta('owner')
    ->moveUrl('/tasks/{id}/move')
    ->csrf('test-token');
if (preg_match('#^/tasks/(\d+)/move$#', $path, $match) === 1 && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json');
    if (($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '') !== 'test-token' || ($_POST['_method'] ?? '') !== 'PATCH') {
        http_response_code(419);
        echo json_encode(['message' => 'The page expired.']);
        exit;
    }
    try {
        $move = $board->cardMove($_POST);
        if ($match[1] === '4' && $move->column === 'todo') {
            throw new InvalidCardMove('A planned task cannot go back to To do.');
        }
        echo json_encode(['message' => 'Moved.']);
    } catch (InvalidCardMove $refusal) {
        http_response_code(422);
        echo json_encode($refusal->toArray());
    }
    exit;
}

// Bootstrap is the only look; ?theme= is ignored.
$theme = 'bootstrap';
$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';

// A custom theme (tests/browser/custom-theme.json) generated by Theme::toCss().
if ($path === '/custom-theme.css') {
    header('Content-Type: text/css');
    echo Theme::fromJson((string) file_get_contents(__DIR__ . '/custom-theme.json'))->toCss();
    exit;
}
$customTheme = isset($_GET['custom']) ? '/custom-theme.css' : null;

// ThemeBuilder endpoints. Each saved theme goes to a temporary file named after it, which
// the tests read back with /theme-saved?name=… (tests run in parallel).
$savedTheme = static fn (string $name): string => sys_get_temp_dir() . '/celerik-browser-tests/saved-' . md5($name) . '.json';
if ($path === '/theme-save' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
    if (!hash_equals('test-token', (string) $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
    try {
        $saved = ThemeBuilder::fromRequest($_POST, (string) file_get_contents('php://input'));
    } catch (InvalidArgumentException $exception) {
        http_response_code(422);
        exit($exception->getMessage());
    }
    file_put_contents($savedTheme($saved->getName()), $saved->toJson());
    if ($json) {
        header('Content-Type: application/json');
        echo json_encode(['data' => $saved->toArray(), 'meta' => []]);
    } else {
        header('Location: /theme-builder', true, 303);
    }
    exit;
}
if ($path === '/theme-saved') {
    header('Content-Type: application/json');
    readfile($savedTheme((string) ($_GET['name'] ?? '')));
    exit;
}
// Theme::toCss() for a posted theme JSON: the tests compare it with the builder's download.
if ($path === '/theme-css' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: text/css');
    echo Theme::fromJson((string) file_get_contents('php://input'))->toCss();
    exit;
}

// The ThemeBuilder, previewing real components: GET /theme-builder?mode=dark
if ($path === '/orgchart') {
    require __DIR__ . '/orgchart-page.php';
    exit;
}

if ($path === '/map') {
    require __DIR__ . '/map-page.php';
    exit;
}

// Map tiles: the same local picture for every tile.
if (preg_match('#^/tiles/\d+/\d+/\d+\.svg$#', $path) === 1) {
    header('Content-Type: image/svg+xml');
    readfile(__DIR__ . '/map-tile.svg');
    exit;
}

if ($path === '/gantt') {
    require __DIR__ . '/gantt-page.php';
    exit;
}

if ($path === '/gauges') {
    require __DIR__ . '/gauge-page.php';
    exit;
}

if ($path === '/timeline-carousel') {
    require __DIR__ . '/timeline-carousel-page.php';
    exit;
}

// The documentation's carousel pictures.
if (preg_match('#^/images/carousel/([a-z]+\.svg)$#', $path, $picture) === 1) {
    $file = dirname(__DIR__, 2) . '/site/public/images/carousel/' . $picture[1];
    if (is_file($file)) {
        header('Content-Type: image/svg+xml');
        readfile($file);
        exit;
    }
}

if ($path === '/display') {
    require __DIR__ . '/display-page.php';
    exit;
}

if ($path === '/dashboard') {
    require __DIR__ . '/dashboard-page.php';
    exit;
}

if ($path === '/theme-builder') {
    require __DIR__ . '/theme-builder-page.php';
    exit;
}

// An admin page: the layout, its navigation, breadcrumb and user menu around a grid.
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    $layout = AdminLayout::make('app')
        ->brand('Acme', '/admin')
        ->navigation(Navigation::make('main')->label('Main')
            ->item('Dashboard', '/admin')
            ->group('Members', static fn (Navigation $group) => $group
                ->item('All members', '/admin/members')
                ->item('Invitations', '/admin/members/invitations', badge: '3'))
            ->item('Settings', '/admin/settings')
            ->current($path))
        ->userMenu(UserMenu::make('account')->user('Ada Lovelace', 'ada@example.com')
            ->link('Profile', '/admin/profile')
            ->divider()
            ->action(Action::request('logout', 'Sign out', '/logout')->csrf('test-token')))
        ->breadcrumb(Breadcrumb::make()->item('Dashboard', '/admin')->item('Members', '/admin/members')->item('Ada Lovelace'))
        ->title('Ada Lovelace')
        ->appBar(Navigation::make('site')->label('Site')->horizontal()
            ->group('Help', static fn (Navigation $group) => $group
                ->item('Guide', '/admin/help/guide')
                ->item('Shortcuts', '/admin/help/shortcuts')))
        ->content(Html::raw(
            Toolbar::make('member-tools')->label('Member')
                ->add(Action::link('edit', 'Edit', '/admin/members/42/edit')->variant('primary')->solid())
                ->separator()
                ->add(
                    MenuButton::make('export')->label('Export')
                        ->link('CSV', '/admin/members.csv')
                        ->link('PDF (soon)', '/admin/members.pdf', disabled: true)
                        ->divider()
                        ->action(Action::request('deactivate', 'Deactivate', '/staff/Ada%20Lovelace/deactivate')
                            ->confirm('Deactivate Ada Lovelace?')
                            ->csrf('test-token')),
                    SplitButton::make('save')
                        ->main(Action::link('save', 'Save', '/admin/members/42/save')->variant('primary')->solid())
                        ->variant('primary', solid: true)
                        ->link('Save as draft', '/admin/members/42/draft'),
                )
                ->render()
            . '<h2 class="h5 mt-4">Files</h2><ul id="files" class="list-unstyled">'
            . '<li data-id="7" data-name="Contract.pdf" tabindex="0">Contract.pdf</li>'
            . '<li data-id="8" data-name="Photo.jpg" tabindex="0">Photo.jpg</li></ul>'
            . ContextMenu::make('file-menu')->for('#files [data-id]')->label('File actions')
                ->link('Open {name}', '/admin/files/{id}')
                ->link('Download', '/admin/files/{id}/download')
                ->render()
            . '<div class="row g-3 my-3"><div class="col-md-6">'
            . Card::make('member-card')->title('Ada Lovelace', level: 2)->subtitle('Engineer')
                ->text('Member since 2024.')->link('/admin/members/42/profile')
                ->actions(Action::link('message', 'Send a message', '/admin/members/42/message'))
                ->render()
            . '</div><div class="col-md-6">'
            . Accordion::make('member-faq')->headingLevel(2)
                ->panel('Access', 'Admin of two teams.', open: true)
                ->panel('Billing', 'Paid by the company.')
                ->panel('Notes', 'None yet.')
                ->render()
            . '</div></div>'
            . Splitter::make('member-split')
                ->pane('Files of Ada.', size: 30, min: 20, max: 70, label: 'Files')
                ->pane('Preview of the selected file.', label: 'Preview')
                ->render()
            . TileLayout::make('member-tiles')->columns(3)->addClass('my-3')
                ->tile('teams', 'Teams', 'Two teams.', cols: 2)
                ->tile('logins', 'Logins', '12 this month.')
                ->tile('tasks', 'Tasks', 'Three open tasks.')
                ->reorderable()->resizable()
                ->render()
            . Grid::make('admin-members')->caption('Recent activity')->column('what', 'What')->column('when', 'When')
                ->rows([['what' => 'Signed in', 'when' => 'Today'], ['what' => 'Changed role', 'when' => 'Yesterday']])
                ->render(),
        ));
    $mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
    ?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Admin layout (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
    <?= Assets::tags('/assets') ?>
</head>
<body>
<?= $layout->render() ?>
</body>
</html>
    <?php
    exit;
}

if ($path !== '/') {
    http_response_code(404);
    exit('Not found.');
}

$components = [
    Grid::make('users')
        ->caption('Users')
        ->column('id', 'ID')
        ->column('name', null, static fn (Column $c) => $c->toggleable(false))
        ->column('email', 'E-mail')
        ->columnToggle()
        ->rowTitle('name')
        ->actions(
            Action::link('view', 'View', '/users/{id}'),
            Action::request('delete', 'Delete', '/users/{id}')
                ->method('DELETE')
                ->variant('danger')
                ->confirm('Delete {name}?', 'This cannot be undone.')
                ->csrf('test-token'),
        )
        ->export('copy', 'csv')
        ->pageSize(2)
        ->pageSizes([2, 10])
        ->rows([
            ['id' => 1, 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            ['id' => 2, 'name' => 'Alan Turing', 'email' => 'alan@example.com'],
            ['id' => 3, 'name' => 'Grace Hopper', 'email' => 'grace@example.com'],
        ]),

    $staff,

    $budget,

    $team,

    $folders,

    $board,

    TreeList::make('plan')
        ->caption('Plan')
        ->column('name', 'Task')
        ->column('owner', 'Owner')
        ->column('hours', 'Hours')
        ->expanded(1)
        ->rows([
            ['id' => 1, 'name' => 'Design', 'owner' => 'Ada', 'hours' => 12, 'children' => [
                ['id' => 2, 'name' => 'Sketches', 'owner' => 'Ada', 'hours' => 5, 'children' => [
                    ['id' => 3, 'name' => 'Logo', 'owner' => 'Grace', 'hours' => 2],
                ]],
                ['id' => 4, 'name' => 'Review', 'owner' => 'Alan', 'hours' => 7],
            ]],
            ['id' => 5, 'name' => 'Build', 'owner' => 'Grace', 'hours' => 30],
        ]),

    Chart::make('revenue')
        ->type('line')
        ->title('Revenue per region')
        ->labels(['Jan', 'Feb', 'Mar', 'Apr'])
        ->dataset('North', [1200, 1900, 1500, 2100])
        ->dataset('South', [900, 1100, 1700, 1600])
        ->dataset('East', [600, 800, 1000, 1400])
        ->height(260),

    Form::make('signup')
        ->action('/signup')
        ->csrf('test-token')
        ->add(
            Field::text('name')->required()->maxLength(80),
            Field::email('email')->required()->help('We never share your e-mail.'),
            Field::tel('phone')->mask('(999) 999-9999'),
            Field::select('country', ['fr' => 'France', 'ca' => 'Canada'])->placeholder('Choose...'),
            Field::checkbox('terms', 'I accept the terms')->required(),
        )
        ->errors(['email' => 'This e-mail is already registered.'])
        ->submit('Create account'),

    Form::make('preferences')
        ->action('/preferences')
        ->csrf('test-token')
        ->add(
            Field::radio('plan', ['free' => 'Free', 'team' => 'Team', 'business' => 'Business'])->required(),
            Field::checkboxes('topics', ['news' => 'News', 'releases' => 'Releases'])->inline()->help('Choose at least one.'),
            Field::switch('newsletter', 'Send me the newsletter')->value(true),
            Field::range('volume')->min(0)->max(10)->value(4),
            Field::color('accent', 'Accent color')->value('#0d6efd'),
            Field::rating('satisfaction')->value(3)->help('Optional.'),
            Field::otp('pairing', 'Pairing code')->help('The 6 digits shown on your device.'),
            Field::keyValue('labels')->value(['env' => 'prod'])->keySuggestions(['env', 'team']),
            Field::select('languages', ['en' => 'English', 'fr' => 'French', 'ln' => 'Lingala'])
                ->multiple()
                ->searchable()
                ->value(['fr']),
            Field::tags('skills', ['PHP', 'Laravel', 'Vue'])
                ->value(['PHP'])
                ->maxTags(4)
                ->help('Type a skill, then Enter or a comma.'),
            $reviewer,
            Section::make('Availability')->collapsible()->columns(2)->add(
                Field::time('call', 'Call time')->min('08:00')->max('18:00')->value('09:30'),
                Field::dateRange('holidays')
                    ->labels('First day', 'Last day')
                    ->value(['start' => '2026-07-01', 'end' => '2026-07-14'])
                    ->columnSpan('full'),
            ),
        )
        ->errors(['plan' => 'Choose a plan.'])
        ->submit('Save'),

    Form::make('article')
        ->errorSummary()
        ->action('/articles')
        ->csrf('test-token')
        ->add(
            Field::text('title')->required(),
            Field::editor('body', 'Article')->required()->value('<h2>Intro</h2><p>Hello <strong>world</strong></p>'),
            Field::file('cover', 'Cover image')->rules(UploadRules::images()->maxSize('2M'))->upload('/uploads'),
        )
        ->submit('Publish'),

    Scheduler::make('team-calendar')
        ->title('Team calendar')
        ->view('month')
        ->date('2026-11-05')
        ->timeZone('UTC')
        ->firstDay(1)
        // A fixed "today" on an empty day of the month shown: the tinted today cell is
        // always checked (contrast, forced colors), whatever the real date.
        ->options(['now' => '2026-11-11T10:00:00Z'])
        ->events([
            ['id' => 1, 'title' => 'Kick-off', 'start' => '2026-11-03T09:00:00Z', 'end' => '2026-11-03T10:00:00Z'],
            ['id' => 2, 'title' => 'Design review', 'start' => '2026-11-05T14:00:00Z', 'end' => '2026-11-05T15:30:00Z', 'color' => '#ffc107'],
            ['id' => 3, 'title' => 'Holiday', 'start' => '2026-11-06'],
        ])
        ->createUrl('/events/create')
        ->updateUrl('/events/update')
        ->deleteUrl('/events/delete')
        ->csrf('test-token'),

    Tabs::make('account')
        ->label('Account settings')
        ->tab('profile', 'Profile', 'Your name and e-mail.')
        ->tab('security', 'Security', Html::raw('<p>Change your password.</p>'))
        ->tab('billing', 'Billing', 'Invoices and payment methods.'),

    FileManager::make('files')
        ->title('Shared files')
        ->url('/files/connector')
        ->assetsUrl('/elfinder')
        ->height(380),

    Form::make('join')
        ->action('/join')
        ->csrf('test-token')
        ->wizard()
        ->add(
            Section::make('Your account')->add(Field::text('full_name')->required()),
            Section::make('Your team')->columns(2)->add(
                Field::text('team_name')->required(),
                Field::text('team_role'),
                Repeater::make('members')
                    ->schema(Field::email('email', 'Member e-mail')->required())
                    ->reorderable()
                    ->defaultItems(0)
                    ->max(3)
                    ->labels(add: 'Add a member', item: 'Member %d')
                    ->columnSpan('full'),
            ),
            Section::make('Confirm')->add(Field::checkbox('join_terms', 'I accept the terms')->required()),
        )
        ->submit('Join'),
];

$modal = Modal::make('contact-modal')
    ->title('Contact us')
    ->body(Form::make('modal-form')->add(Field::email('contact_email', 'E-mail')->required())->submit(null))
    ->cancelButton()
    ->button('Send', 'primary', attributes: ['type' => 'submit', 'form' => 'modal-form']);

$notification = Notification::make('flash')
    ->success('Profile saved.')
    ->error('The export failed.');
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Celerik browser tests (<?= $theme ?>, <?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <script src="/vendor/jquery/jquery.min.js" defer></script>
    <script src="/vendor/bootstrap/js/bootstrap.bundle.min.js" defer></script>
    <?= Assets::tags('/assets', customTheme: $customTheme) ?>
</head>
<body>
<main class="container py-4">
    <h1>Celerik components</h1>
    <?php foreach ($components as $component) : ?>
        <section class="my-4"><?= $component->render() ?></section>
    <?php endforeach ?>
    <section class="my-4">
        <?= $modal->trigger('Contact us') ?>
        <?= $modal->render() ?>
    </section>
    <?= $notification->render() ?>
</main>
</body>
</html>
