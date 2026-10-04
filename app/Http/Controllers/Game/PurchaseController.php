<?php

namespace App\Http\Controllers\Game;

use App\Actions\Game\PurchaseBusiness;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\PurchaseBusinessRequest;
use App\Models\Business;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function store(PurchaseBusinessRequest $request, Game $game, PurchaseBusiness $purchase): RedirectResponse
    {
        Gate::authorize('update', $game);

        $business = Business::query()->where('game_id', $game->id)->find($request->integer('business_id'))
            ?? throw ValidationException::withMessages(['business_id' => 'That business is not in this game.']);

        $purchase->handle($game, $business);

        return to_route('games.show', $game);
    }
}
