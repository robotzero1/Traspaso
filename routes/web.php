<?php

use App\Http\Controllers\Game\DecisionsController;
use App\Http\Controllers\Game\EndController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Game\MonthController;
use App\Http\Controllers\Game\PurchaseController;
use App\Http\Controllers\Map\FootfallController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The starter kit's dashboard; the game list is the real home.
    Route::redirect('dashboard', 'games')->name('dashboard');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('games/{game}/purchase', [PurchaseController::class, 'store'])->name('games.purchase');
    Route::put('games/{game}/decisions', [DecisionsController::class, 'update'])->name('games.decisions');
    Route::post('games/{game}/months', [MonthController::class, 'store'])->name('games.months.store');
    Route::post('games/{game}/end', [EndController::class, 'store'])->name('games.end');

    Route::get('map/footfall', FootfallController::class)->name('map.footfall');
});

require __DIR__.'/settings.php';
