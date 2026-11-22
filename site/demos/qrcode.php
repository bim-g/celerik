<?php

use Celerik\Ui\Components\Barcode;
use Celerik\Ui\Components\QrCode;
use Celerik\Ui\Support\Html;

return [
    Html::raw('<div class="d-flex flex-wrap gap-4 align-items-start">'),
    QrCode::make('site')->value('https://kivudesign.github.io/celerik/')->label('Celerik UI'),
    QrCode::make('wifi')->value('WIFI:T:WPA;S:Office;P:correct horse battery staple;;')->label('Office Wi-Fi')
        ->hideValue()->level('Q'),
    Html::raw('</div><div class="d-flex flex-wrap gap-4 align-items-start">'),
    Barcode::make('isbn')->type('ean13')->value('978020137962')->label('ISBN'),
    Barcode::make('order')->value('ORDER-2026-000042')->label('Order number')->height(60),
    Html::raw('</div>'),
];
