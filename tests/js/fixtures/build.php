<?php

/**
 * Renders the PHP components to HTML fixtures used by the Jest and axe-core tests,
 * so the JS wrappers are always tested against the markup PHP actually produces.
 */

declare(strict_types=1);

use Celerik\Ui\Components\Chart;
use Celerik\Ui\Components\FileManager;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Components\Form\Repeater;
use Celerik\Ui\Components\Form\Section;
use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Grid;
use Celerik\Ui\Components\Grid\Column;
use Celerik\Ui\Components\Grid\Filter;
use Celerik\Ui\Components\Modal;
use Celerik\Ui\Components\Notification;
use Celerik\Ui\Components\Scheduler;
use Celerik\Ui\Components\Tabs;
use Celerik\Ui\Components\ThemeBuilder;
use Celerik\Ui\Support\Html;
use Celerik\Ui\Theme\Theme;
use Celerik\Ui\Upload\UploadRules;

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

// The stylesheet of the bootstrap preset (presets.test.js), and the teal browser-test theme
// with a font and a whole-number radius: resources/js/theme-css.js must write the same text
// (theme-css.test.js).
file_put_contents(__DIR__ . '/theme-bootstrap.css', Theme::preset('bootstrap')->toCss());
$custom = Theme::fromJson((string) file_get_contents(dirname(__DIR__, 2) . '/browser/custom-theme.json'))
    ->fontFamily('"Inter", system-ui, sans-serif')
    ->radius(1.0);
file_put_contents(__DIR__ . '/theme-custom.css', $custom->toCss());

echo 'Wrote ' . count($fixtures) . " fixtures and 2 theme stylesheets to tests/js/fixtures\n";
