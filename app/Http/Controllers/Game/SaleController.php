<?php

namespace App\Http\Controllers\Game;

use App\Game\Sales;
use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\SaleOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Listing the café, withdrawing it, and answering buyers (SPEC §12). Amounts come in whole euros. */
class SaleController extends Controller
{
    public function store(Request $request, Game $game, Sales $sales): RedirectResponse
    {
        Gate::authorize('update', $game);

        $data = $request->validate([
            'asking' => ['required', 'integer', 'min:1000', 'max:2000000'],
            'agency' => ['boolean'],
        ]);
        $sales->list($game, $data['asking'] * 100, $request->boolean('agency'));

        return to_route('games.show', $game);
    }

    public function destroy(Game $game, Sales $sales): RedirectResponse
    {
        Gate::authorize('update', $game);

        $sales->withdraw($game);

        return to_route('games.show', $game);
    }

    public function answer(Request $request, Game $game, SaleOffer $offer, Sales $sales): RedirectResponse
    {
        Gate::authorize('update', $game);
        abort_unless($offer->listing->game_id === $game->id, 404);

        $data = $request->validate([
            'answer' => ['required', Rule::in(['accept', 'reject', 'counter'])],
            'counter' => ['required_if:answer,counter', 'nullable', 'integer', 'min:1'],
        ]);
        $sales->answer($offer, $data['answer'], isset($data['counter']) ? $data['counter'] * 100 : null);

        return to_route('games.show', $game);
    }
}
