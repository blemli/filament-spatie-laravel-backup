<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('backups-disk');
    Storage::fake('local');
    $this->actingAs(new User);
});

function archive(): UploadedFile
{
    return UploadedFile::fake()->create('backup.zip', 16, 'application/zip');
}

function allowRestore(): void
{
    Gate::define('restore-backup', fn (User $user) => true);
}

it('hides the restore button without permission', function () {
    Livewire::test(Backups::class)->assertActionHidden('restore_backup');
});

it('shows the restore button to an authorized user', function () {
    allowRestore();

    Livewire::test(Backups::class)->assertActionVisible('restore_backup');
});

it('restores an uploaded archive', function () {
    allowRestore();
    Bus::fake();

    Livewire::test(Backups::class)
        ->callAction('restore_backup', [
            'archive' => archive(),
            'reset' => true,
            'confirmation' => 'overwrite everything',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    Bus::assertDispatchedAfterResponse(RestoreBackupJob::class);

    expect(RestoreBackupJob::isRunning())->toBeTrue();
});

it('refuses to restore without the exact confirmation phrase', function () {
    allowRestore();
    Bus::fake();

    Livewire::test(Backups::class)
        ->callAction('restore_backup', [
            'archive' => archive(),
            'confirmation' => 'yes',
        ])
        ->assertHasActionErrors(['confirmation']);

    Bus::assertNotDispatched(RestoreBackupJob::class);
});

it('refuses to restore without an archive', function () {
    allowRestore();
    Bus::fake();

    Livewire::test(Backups::class)
        ->callAction('restore_backup', [
            'confirmation' => 'overwrite everything',
        ])
        ->assertHasActionErrors(['archive']);

    Bus::assertNotDispatched(RestoreBackupJob::class);
});

it('parks the upload on the configured disk, not on a backup destination', function () {
    allowRestore();
    Bus::fake();

    Livewire::test(Backups::class)->callAction('restore_backup', [
        'archive' => archive(),
        'confirmation' => 'overwrite everything',
    ]);

    expect(Storage::disk('local')->files('filament-spatie-backup-restore'))->toHaveCount(1)
        ->and(Storage::disk('backups-disk')->allFiles())->toBeEmpty();
});

it('will not restore while a backup is running', function () {
    allowRestore();
    Bus::fake();

    CreateBackupJob::markAsRunning(null);

    Livewire::test(Backups::class)->callAction('restore_backup', [
        'archive' => archive(),
        'confirmation' => 'overwrite everything',
    ]);

    Bus::assertNotDispatched(RestoreBackupJob::class);
});

it('blocks a new backup while a restore is running', function () {
    Gate::define('create-backup', fn (User $user) => true);

    RestoreBackupJob::markAsRunning(null);

    Livewire::test(Backups::class)->assertActionDisabled('create_backup');
});

it('offers a connection choice only when the backup covers more than one', function () {
    allowRestore();

    config()->set('backup.backup.source.databases', ['sqlite']);
    expect(Backups::restorableConnections())->toBe(['sqlite']);

    config()->set('backup.backup.source.databases', ['sqlite', 'ceebo']);
    expect(Backups::restorableConnections())->toBe(['sqlite', 'ceebo']);
});
