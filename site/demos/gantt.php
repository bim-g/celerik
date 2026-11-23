<?php

use Caelrik\Ui\Components\Gantt;

return [
    Gantt::make('launch')->label('Product launch')->today('2026-11-20')
        ->task('research', 'User research', '2026-10-26', '2026-11-06', progress: 100, color: 'info')
        ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100, depends: ['research'])
        ->task('build', 'Build the API', '2026-11-16', '2026-12-11', 40, ['design'], 'success')
        ->task('docs', 'Documentation', '2026-11-30', '2026-12-18', 0, ['design'], 'secondary')
        ->task('qa', 'Testing', '2026-12-07', '2026-12-18', depends: ['build'], color: 'warning')
        ->milestone('release', 'Release', '2026-12-21', depends: ['qa', 'docs'], color: 'danger'),
    Gantt::make('sprint')->label('Sprint 12, by day')->scale('day')->today('2026-11-20')->dateFormat('D j')
        ->task('login', 'Login page', '2026-11-16', '2026-11-18', progress: 100)
        ->task('reset', 'Password reset', '2026-11-18', '2026-11-24', progress: 50, depends: ['login'])
        ->milestone('demo', 'Demo', '2026-11-25', depends: ['reset']),
];
