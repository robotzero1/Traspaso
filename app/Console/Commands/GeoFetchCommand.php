<?php

namespace App\Console\Commands;

use App\Geo\GeoFiles;
use App\Geo\OverpassQueries;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('geo:fetch
    {--skip-boundaries : Use sources/neighbourhoods.geojson instead of OSM boundaries}
    {--fresh : Download every tile again instead of resuming}')]
#[Description('Download the OpenStreetMap data for the city (run locally, with network access)')]
class GeoFetchCommand extends Command
{
    private const RETRY_STATUSES = [429, 502, 503, 504];

    public function handle(): int
    {
        ini_set('memory_limit', config('geo.build_memory_limit'));

        $files = GeoFiles::fromConfig();
        $queries = new OverpassQueries(config('geo'));
        $tiles = $queries->tiles((int) config('geo.fetch_tiles'));

        try {
            foreach (['streets' => fn ($bbox) => $queries->streets($bbox), 'pois' => fn ($bbox) => $queries->pointsOfInterest($bbox)] as $name => $query) {
                $parts = [];

                foreach ($tiles as $i => $bbox) {
                    $path = "{$files->rawPath}/tiles/{$name}-{$i}.json";
                    $parts[] = $path;
                    $label = sprintf('Downloading %s (%d/%d)', $name, $i + 1, count($tiles));

                    if (! $this->option('fresh') && $files->readJson($path) !== null) {
                        $this->components->twoColumnDetail($label, 'already done');

                        continue;
                    }

                    $this->components->task($label, fn () => $this->download($files, $path, $query($bbox)));
                }

                $this->components->task("Merging {$name}", fn () => $this->merge($files, $parts, "{$files->rawPath}/{$name}.json"));
            }

            if (! $this->option('skip-boundaries')) {
                $this->components->task('Downloading boundaries', fn () => $this->download($files, "{$files->rawPath}/boundaries.json", $queries->boundaries()));
            }
        } catch (Throwable $e) {
            return $this->failed($e);
        }

        $boundaries = $files->readJson("{$files->rawPath}/boundaries.json");

        if (! $this->option('skip-boundaries') && empty($boundaries['elements'])) {
            $this->components->warn('OSM returned no district boundaries at that admin_level. Put a GeoJSON of the districts at '
                .config('geo.sources_path').'/neighbourhoods.geojson (or change geo.boundaries.district_admin_level).');
        }

        $this->components->info('Saved to '.config('geo.raw_path').'. Next: php artisan geo:build');

        return self::SUCCESS;
    }

    /** One Overpass request, retried while the server is busy or timing out. */
    private function download(GeoFiles $files, string $path, string $query): bool
    {
        $delay = (int) config('geo.retry_delay_ms');

        $response = Http::timeout(config('geo.overpass_timeout_seconds') + 30)
            ->withUserAgent(config('geo.user_agent'))
            ->accept('application/json')
            ->asForm()
            ->retry(
                (int) config('geo.retries') + 1,
                fn (int $attempt) => $delay * $attempt,
                fn (Throwable $e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), self::RETRY_STATUSES, true)),
            )
            ->post(config('geo.overpass_url'), ['data' => $query]);

        // Saved as received: whole-city files run to tens of megabytes.
        $files->writeRaw($path, $response->body());

        return true;
    }

    /**
     * Joins the tiles into one file, dropping elements that appear in more
     * than one tile (streets crossing a tile edge, say).
     *
     * @param  list<string>  $parts
     */
    private function merge(GeoFiles $files, array $parts, string $path): bool
    {
        $elements = [];
        $meta = null;

        foreach ($parts as $part) {
            $data = $files->readJson($part) ?? [];
            $meta ??= $data['osm3s'] ?? null;

            foreach ($data['elements'] ?? [] as $element) {
                $elements["{$element['type']}/{$element['id']}"] = $element;
            }
        }

        $files->writeJson($path, array_filter(['osm3s' => $meta, 'elements' => array_values($elements)]));

        return true;
    }

    private function failed(Throwable $e): int
    {
        $message = $e instanceof RequestException ? "HTTP {$e->response->status()}" : $e->getMessage();
        $this->components->error("Download failed: {$message}");

        if (str_contains($e->getMessage(), 'cURL error 60')) {
            $this->line('  PHP has no trusted certificate authorities configured (common on Windows).');
            $this->line('  Download https://curl.se/ca/cacert.pem, then in php.ini set curl.cainfo and');
            $this->line('  openssl.cafile to its full path. Don\'t turn certificate checks off.');
        }

        if ($e instanceof RequestException && in_array($e->response->status(), self::RETRY_STATUSES, true)) {
            $this->line('  The Overpass server is busy or timing out. Run the command again later: finished');
            $this->line('  tiles are kept. Or raise geo.fetch_tiles, or set OVERPASS_URL to another server,');
            $this->line('  e.g. https://overpass.kumi.systems/api/interpreter');
        }

        return self::FAILURE;
    }
}
