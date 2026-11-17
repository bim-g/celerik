<?php

use Celerik\Ui\Components\Action;
use Celerik\Ui\Support\Html;

// Actions on their own, e.g. in a page's toolbar. The demo's requests are answered in the
// browser: nothing is saved.
return Html::raw(
    '<div class="d-flex flex-wrap gap-2">'
    . Action::link('new', 'New member', '?new=1')->variant('primary')->solid()
    . Action::request('export', 'Send me a report', '/demo-api/reports')
    . Action::request('archive', 'Archive last year', '/demo-api/archive')
        ->variant('danger')
        ->confirm('Archive last year\'s members?', 'They leave the list; you can restore them later.')
    . '</div>',
);
