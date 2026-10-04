<?php

namespace App\Http\Controllers\Game;

use App\Actions\Game\EndGame;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EndController extends Controller
{
    public function store(Request $request, Game $game, EndGame $end): RedirectResponse
    {
        Gate::authorize('update', $game);

        $choice = $request->validate(['outcome' => ['required', Rule::in(['sell', 'keep'])]])['outcome'];
        $choice === 'sell' ? $end->sell($game) : $end->keep($game);

        return to_route('games.show', $game);
    }
}
