<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use Spatie\Backup\Commands\BackupCommand;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const RUNNING_CACHE_KEY = 'filament-spatie-backup::backup-running';

    // Public so queue workers pick it up from the payload; 0 disables the worker timeout.
    public ?int $timeout;

    public function __construct(
        protected readonly Option $option = Option::ALL,
        ?int $timeout = null,
    ) {
        $this->timeout = $timeout;
    }

    public function handle(): void
    {
        if ($this->timeout === 0) {
            // Spatie's BackupCommand ignores a --timeout of 0, so lift the limit ourselves.
            set_time_limit(0);
        }

        try {
            Artisan::call(BackupCommand::class, [
                '--only-db' => $this->option === Option::ONLY_DB,
                '--only-files' => $this->option === Option::ONLY_FILES,
                '--filename' => match ($this->option) {
                    Option::ALL => null,
                    default => str_replace('_', '-', $this->option->value) .
                        '-' . date('Y-m-d-H-i-s') . '.zip'
                },
                '--timeout' => $this->timeout > 0 ? $this->timeout : null,
            ]);
        } finally {
            static::markAsFinished();
        }
    }

    public function failed(?Throwable $exception): void
    {
        static::markAsFinished();
    }

    public static function isRunning(): bool
    {
        return (bool) Cache::get(static::RUNNING_CACHE_KEY, false);
    }

    /**
     * The flag expires on its own (at least 30 minutes, or the configured job
     * timeout if longer) so a crashed worker can never block the button forever.
     */
    public static function markAsRunning(?int $timeout): void
    {
        Cache::put(static::RUNNING_CACHE_KEY, true, now()->addSeconds(max($timeout ?? 0, 1800)));
    }

    public static function markAsFinished(): void
    {
        Cache::forget(static::RUNNING_CACHE_KEY);
    }
}
