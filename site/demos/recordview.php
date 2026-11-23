<?php

use Caelrik\Ui\Components\RecordView;
use Caelrik\Ui\Components\RecordView\Entry;

$member = [
    'name' => 'Ada Lovelace',
    'email' => 'ada@example.com',
    'phone' => '+44 20 7946 0000',
    'website' => 'https://example.com/ada',
    'company' => ['name' => 'Analytical Engines Ltd'],
    'bio' => null,
    'joined_at' => '2024-03-01',
    'balance' => 1234.5,
    'admin' => true,
    'status' => 'active',
    'teams' => ['Core', 'Docs', 'Design'],
];

return RecordView::make('member')
    ->record($member)
    ->section('Profile', [
        Entry::text('name', 'Name'),
        Entry::text('company.name', 'Company'),
        Entry::email('email', 'E-mail'),
        Entry::phone('phone', 'Phone'),
        Entry::url('website', 'Website', 'Personal page'),
        Entry::text('bio', 'Biography')->placeholder('No biography yet'),
    ], description: 'How to reach the member.', columns: 2)
    ->section('Account', [
        Entry::date('joined_at', 'Member since', 'j F Y'),
        Entry::number('balance', 'Balance', 2, prefix: '$'),
        Entry::boolean('admin', 'Administrator'),
        Entry::badge('status', 'Status', ['active' => 'success', 'suspended' => 'danger']),
        Entry::list('teams', 'Teams', badges: true)->span(2),
    ], columns: 3);
