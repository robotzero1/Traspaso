<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $capital = config('market.zaragoza_cafe.game.starting_capital_cents');

        return [
            'starting_capital_euros' => ['required', 'integer', 'min:'.intdiv($capital['min'], 100), 'max:'.intdiv($capital['max'], 100)],
        ];
    }

    public function startingCapitalCents(): int
    {
        return (int) $this->validated('starting_capital_euros') * 100;
    }
}
