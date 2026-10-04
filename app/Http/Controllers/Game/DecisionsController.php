<?php

namespace App\Http\Controllers\Game;

use App\Actions\Game\UpdateDecisions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\UpdateDecisionsRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DecisionsController extends Controller
{
    public function update(UpdateDecisionsRequest $request, Game $game, UpdateDecisions $update): RedirectResponse
    {
        Gate::authorize('update', $game);

        $update->handle($game, $request->validated());

        return to_route('games.show', $game);
    }
}
