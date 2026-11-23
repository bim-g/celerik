<?php

/**
 * Gantt charts, included by server.php (GET /gantt?mode=…). "Today" is fixed so the page
 * does not change with the day the tests run.
 */

declare(strict_types=1);

use Caelrik\Ui\Assets;
use Caelrik\Ui\Components\Gantt;

$mode = ($_GET['mode'] ?? '') === 'dark' ? 'dark' : 'light';
?>
<!doctype html>
<html lang="en" data-bs-theme="<?= $mode ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <title>Gantt (<?= $mode ?>)</title>
    <link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css">
    <?= Assets::tags('/assets') ?>
</head>
<body>
<main class="container py-3">
    <h1>Gantt</h1>
    <?= Gantt::make('launch')->label('Product launch')->today('2026-11-20')
        ->task('research', 'User research', '2026-10-26', '2026-11-06', progress: 100, color: 'info')
        ->task('design', 'Design', '2026-11-02', '2026-11-13', progress: 100, depends: ['research'])
        ->task('build', 'Build <api>', '2026-11-16', '2026-12-11', progress: 40, depends: ['design'], color: 'success')
        ->task('docs', 'Documentation', '2026-11-30', '2026-12-18', progress: 0, depends: ['design'], color: 'secondary')
        ->task('qa', 'Testing', '2026-12-07', '2026-12-18', depends: ['build'], color: 'warning')
        ->milestone('release', 'Release', '2026-12-21', depends: ['qa', 'docs'], color: 'danger') ?>

    <?= Gantt::make('sprint')->label('Sprint 12')->scale('day')->today('2026-11-20')->dateFormat('D j')
        ->task('s1', 'Login page', '2026-11-16', '2026-11-18', progress: 100)
        ->task('s2', 'Password reset', '2026-11-18', '2026-11-24', progress: 50, depends: ['s1'])
        ->milestone('demo', 'Demo', '2026-11-25', depends: ['s2']) ?>

    <?= Gantt::make('roadmap')->label('Roadmap 2027')->scale('month')->today('2026-11-20')
        ->task('r1', 'Phase 8', '2026-11-16', '2027-02-26', progress: 10)
        ->task('r2', 'Phase 9', '2027-03-01', '2027-06-30', depends: ['r1'], color: 'info')
        ->milestone('v1', 'Version 1.0', '2027-07-01', depends: ['r2']) ?>
</main>
</body>
</html>
