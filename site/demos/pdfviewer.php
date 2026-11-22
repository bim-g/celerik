<?php

use Celerik\Ui\Components\PdfViewer;

return PdfViewer::make('report')
    ->url('../files/annual-report.pdf')
    ->label('Annual report 2026')
    ->size('sm');
