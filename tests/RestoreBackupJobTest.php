<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;
use Wnx\LaravelBackupRestore\Commands\RestoreCommand;

// Testbench's console kernel is final, so the Artisan facade cannot be mocked
// directly; swap in a mock of the kernel contract instead.
function fakeRestoreArtisan(): MockInterface
{
    $kernel = Mockery::mock(Kernel::class);

    Artisan::swap($kernel);

    return $kernel;
}

beforeEach(function () {
    Storage::fake('uploads-disk');
    Storage::disk('uploads-disk')->put('restore/archive.zip', 'zip');
});

it('exposes the timeout to queue workers', function () {
    expect((new RestoreBackupJob('uploads-disk', 'restore/archive.zip', timeout: 300))->timeout)->toBe(300)
        ->and((new RestoreBackupJob('uploads-disk', 'restore/archive.zip'))->timeout)->toBeNull()
        ->and((new RestoreBackupJob('uploads-disk', 'restore/archive.zip', timeout: 0))->timeout)->toBe(0);
});

it('runs the restore command with the uploaded archive', function () {
    fakeRestoreArtisan()->shouldReceive('call')
        ->once()
        ->withArgs(function (string $command, array $options): bool {
            return $command === RestoreCommand::class
                && $options['--disk'] === 'uploads-disk'
                && $options['--backup'] === 'restore/archive.zip'
                && $options['--connection'] === 'ceebo'
                && $options['--password'] === 'hunter2'
                && $options['--reset'] === true;
        })
        ->andReturn(0);

    (new RestoreBackupJob('uploads-disk', 'restore/archive.zip', 'ceebo', 'hunter2'))->handle();
});

it('omits the options it has no value for', function () {
    fakeRestoreArtisan()->shouldReceive('call')
        ->once()
        ->withArgs(function (string $command, array $options): bool {
            return ! array_key_exists('--connection', $options)
                && ! array_key_exists('--password', $options)
                // A false --reset is a flag that must not be passed at all.
                && ! array_key_exists('--reset', $options);
        })
        ->andReturn(0);

    (new RestoreBackupJob('uploads-disk', 'restore/archive.zip', reset: false))->handle();
});

it('discards the uploaded archive once the restore is done', function () {
    fakeRestoreArtisan()->shouldReceive('call')->once()->andReturn(0);

    (new RestoreBackupJob('uploads-disk', 'restore/archive.zip'))->handle();

    Storage::disk('uploads-disk')->assertMissing('restore/archive.zip');
});

it('discards the uploaded archive when the restore throws', function () {
    fakeRestoreArtisan()->shouldReceive('call')->once()->andThrow(new RuntimeException('restore failed'));

    expect(fn () => (new RestoreBackupJob('uploads-disk', 'restore/archive.zip'))->handle())
        ->toThrow(RuntimeException::class);

    Storage::disk('uploads-disk')->assertMissing('restore/archive.zip');
});

it('tracks its running state in the cache', function () {
    expect(RestoreBackupJob::isRunning())->toBeFalse();

    RestoreBackupJob::markAsRunning(null);
    expect(RestoreBackupJob::isRunning())->toBeTrue();

    RestoreBackupJob::markAsFinished();
    expect(RestoreBackupJob::isRunning())->toBeFalse();
});

it('clears the running flag and the upload when the job fails', function () {
    RestoreBackupJob::markAsRunning(null);

    (new RestoreBackupJob('uploads-disk', 'restore/archive.zip'))->failed(new RuntimeException('worker died'));

    expect(RestoreBackupJob::isRunning())->toBeFalse();
    Storage::disk('uploads-disk')->assertMissing('restore/archive.zip');
});

it('does not share its running flag with the backup job', function () {
    RestoreBackupJob::markAsRunning(null);

    expect(CreateBackupJob::isRunning())->toBeFalse();
});
