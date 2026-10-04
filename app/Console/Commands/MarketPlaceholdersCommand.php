<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('market:placeholders {market=zaragoza_cafe : The parameter sheet in config/market}')]
#[Description('List the sections of a market parameter sheet that still hold placeholder values')]
class MarketPlaceholdersCommand extends Command
{
    public function handle(): int
    {
        $market = $this->argument('market');
        $sheet = config("market.{$market}");

        if (! is_array($sheet)) {
            $this->error("There is no parameter sheet at config/market/{$market}.php.");

            return self::FAILURE;
        }

        $rows = [];

        foreach ($sheet as $section => $values) {
            $source = is_array($values) ? ($values['source'] ?? null) : null;

            if (is_string($source) && str_starts_with($source, 'PLACEHOLDER')) {
                $rows[] = [$section, trim(substr($source, strlen('PLACEHOLDER')), ' :')];
            }
        }

        if ($rows === []) {
            $this->info("Every section of [{$market}] has a verified source.");

            return self::SUCCESS;
        }

        $this->warn(count($rows)." section(s) of [{$market}] still hold placeholder values:");
        $this->table(['Section', 'Verify against'], $rows);

        return self::SUCCESS;
    }
}
