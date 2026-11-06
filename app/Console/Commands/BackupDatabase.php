<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

#[Signature('app:backup-database {--keep= : How many backups to keep (default from BACKUP_KEEP, 14)}')]
#[Description('Back up this site\'s database to a zip file and delete the oldest backups')]
class BackupDatabase extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = DatabaseBackup::create();
        $size = DatabaseBackup::disk()->size($path);
        $deleted = DatabaseBackup::prune((int) ($this->option('keep') ?: config('services.backups.keep', 14)));

        $this->components->info('Backup saved: '.$path.' ('.Number::fileSize($size).').'.($deleted ? " Deleted {$deleted} old backup(s)." : ''));

        return self::SUCCESS;
    }
}
