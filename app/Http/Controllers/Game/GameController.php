<?php

namespace App\Http\Controllers\Game;

use App\Actions\Game\StartGame;
use App\Enums\GameStatus;
use App\Game\GamePresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Game\StoreGameRequest;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /** Where the installed app opens: the player's latest active café, else the game list. */
    public function latest(Request $request): RedirectResponse
    {
        $game = $request->user()->games()->where('status', GameStatus::Active)->latest('updated_at')->latest('id')->first();

        return $game ? to_route('games.show', $game) : to_route('games.index');
    }

    public function index(Request $request, GamePresenter $presenter): Response
    {
        $capital = config('market.zaragoza_cafe.game.starting_capital_cents');

        return Inertia::render('games/index', [
            'games' => $request->user()->games()->with('business')->latest()->get()
                ->map(fn (Game $game) => $presenter->summary($game))->all(),
            'starting_capital' => [
                'min_euros' => intdiv($capital['min'], 100),
                'max_euros' => intdiv($capital['max'], 100),
            ],
        ]);
    }

    public function store(StoreGameRequest $request, StartGame $startGame): RedirectResponse
    {
        $game = $startGame->handle($request->user(), $request->startingCapitalCents());

        return to_route('games.show', $game);
    }

    public function show(Game $game, GamePresenter $presenter): Response
    {
        Gate::authorize('view', $game);

        return Inertia::render('games/show', $presenter->show($game));
    }
}
