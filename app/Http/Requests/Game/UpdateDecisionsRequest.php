<?php

namespace App\Http\Requests\Game;

use App\Models\Game;
use App\Simulation\Data\DayPart;
use App\Simulation\Data\QualityTier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDecisionsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Game $game */
        $game = $this->route('game');
        $limits = config("market.{$game->market}.decision_limits");

        return [
            'price_level' => ['required', 'numeric', "min:{$limits['price_level']['min']}", "max:{$limits['price_level']['max']}"],
            'open_day_parts' => ['required', 'array', 'min:1'],
            'open_day_parts.*' => ['distinct', Rule::enum(DayPart::class)],
            'open_days_per_week' => ['required', 'integer', 'min:1', 'max:7'],
            'staff_count' => ['required', 'integer', "min:{$limits['staff_count']['min']}", "max:{$limits['staff_count']['max']}"],
            'marketing_spend_cents' => [
                'required', 'integer',
                "min:{$limits['marketing_spend_cents']['min']}", "max:{$limits['marketing_spend_cents']['max']}",
            ],
            'quality_tier' => ['required', Rule::enum(QualityTier::class)],
            'event_choices' => ['sometimes', 'array'],
            'event_choices.*' => ['string'],
            // Save and play the month in one go.
            'and_play' => ['sometimes', 'boolean'],
        ];
    }
}
