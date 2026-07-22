<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

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

    public static function getBackupDestinationData(string $disk, int $ttlSeconds = 4): array
    {
        return Cache::remember('backups-' . $disk, now()->addSeconds($ttlSeconds), function () use ($disk) {
            return BackupDestination::create($disk, config('backup.backup.name'))
                ->backups()
                ->map(function (Backup $backup) use ($disk) {
                    return [
                        'disk' => $disk,
                        'path' => $backup->path(),
                        'date' => $backup->date()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                        'size' => Format::humanReadableSize($backup->sizeInBytes()),
                    ];
                })
                ->toArray();
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
