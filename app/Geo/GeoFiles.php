<?php

namespace App\Geo;

use RuntimeException;

/**
 * Reading and writing the pipeline's files: raw downloads, hand-collected
 * sources and the derived files the seeders import.
 */
final class GeoFiles
{
    public function __construct(
        public readonly string $rawPath,
        public readonly string $sourcesPath,
        public readonly string $outputPath,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            base_path(config('geo.raw_path')),
            base_path(config('geo.sources_path')),
            base_path(config('geo.output_path')),
        );
    }

    /** @return array<string, mixed>|null */
    public function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("{$path} is not valid JSON.");
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function writeJson(string $path, array $data, bool $pretty = false): void
    {
        $this->ensureDirectory(dirname($path));
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | ($pretty ? JSON_PRETTY_PRINT : 0);
        file_put_contents($path, json_encode($data, $flags | JSON_THROW_ON_ERROR)."\n");
    }

    public function writeRaw(string $path, string $contents): void
    {
        $this->ensureDirectory(dirname($path));
        file_put_contents($path, $contents);
    }

    /**
     * Reads a CSV with a header row; skips blank lines and lines starting with #.
     *
     * @return list<array<string, string>>
     */
    public function readCsv(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');
        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if ($line === [null] || str_starts_with(trim((string) $line[0]), '#')) {
                continue;
            }

            if ($header === null) {
                $header = array_map('trim', $line);

                continue;
            }

            $rows[] = array_combine($header, array_pad(array_map('trim', $line), count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    /** @param list<array<string, mixed>> $rows */
    public function writeCsv(string $path, array $rows): void
    {
        $this->ensureDirectory(dirname($path));
        $handle = fopen($path, 'w');

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]), escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($v) => is_bool($v) ? (int) $v : $v, $row), escape: '');
            }
        }

        fclose($handle);
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }
}
