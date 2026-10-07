<?php

use App\Http\Controllers\Game\DecisionsController;
use App\Http\Controllers\Game\EndController;
use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Game\MonthController;
use App\Http\Controllers\Game\PurchaseController;
use App\Http\Controllers\Map\FootfallController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ViabilityController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// The viability check: standalone, no account needed (SPEC §11).
Route::get('viability', [ViabilityController::class, 'create'])->name('viability.create');
Route::post('viability', [ViabilityController::class, 'store'])->middleware('throttle:10,60')->name('viability.store');
Route::get('viability/{report}', [ViabilityController::class, 'show'])->name('viability.show');

// Payments (SPEC §11).
Route::post('viability/{report}/checkout', [PaymentController::class, 'viabilityReport'])->middleware('throttle:10,1')->name('payments.viability');
Route::get('payments/return', [PaymentController::class, 'returned'])->name('payments.return');
Route::post('stripe/webhook', [PaymentController::class, 'webhook'])->name('payments.webhook');

// The draft legal pages.
Route::inertia('terms', 'legal/terms')->name('legal.terms');
Route::inertia('privacy', 'legal/privacy')->name('legal.privacy');
Route::inertia('disclaimer', 'legal/disclaimer')->name('legal.disclaimer');

Route::middleware(['auth', 'verified'])->group(function () {
    // The starter kit's dashboard; the game list is the real home.
    Route::redirect('dashboard', 'games')->name('dashboard');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::post('capital/checkout', [PaymentController::class, 'capital'])->middleware('throttle:10,1')->name('payments.capital');
    Route::post('games', [GameController::class, 'store'])->name('games.store');
    Route::get('games/latest', [GameController::class, 'latest'])->name('games.latest');
    Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');
    Route::post('games/{game}/purchase', [PurchaseController::class, 'store'])->name('games.purchase');
    Route::put('games/{game}/decisions', [DecisionsController::class, 'update'])->name('games.decisions');
    Route::post('games/{game}/months', [MonthController::class, 'store'])->name('games.months.store');
    Route::post('games/{game}/end', [EndController::class, 'store'])->name('games.end');

    Route::get('map/footfall', FootfallController::class)->name('map.footfall');
});

require __DIR__.'/settings.php';
