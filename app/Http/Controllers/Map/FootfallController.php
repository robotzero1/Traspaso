<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\FootfallPoint;
use Illuminate\Http\JsonResponse;

/**
 * The footfall surface for the map's footfall layer, loaded only when the
 * player switches the layer on. Rows are compact arrays to keep thousands
 * of points small: [lat, lng, overall, morning, lunch, afternoon, evening, night].
 */
class FootfallController extends Controller
{
    public const array COLUMNS = [
        'lat', 'lng', 'footfall', 'footfall_morning', 'footfall_lunch', 'footfall_afternoon', 'footfall_evening', 'footfall_night',
    ];

    public function __invoke(): JsonResponse
    {
        $points = FootfallPoint::query()->orderBy('id')->toBase()->get(self::COLUMNS)
            ->map(fn (object $p) => array_map('floatval', array_values((array) $p)))
            ->all();

        return response()->json(['columns' => self::COLUMNS, 'points' => $points])
            ->setPrivate()->setMaxAge(3600);
    }
}
