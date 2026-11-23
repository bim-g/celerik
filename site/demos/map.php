<?php

use Caelrik\Ui\Components\Map;

return [
    Map::make('offices')->label('Our offices around Lake Kivu')
        ->marker(-1.6792, 29.2228, 'Goma', 'Head office, Avenue du Lac', url: '#goma')
        ->marker(-2.5083, 28.8608, 'Bukavu', 'Sales and support', url: '#bukavu', color: 'success')
        ->marker(-1.9441, 30.0619, 'Kigali', 'Partner office', color: 'warning')
        ->marker(-2.0700, 29.0400, 'Idjwi', 'Coffee cooperative', color: 'info'),
];
