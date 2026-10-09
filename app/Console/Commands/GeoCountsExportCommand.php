<?php

namespace App\Console\Commands;

use App\Geo\GeoFiles;
use App\Models\PedestrianCount;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Writes the counts made on the counting page into the committed
 * pedestrian_counts.csv that geo:calibrate reads (SPEC §8), keeping the
 * file's header comments and any rows already in it. Only the spot, day
 * part, date, minutes, count and note go out: not who counted.
 */
#[Signature('geo:counts-export')]
#[Description('Add the counts from the counting page to pedestrian_counts.csv')]
class GeoCountsExportCommand extends Command
{
    private const COLUMNS = ['lat', 'lng', 'day_part', 'date', 'minutes', 'count', 'notes'];

    public function handle(): int
    {
        $files = GeoFiles::fromConfig();
        $path = "{$files->sourcesPath}/pedestrian_counts.csv";
        $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
        $comments = array_values(array_filter($lines, fn (string $l) => str_starts_with($l, '#')));
        $rows = array_map(fn (array $r) => array_map(fn (string $c) => $r[$c] ?? '', self::COLUMNS), $files->readCsv($path));

        $key = fn (array $r) => implode('|', array_slice($r, 0, 6));
        $seen = array_flip(array_map($key, $rows));
        $added = 0;

        foreach (PedestrianCount::query()->orderBy('counted_on')->orderBy('id')->get() as $c) {
            $row = [number_format($c->lat, 6, '.', ''), number_format($c->lng, 6, '.', ''), $c->day_part, $c->counted_on->toDateString(), (string) $c->minutes, (string) $c->count, (string) $c->note];

            if (! isset($seen[$key($row)])) {
                $rows[] = $row;
                $seen[$key($row)] = true;
                $added++;
            }
        }

        $out = fopen($path, 'w');
        fwrite($out, implode("\n", $comments).($comments === [] ? '' : "\n"));
        fputcsv($out, self::COLUMNS, escape: '');

        foreach ($rows as $row) {
            fputcsv($out, $row, escape: '');
        }

        fclose($out);
        $this->components->info("{$added} new count(s) added; ".count($rows)." in {$path}. Commit it, then run geo:calibrate.");

        return self::SUCCESS;
    }
}
