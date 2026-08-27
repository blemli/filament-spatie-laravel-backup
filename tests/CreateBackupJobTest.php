<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Mockery\MockInterface;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use Spatie\Backup\Commands\BackupCommand;

// Testbench's console kernel is final, so the Artisan facade cannot be mocked
// directly; swap in a mock of the kernel contract instead.
function fakeArtisan(): MockInterface
{
    $kernel = Mockery::mock(Kernel::class);

    Artisan::swap($kernel);

    return $kernel;
}

it('exposes the timeout to queue workers', function () {
    expect((new CreateBackupJob(Option::ALL, 300))->timeout)->toBe(300)
        ->and((new CreateBackupJob(Option::ALL))->timeout)->toBeNull()
        ->and((new CreateBackupJob(Option::ALL, 0))->timeout)->toBe(0);
});

it('runs the backup command with the right options', function () {
    fakeArtisan()->shouldReceive('call')
        ->once()
        ->withArgs(function (string $command, array $options): bool {
            return $command === BackupCommand::class
                && $options['--only-db'] === true
                && $options['--only-files'] === false
                && str_starts_with($options['--filename'], 'only-db-')
                && $options['--timeout'] === 120;
        })
        ->andReturn(0);

    (new CreateBackupJob(Option::ONLY_DB, 120))->handle();
});

it('passes no --timeout to the command when timeouts are disabled', function () {
    fakeArtisan()->shouldReceive('call')
        ->once()
        ->withArgs(fn (string $command, array $options): bool => $options['--timeout'] === null)
        ->andReturn(0);

    (new CreateBackupJob(Option::ALL, 0))->handle();
});

it('tracks its running state in the cache', function () {
    expect(CreateBackupJob::isRunning())->toBeFalse();

    CreateBackupJob::markAsRunning(null);
    expect(CreateBackupJob::isRunning())->toBeTrue();

    CreateBackupJob::markAsFinished();
    expect(CreateBackupJob::isRunning())->toBeFalse();
});

it('clears the running flag once the backup finished', function () {
    fakeArtisan()->shouldReceive('call')->once()->andReturn(0);

    CreateBackupJob::markAsRunning(null);
    (new CreateBackupJob(Option::ALL))->handle();

    expect(CreateBackupJob::isRunning())->toBeFalse();
});

it('clears the running flag when the backup command throws', function () {
    fakeArtisan()->shouldReceive('call')->once()->andThrow(new RuntimeException('backup failed'));

    CreateBackupJob::markAsRunning(null);

    expect(fn () => (new CreateBackupJob(Option::ALL))->handle())
        ->toThrow(RuntimeException::class)
        ->and(CreateBackupJob::isRunning())->toBeFalse();
});

it('clears the running flag when the job fails', function () {
    CreateBackupJob::markAsRunning(null);

    (new CreateBackupJob(Option::ALL))->failed(new RuntimeException('worker died'));

    expect(CreateBackupJob::isRunning())->toBeFalse();
});

it('routes the full backup through the configured command', function () {
    fakeArtisan()->shouldReceive('call')
        ->once()
        ->with('backup:full')
        ->andReturn(0);

    (new CreateBackupJob(Option::ALL, fullBackupCommand: 'backup:full'))->handle();
});

it('keeps the db and files options on the stock command despite a configured full command', function () {
    fakeArtisan()->shouldReceive('call')
        ->once()
        ->withArgs(fn (string $command, array $options): bool => $command === BackupCommand::class
            && $options['--only-db'] === true)
        ->andReturn(0);

    (new CreateBackupJob(Option::ONLY_DB, fullBackupCommand: 'backup:full'))->handle();
});
