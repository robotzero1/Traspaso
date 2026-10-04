<?php

namespace App\Http\Controllers\Game;

use App\Actions\Game\AdvanceMonth;
use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class MonthController extends Controller
{
    /** Play the current month. */
    public function store(Game $game, AdvanceMonth $advance): RedirectResponse
    {
        Gate::authorize('update', $game);

        $advance->handle($game);

        return to_route('games.show', $game);
    }
}
