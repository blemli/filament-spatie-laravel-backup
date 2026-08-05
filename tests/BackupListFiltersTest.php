<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Schemas\RestoreForm;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\User;

beforeEach(function () {
    Carbon::setTestNow('2026-07-22 12:00:00');

    $this->actingAs(new User);

    Storage::fake('backups-disk');
    // Dates parse from the filename; the prefixed ones fall back to the file
    // mtime ("now"), so everything except the June backup is still retained.
    Storage::disk('backups-disk')->put('test-app/2026-07-20-01-30-00.zip', 'full');
    Storage::disk('backups-disk')->put('test-app/2026-06-01-00-00-00.zip', 'old-full');
    Storage::disk('backups-disk')->put('test-app/only-db-2026-07-21-02-00-00.zip', 'db');
    Storage::disk('backups-disk')->put('test-app/only-files-2026-07-19-03-00-00.zip', 'files');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('shows all backups without filters', function () {
    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->assertSee('2026-07-20-01-30-00.zip')
        ->assertSee('only-db-2026-07-21-02-00-00.zip')
        ->assertSee('only-files-2026-07-19-03-00-00.zip');
});

it('filters by type', function () {
    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->filterTable('type', 'db')
        ->assertSee('only-db-2026-07-21-02-00-00.zip')
        ->assertDontSee('only-files-2026-07-19-03-00-00.zip')
        ->assertDontSee('2026-07-20-01-30-00.zip');
});

it('filters by cleanup status', function () {
    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->filterTable('cleanup', 'in_rotation')
        ->assertSee('2026-06-01-00-00-00.zip')
        ->assertDontSee('2026-07-20-01-30-00.zip');

    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->filterTable('cleanup', 'retained')
        ->assertSee('2026-07-20-01-30-00.zip')
        ->assertDontSee('2026-06-01-00-00-00.zip');
});

it('filters by disk', function () {
    config()->set('backup.backup.destination.disks', ['backups-disk', 'other-disk']);
    config()->set('filesystems.disks.other-disk', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/other-disk'),
    ]);

    Storage::fake('other-disk');
    Storage::disk('other-disk')->put('test-app/2026-07-18-06-00-00.zip', 'other');

    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->filterTable('disk', 'backups-disk')
        ->assertSee('2026-07-20-01-30-00.zip')
        ->assertDontSee('2026-07-18-06-00-00.zip');
});

it('hides the disk filter when there is only one destination', function () {
    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->assertTableFilterHidden('disk')
        ->assertTableFilterVisible('type');
});

it('shows the disk filter once a second destination exists', function () {
    config()->set('backup.backup.destination.disks', ['backups-disk', 'second-disk']);
    config()->set('filesystems.disks.second-disk', config('filesystems.disks.backups-disk'));
    Storage::fake('second-disk');

    Livewire::test(BackupDestinationListRecords::class)
        ->loadTable()
        ->assertTableFilterVisible('disk');
});

it('applies filters without waiting for an apply click', function () {
    expect(Livewire::test(BackupDestinationListRecords::class)->instance()->getTable()->hasDeferredFilters())
        ->toBeFalse();
});

it('restores a backup straight from its destination', function () {
    Gate::define('restore-backup', fn (User $user) => true);
    Bus::fake();

    RestoreForm::dispatch(
        disk: 'backups-disk',
        path: 'test-app/only-db-2026-07-21-02-00-00.zip',
        data: ['reset' => true],
        discardAfterwards: false,
    );

    Bus::assertDispatchedAfterResponse(RestoreBackupJob::class);

    // The archive is a real backup on its destination, not a disposable upload.
    Storage::disk('backups-disk')->assertExists('test-app/only-db-2026-07-21-02-00-00.zip');
});

it('puts a restore action on every row', function () {
    $names = collect(
        Livewire::test(BackupDestinationListRecords::class)->instance()->getTable()->getRecordActions()
    )->map(fn ($action) => $action->getName());

    expect($names)->toContain('restore');
});
