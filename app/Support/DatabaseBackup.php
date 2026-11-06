<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Backs up this site's database to a zip file, with no external tools needed
 * (mysqldump is not required). Each table is saved as one JSON file of rows.
 *
 * Only this site's own database is included, never other databases that
 * share the same MySQL server. Throwaway tables (cache, sessions, jobs) are
 * skipped. Backups go to the disk named by BACKUP_DISK (the private local
 * disk by default; point it at an S3 bucket to keep copies off the server).
 */
class DatabaseBackup
{
    public const string FOLDER = 'backups';

    /**
     * Tables that only hold temporary data and are rebuilt by the app.
     */
    private const array SKIPPED_TABLES = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs'];

    /**
     * Make a new backup and return its path on the backup disk.
     */
    public static function create(): string
    {
        $tables = self::tables();
        $workFile = tempnam(sys_get_temp_dir(), 'backup');
        $zip = new ZipArchive;

        if ($workFile === false || $zip->open($workFile, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the backup file.');
        }

        $counts = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
            $counts[$table] = count($rows);
            $zip->addFromString($table.'.json', (string) json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $zip->addFromString('manifest.json', (string) json_encode([
            'app' => config('app.name'),
            'database' => DB::connection()->getDatabaseName(),
            'created_at' => now()->toIso8601String(),
            'tables' => $counts,
        ], JSON_PRETTY_PRINT));
        $zip->close();

        $path = self::FOLDER.'/backup-'.now()->format('Y-m-d-His').'.zip';
        $stream = fopen($workFile, 'r');

        if ($stream === false) {
            throw new RuntimeException('Could not read the backup file back to save it.');
        }

        self::disk()->put($path, $stream);
        fclose($stream);
        @unlink($workFile);

        return $path;
    }

    /**
     * Existing backups, newest first.
     *
     * @return list<array{path: string, name: string, size: int, created_at: Carbon}>
     */
    public static function list(): array
    {
        return array_values(collect(self::disk()->files(self::FOLDER))
            ->filter(fn (string $path): bool => str_ends_with($path, '.zip'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => self::disk()->size($path),
                'created_at' => Carbon::createFromTimestamp(self::disk()->lastModified($path)),
            ])
            ->sortByDesc('name')
            ->all());
    }

    /**
     * Delete all but the newest $keep backups. Returns how many were deleted.
     */
    public static function prune(int $keep): int
    {
        $old = array_slice(self::list(), max(1, $keep));

        foreach ($old as $backup) {
            self::disk()->delete($backup['path']);
        }

        return count($old);
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(config('services.backups.disk', 'local'));
    }

    /**
     * This site's tables only, without the temporary ones.
     *
     * @return list<string>
     */
    private static function tables(): array
    {
        $connection = DB::connection();
        $sharedServer = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $database = $connection->getDatabaseName();
        $tables = [];

        foreach (Schema::getTables() as $table) {
            $name = $table['name'];

            if ($sharedServer && $table['schema'] !== $database) {
                continue;
            }

            if (! in_array($name, self::SKIPPED_TABLES, true) && ! str_starts_with($name, 'sqlite_') && ! in_array($name, $tables, true)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }
}
