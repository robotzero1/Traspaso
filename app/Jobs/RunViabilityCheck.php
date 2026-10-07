<?php

namespace App\Jobs;

use App\Game\GameMapper;
use App\Game\MarketData;
use App\Generation\Geo\Geo;
use App\Generation\Location;
use App\Models\FootfallPoint;
use App\Models\ViabilityReport;
use App\Viability\ViabilityCheck;
use App\Viability\ViabilityInput;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Runs a viability check on the queue: finds the commercial street point
 * nearest the pin, plays the futures and stores the results.
 */
class RunViabilityCheck implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $reportId) {}

    public function handle(GameMapper $mapper, MarketData $data): void
    {
        $report = ViabilityReport::query()->findOrFail($this->reportId);
        $report->update(['status' => 'running']);
        $input = ViabilityInput::fromArray($report->inputs);
        $point = self::nearestPoint($input->lat, $input->lng) ?? throw new \RuntimeException('No commercial street point near the pin.');
        $parameters = config('market.'.config('viability.market'));
        $below = FootfallPoint::query()->where('footfall', '<', $point->footfall)->count();

        $results = (new ViabilityCheck($parameters, $data->rivalPlaces($parameters['competitors']['unlisted']['poi_types'], $mapper)))->run(
            $input,
            new Location($point->lat, $point->lng, $point->footfall, $point->footfallByDayPart(), $point->street_type, $point->id),
            $mapper->neighbourhood($point->neighbourhood),
            footfallPercentile: $below / max(1, FootfallPoint::query()->count()),
            runs: (int) config('viability.runs'),
            years: (int) config('viability.years'),
            // Trading starts next month.
            startMonth: now('Europe/Madrid')->addMonthNoOverflow()->month,
            seed: crc32($report->uuid),
        );

        $report->update(['status' => 'done', 'results' => $results]);
    }

    public function failed(?Throwable $e): void
    {
        ViabilityReport::query()->whereKey($this->reportId)->update(['status' => 'failed']);
    }

    /** The commercial street point nearest the pin, within viability.max_point_metres. */
    public static function nearestPoint(float $lat, float $lng): ?FootfallPoint
    {
        $max = (float) config('viability.max_point_metres');
        $dLat = $max / 111_195;
        $dLng = $dLat / cos(deg2rad($lat));

        return FootfallPoint::query()->with('neighbourhood')
            ->whereBetween('lat', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('lng', [$lng - $dLng, $lng + $dLng])
            ->get()
            ->map(fn (FootfallPoint $p) => [$p, Geo::distanceMetres($lat, $lng, $p->lat, $p->lng)])
            ->filter(fn (array $p) => $p[1] <= $max)
            ->sortBy(fn (array $p) => $p[1])
            ->first()[0] ?? null;
    }
}
