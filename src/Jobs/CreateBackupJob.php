<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use Spatie\Backup\Commands\BackupCommand;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

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
    }
}
