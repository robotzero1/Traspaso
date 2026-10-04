<?php

namespace App\Console\Commands;

use App\Geo\GeoFiles;
use App\Geo\OverpassQueries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('geo:fetch {--skip-boundaries : Use sources/neighbourhoods.geojson instead of OSM boundaries}')]
#[Description('Download the OpenStreetMap data for the city (run locally, with network access)')]
class GeoFetchCommand extends Command
{
    public function handle(): int
    {
        $files = GeoFiles::fromConfig();
        $queries = new OverpassQueries(config('geo'));
        $downloads = ['streets' => $queries->streets(), 'pois' => $queries->pointsOfInterest()];

        if (! $this->option('skip-boundaries')) {
            $downloads['boundaries'] = $queries->boundaries();
        }

        foreach ($downloads as $name => $query) {
            try {
                $this->components->task("Downloading {$name}", function () use ($files, $name, $query) {
                    $response = Http::timeout(config('geo.overpass_timeout_seconds') + 30)
                        ->asForm()
                        ->post(config('geo.overpass_url'), ['data' => $query])
                        ->throw();

                    // Saved as received: the city's files run to tens of megabytes.
                    $files->writeRaw("{$files->rawPath}/{$name}.json", $response->body());

                    return true;
                });
            } catch (Throwable $e) {
                $this->components->error("Downloading {$name} failed: {$e->getMessage()}");

                if (str_contains($e->getMessage(), 'cURL error 60')) {
                    $this->line('  PHP has no trusted certificate authorities configured (common on Windows).');
                    $this->line('  Download https://curl.se/ca/cacert.pem, then in php.ini set curl.cainfo and');
                    $this->line('  openssl.cafile to its full path. Don\'t turn certificate checks off.');
                }

                return self::FAILURE;
            }
        }

        $boundaries = $files->readJson("{$files->rawPath}/boundaries.json");

        if (! $this->option('skip-boundaries') && empty($boundaries['elements'])) {
            $this->components->warn('OSM returned no district boundaries at that admin_level. Put a GeoJSON of the districts at '
                .config('geo.sources_path').'/neighbourhoods.geojson (or change geo.boundaries.district_admin_level).');
        }

        $this->components->info('Saved to '.config('geo.raw_path').'. Next: php artisan geo:build');

        return self::SUCCESS;
    }
}
