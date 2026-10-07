<?php

namespace App\Http\Requests\Game;

use App\Payments\Payments;
use Illuminate\Foundation\Http\FormRequest;

class StoreGameRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $capital = config('market.zaragoza_cafe.game.starting_capital_cents');

        return [
            // Up to the free amount, or the savings tier this account bought.
            'starting_capital_euros' => ['required', 'integer', 'min:'.intdiv($capital['min'], 100), 'max:'.intdiv(min($capital['max'], Payments::maxCapitalCents($this->user())), 100)],
        ];
    }

    public function startingCapitalCents(): int
    {
        return (int) $this->validated('starting_capital_euros') * 100;
    }
}
