<?php

// Add to routes/web.php:  require __DIR__ . '/caelrik.php';

declare(strict_types=1);

use App\Http\Controllers\CaelrikArticleController;
use App\Http\Controllers\CaelrikCalendarController;
use App\Http\Controllers\CaelrikDemoController;
use App\Http\Controllers\CaelrikFilesController;
use App\Http\Controllers\CaelrikThemeController;
use App\Http\Controllers\CaelrikUiController;
use Illuminate\Support\Facades\Route;

Route::prefix('caelrik')->name('caelrik.')->controller(CaelrikDemoController::class)->group(static function (): void {
    Route::get('/grid', 'grid')->name('grid');
    Route::get('/grid/data', 'gridData')->name('grid.data');
    Route::get('/chart', 'chart')->name('chart');
    Route::get('/form', 'form')->name('form');
    Route::post('/form', 'submit')->name('form.submit');
});

Route::prefix('caelrik/scheduler')
    ->name('caelrik.scheduler')
    ->controller(CaelrikCalendarController::class)
    ->group(static function (): void {
        Route::get('/', 'show');
        Route::get('/events', 'events')->name('.events');
        Route::post('/events/create', 'create')->name('.create');
        Route::post('/events/update', 'update')->name('.update');
        Route::post('/events/delete', 'delete')->name('.delete');
    });

Route::prefix('caelrik/article')
    ->name('caelrik.article')
    ->controller(CaelrikArticleController::class)
    ->group(static function (): void {
        Route::get('/', 'show');
        Route::post('/', 'submit')->name('.submit');
        Route::match(['POST', 'DELETE'], '/upload/{field}', 'upload')
            ->whereIn('field', ['cover', 'attachments'])
            ->name('.upload');
    });

Route::prefix('caelrik/ui')
    ->name('caelrik.ui')
    ->controller(CaelrikUiController::class)
    ->group(static function (): void {
        Route::get('/', 'show');
        Route::post('/subscribe', 'subscribe')->name('.subscribe');
    });

Route::prefix('caelrik/files')
    ->name('caelrik.files')
    ->controller(CaelrikFilesController::class)
    ->group(static function (): void {
        Route::get('/', 'show');
        Route::match(['GET', 'POST'], '/connector', 'connector')->name('.connector');
    });

// Development only: the controller answers 404 unless APP_ENV is "local".
Route::prefix('caelrik/theme')
    ->name('caelrik.theme')
    ->controller(CaelrikThemeController::class)
    ->group(static function (): void {
        Route::get('/', 'show');
        Route::post('/', 'save')->name('.save');
    });
