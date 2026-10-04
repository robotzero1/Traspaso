<?php

namespace App\Console\Commands;

use App\Geo\GeoBuild;
use App\Geo\GeoFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('geo:build')]
#[Description('Derive neighbourhoods, points of interest and the footfall surface from the downloaded data')]
class GeoBuildCommand extends Command
{
    public function handle(): int
    {
        // Decoding a whole city's street network needs more than PHP's default.
        ini_set('memory_limit', config('geo.build_memory_limit'));

        $build = new GeoBuild(GeoFiles::fromConfig(), config('geo'));

        try {
            $counts = $build->run(function (string $step, int $done, int $total) {
                $this->output->write(sprintf("\r  %s: %d / %d", $step, $done, $total));
            });
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();

        foreach ($build->warnings as $warning) {
            $this->components->warn($warning);
        }

        $this->components->twoColumnDetail('Neighbourhoods', (string) $counts['neighbourhoods']);
        $this->components->twoColumnDetail('Streets', (string) $counts['streets']);
        $this->components->twoColumnDetail('Points of interest', (string) $counts['points_of_interest']);
        $this->components->twoColumnDetail('Footfall points', (string) $counts['footfall_points']);
        $this->components->info('Written to '.config('geo.output_path').'. Commit those files, then: php artisan db:seed');

        return self::SUCCESS;
    }
}
