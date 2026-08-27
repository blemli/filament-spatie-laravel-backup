<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Spatie\Backup\Config\MonitoredBackupsConfig;
use Spatie\Backup\Helpers\Format;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;

class FilamentSpatieLaravelBackup
{
    public static function getDisks(): array
    {
        return config('backup.backup.destination.disks');
    }

    public static function getDisk(): string
    {
        $defaultDisks = static::getDisks();

        return request('tableFilters.disk.value', reset($defaultDisks));
    }

    public static function getFilterDisks(): array
    {
        $result = [];

        foreach (static::getDisks() as $value) {
            $result[$value] = ucfirst($value);
        }

        return $result;
    }

    /**
     * Every backup destination name worth listing: the primary backup name,
     * plus any additionally monitored ones — an app backing up into several
     * folders (e.g. nightly db-only plus weekly full) monitors each of them.
     * A monitored folder with no backups simply contributes no rows.
     */
    public static function getBackupNames(): array
    {
        return collect([config('backup.backup.name')])
            ->merge(collect(config('backup.monitor_backups', []))->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function getBackupDestinationData(string $disk, int $ttlSeconds = 4): array
    {
        return Cache::remember('backups-' . $disk, now()->addSeconds($ttlSeconds), function () use ($disk) {
            return collect(static::getBackupNames())
                ->flatMap(fn (string $name) => static::getBackupDataForName($disk, $name))
                ->values()
                ->toArray();
        });
    }

    protected static function getBackupDataForName(string $disk, string $name): Collection
    {
        return BackupDestination::create($disk, $name)
            ->backups()
            ->map(function (Backup $backup) use ($disk, $name) {
                $file = basename($backup->path());

                // Spatie prepends the configured filename_prefix to every zip,
                // even ones created with an explicit --filename; strip it so
                // the option-based type detection below still matches.
                $prefix = (string) config('backup.backup.destination.filename_prefix', '');
                if ($prefix !== '' && str_starts_with($file, $prefix)) {
                    $file = substr($file, strlen($prefix));
                }

                return [
                    'disk' => $disk,
                    'name' => $name,
                    'path' => $backup->path(),
                    'date' => $backup->date()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    'size' => Format::humanReadableSize($backup->sizeInBytes()),
                    // Backups created from the panel are named after their option;
                    // anything else (e.g. plain artisan backup:run) is a full backup.
                    'type' => str_starts_with($file, 'only-db-') ? 'db' : (str_starts_with($file, 'only-files-') ? 'files' : 'all'),
                    // End of spatie's "keep all backups" window; afterwards the
                    // cleanup strategy thins backups out on a rotation schedule.
                    'cleanup_at' => $backup->date()
                        ->clone()
                        ->addDays((int) config('backup.cleanup.default_strategy.keep_all_backups_for_days', 7))
                        ->getTimestamp(),
                ];
            });
    }

    /**
     * A URL the browser can download the backup from directly (bypassing Livewire):
     * a temporary URL when the disk supports them (e.g. S3), otherwise a signed
     * route that streams the file. Both expire after 30 minutes.
     */
    public static function getDownloadUrl(string $disk, string $path): string
    {
        $filesystem = Storage::disk($disk);

        if ($filesystem->providesTemporaryUrls()) {
            return $filesystem->temporaryUrl($path, now()->addMinutes(30));
        }

        return URL::signedRoute(
            'filament-spatie-backup.download',
            ['disk' => $disk, 'path' => $path],
            now()->addMinutes(30),
        );
    }

    public static function getBackupDestinationStatusData(int $ttlSeconds = 4): array
    {
        return Cache::remember('backup-statuses', now()->addSeconds($ttlSeconds), function () {
            $config = class_exists('Spatie\Backup\Config\MonitoredBackupsConfig')
                ? MonitoredBackupsConfig::fromArray(config('backup.monitor_backups'))
                : config('backup.monitor_backups');

            return BackupDestinationStatusFactory::createForMonitorConfig($config)
                ->map(function (BackupDestinationStatus $backupDestinationStatus, int | string $key) {
                    return [
                        'id' => $key,
                        'name' => $backupDestinationStatus->backupDestination()->backupName(),
                        'disk' => $backupDestinationStatus->backupDestination()->diskName(),
                        'reachable' => $backupDestinationStatus->backupDestination()->isReachable(),
                        'healthy' => $backupDestinationStatus->isHealthy(),
                        'amount' => $backupDestinationStatus->backupDestination()->backups()->count(),
                        'newest' => $backupDestinationStatus->backupDestination()->newestBackup()
                            ? $backupDestinationStatus->backupDestination()->newestBackup()->date()->diffForHumans()
                            : __('filament-spatie-backup::backup.components.backup_destination_status_list.table.fields.no_backups_present'),
                        'usedStorage' => Format::humanReadableSize($backupDestinationStatus->backupDestination()->usedStorage()),
                    ];
                })
                ->values()
                ->toArray();
        });
    }
}
