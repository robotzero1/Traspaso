<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * A consistent, compressed copy of the SQLite database (VACUUM INTO works
 * while the app runs), keeping ops.backup.keep_days of them. Copy the
 * folder off the server too: a backup on the same disk isn't one.
 */
#[Signature('app:backup')]
#[Description('Back up the database and delete old backups')]
class BackupCommand extends Command
{
    public function handle(): int
    {
        $db = DB::connection(config('ops.backup.connection'));

        if ($db->getDriverName() !== 'sqlite') {
            $this->components->error('app:backup handles SQLite only; back up other databases with their own tools (e.g. mysqldump).');

            return self::FAILURE;
        }

        $dir = config('ops.backup.path');
        File::ensureDirectoryExists($dir);

        $name = 'traspaso-'.now()->format('Y-m-d-His');
        $raw = "{$dir}/{$name}.sqlite";
        $db->statement('VACUUM INTO ?', [$raw]);

        $in = fopen($raw, 'rb');
        $out = gzopen("{$raw}.gz", 'wb6');

        while (! feof($in)) {
            gzwrite($out, fread($in, 1 << 20));
        }

        fclose($in);
        gzclose($out);
        unlink($raw);

        $cutoff = now()->subDays(config('ops.backup.keep_days'))->getTimestamp();
        $deleted = 0;

        foreach (File::glob("{$dir}/traspaso-*.sqlite.gz") as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $deleted++;
            }
        }

        $this->components->info("Backed up to {$raw}.gz; removed {$deleted} old backup(s).");

        return self::SUCCESS;
    }
}
