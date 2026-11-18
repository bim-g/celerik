<?php

use Celerik\Ui\Components\Action;
use Celerik\Ui\Components\Form;
use Celerik\Ui\Components\Form\Field;
use Celerik\Ui\Support\Html;

// Actions on their own, e.g. in a page's toolbar. The demo's requests are answered in the
// browser: nothing is saved.
return Html::raw(
    '<div class="d-flex flex-wrap gap-2">'
    . Action::link('new', 'New member', '?new=1')->variant('primary')->solid()
    . Action::request('invite', 'Invite someone', '/demo-api/invitations')
        ->form(Form::make('invite-form')->add(
            Field::email('email', 'E-mail')->required(),
            Field::select('role', ['member' => 'Member', 'admin' => 'Administrator'], 'Role')->value('member'),
            Field::textarea('note', 'A note for them')->help('Optional.'),
        ), 'Invite someone to the team', 'Send the invitation')
    . Action::request('export', 'Send me a report', '/demo-api/reports')
    . Action::request('archive', 'Archive last year', '/demo-api/archive')
        ->variant('danger')
        ->confirm('Archive last year\'s members?', 'They leave the list; you can restore them later.')
    . '</div>',
);
