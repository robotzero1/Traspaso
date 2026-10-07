<?php

namespace App\Console\Commands;

use App\Ops\HealthCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:health')]
#[Description('Check the database, queue worker and nightly run')]
class HealthCommand extends Command
{
    public function handle(HealthCheck $check): int
    {
        $problems = $check->problems();

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        if ($problems === []) {
            $this->components->info('All good.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
