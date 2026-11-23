<?php

use Caelrik\Ui\Components\Carousel;
use Caelrik\Ui\Support\Html;

return [
    Carousel::make('news')->label('News')
        ->image(
            '../images/carousel/lake.svg',
            'Lake Kivu at dawn, hills behind the water',
            caption: 'Our new office looks over Lake Kivu.',
        )
        ->image(
            '../images/carousel/hills.svg',
            'Green hills under a blue sky',
            caption: 'The team retreat.',
            label: 'Retreat',
        )
        ->image('../images/carousel/city.svg', 'A city at night, with lit windows', caption: 'Late releases.'),
    Html::raw('<h2 class="h5 mt-4">Tips, every 6 seconds</h2>'),
    Carousel::make('tips')->label('Tips')->autoplay(6)
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">Keyboard</h3>'
            . '<p class="mb-0">Every component works from the keyboard.</p></div>'), label: 'Keyboard')
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">Dark mode</h3>'
            . '<p class="mb-0">Every component has a dark mode.</p></div>'), label: 'Dark mode')
        ->slide(Html::raw('<div class="card card-body"><h3 class="h6">No inline styles</h3>'
            . '<p class="mb-0">A strict Content Security Policy works.</p></div>'), label: 'Strict CSP'),
];
