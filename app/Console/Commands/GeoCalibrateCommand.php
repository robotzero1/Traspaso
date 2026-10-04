<?php

namespace App\Console\Commands;

use App\Generation\Geo\Calibration;
use App\Geo\GeoFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('geo:calibrate {--fit : Search for the component weights that best match the counts}')]
#[Description('Compare the footfall surface with manual pedestrian counts')]
class GeoCalibrateCommand extends Command
{
    public function handle(): int
    {
        $files = GeoFiles::fromConfig();
        $points = array_map(fn (array $row) => array_map(fn ($v) => is_numeric($v) ? (float) $v : $v, $row), $files->readCsv("{$files->outputPath}/footfall_points.csv"));
        $counts = array_map(fn (array $row) => [
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'day_part' => $row['day_part'],
            // Per minute, so counts of different lengths compare.
            'count' => (float) $row['count'] / max(1.0, (float) ($row['minutes'] ?: 10)),
        ], $files->readCsv("{$files->sourcesPath}/pedestrian_counts.csv"));

        if ($points === [] || $counts === []) {
            $this->components->error('Needs footfall_points.csv (geo:build) and rows in '.config('geo.sources_path').'/pedestrian_counts.csv.');

            return self::FAILURE;
        }

        $matched = Calibration::match($counts, $points);
        $rho = Calibration::spearman($matched);

        $this->components->twoColumnDetail('Counts', (string) count($counts));
        $this->components->twoColumnDetail('Matched to a surface point (≤ 50 m)', (string) count($matched));

        if (count($matched) < 3) {
            $this->components->warn('Fewer than three counts matched a commercial point; count on shopping streets.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Rank correlation with the model', sprintf('%.2f (p ≈ %.3f)', $rho, Calibration::pValue($rho, count($matched))));

        if ($this->option('fit')) {
            $fit = Calibration::fit($matched);
            $this->newLine();
            $this->components->info(sprintf('Best weights (rank correlation %.2f):', $fit['spearman']));

            foreach ($fit['weights'] as $component => $weight) {
                $this->components->twoColumnDetail($component, number_format($weight, 1));
            }

            $this->line('  Put these in config/geo.php footfall.component_weights, mark the source as calibrated, and run geo:build again.');
        }

        return self::SUCCESS;
    }
}
