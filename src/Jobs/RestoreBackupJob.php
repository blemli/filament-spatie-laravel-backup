<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const RUNNING_CACHE_KEY = 'filament-spatie-backup::restore-running';

    // Public so queue workers pick it up from the payload; 0 disables the worker timeout.
    public ?int $timeout;

    public function __construct(
        protected readonly string $disk,
        protected readonly string $path,
        // Not $connection: Queueable already owns that name for the queue.
        protected readonly ?string $databaseConnection = null,
        protected readonly ?string $password = null,
        protected readonly bool $reset = true,
        ?int $timeout = null,
    ) {
        $this->timeout = $timeout;
    }

    public function handle(): void
    {
        if ($this->timeout === 0) {
            set_time_limit(0);
        }

        try {
            Artisan::call(RestoreCommand::class, array_filter([
                '--disk' => $this->disk,
                '--backup' => $this->path,
                '--connection' => $this->databaseConnection,
                '--password' => $this->password,
                '--reset' => $this->reset,
            ], fn (mixed $value): bool => $value !== null && $value !== false));
        } finally {
            $this->discardUpload();
            static::markAsFinished();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->discardUpload();
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

    /**
     * An upload is a full copy of the database in a directory the app can read,
     * so it goes as soon as the restore is done with it — success or failure.
     * The operator still holds the file they uploaded, so nothing is lost.
     */
    protected function discardUpload(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
