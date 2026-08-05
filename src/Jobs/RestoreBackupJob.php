<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\Actions\RestoreMediaFiles;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
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

    /**
     * @param  list<string>  $databaseConnections  restored one after another; empty
     *                                             leaves the command to its default
     */
    public function __construct(
        protected readonly string $disk,
        protected readonly string $path,
        // Not $connection: Queueable already owns that name for the queue.
        protected readonly array $databaseConnections = [],
        protected readonly ?string $password = null,
        protected readonly bool $reset = true,
        // Only an upload is disposable. An archive restored in place is a real
        // backup on its destination and has to survive being used.
        protected readonly bool $discardAfterwards = false,
        protected readonly bool $restoreMedia = false,
        ?int $timeout = null,
    ) {
        $this->timeout = $timeout;
    }

    public function handle(): void
    {
        if ($this->timeout === 0) {
            set_time_limit(0);
        }

        // Nothing may write while the database is being replaced. Maintenance
        // mode also parks the queue workers: a worker checks between jobs and
        // sleeps, so this job (already running) finishes while the rest wait.
        $this->takeDown();

        try {
            // The command restores one connection per run, so a backup covering
            // several databases takes one run each. Sequentially and inside the
            // one lock: they come from the same archive and belong together.
            foreach ($this->databaseConnections ?: [null] as $connection) {
                Artisan::call(RestoreCommand::class, array_filter([
                    '--disk' => $this->disk,
                    '--backup' => $this->path,
                    '--connection' => $connection,
                    '--password' => $this->password,
                    '--reset' => $this->reset,
                ], fn (mixed $value): bool => $value !== null && $value !== false));
            }

            if ($this->restoreMedia) {
                (new RestoreMediaFiles)->execute(
                    $this->localArchivePath(),
                    FilamentSpatieLaravelBackupPlugin::get()->getRestoreMediaDisk(),
                    $this->password,
                );
            }
        } finally {
            // Ordered so the app comes back even if discarding the upload fails.
            $this->bringUp();
            $this->discardUpload();
            static::markAsFinished();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->bringUp();
        $this->discardUpload();
        static::markAsFinished();
    }

    protected function takeDown(): void
    {
        Artisan::call('down', ['--render' => '', '--retry' => 60]);
    }

    /**
     * Best effort: a worker killed outright (OOM, SIGKILL) runs neither this nor
     * failed(), and the app stays down until someone runs `php artisan up`.
     */
    protected function bringUp(): void
    {
        try {
            Artisan::call('up');
        } catch (Throwable) {
            // Never let this mask the restore's own failure.
        }
    }

    /**
     * A local path the archive can be opened from. Remote disks are streamed to
     * a temporary file first, because ZipArchive needs a real filesystem path.
     */
    protected function localArchivePath(): string
    {
        $disk = Storage::disk($this->disk);

        try {
            $path = $disk->path($this->path);

            if (is_file($path)) {
                return $path;
            }
        } catch (Throwable) {
            // Not a local driver; fall through to downloading it.
        }

        $temporary = tempnam(sys_get_temp_dir(), 'backup-restore-') . '.zip';

        $source = $disk->readStream($this->path);
        $target = fopen($temporary, 'wb');
        stream_copy_to_stream($source, $target);
        fclose($target);

        if (is_resource($source)) {
            fclose($source);
        }

        return $temporary;
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
        if (! $this->discardAfterwards) {
            return;
        }

        Storage::disk($this->disk)->delete($this->path);
    }
}
