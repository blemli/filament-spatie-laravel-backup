<?php

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('backups-disk');
    $this->actingAs(new User);
});

it('renders the backups page', function () {
    Livewire::test(Backups::class)->assertSuccessful();
});

it('hides the create button without permission', function () {
    Livewire::test(Backups::class)
        ->assertActionHidden('create_backup');
});

it('creates a full backup through the modal', function () {
    Gate::define('create-backup', fn (User $user) => true);
    Bus::fake();

    Livewire::test(Backups::class)
        ->assertActionVisible('create_backup')
        ->callAction(['create_backup', 'create_backup_all'])
        ->assertNotified();

    Bus::assertDispatchedAfterResponse(CreateBackupJob::class);

    expect(CreateBackupJob::isRunning())->toBeTrue();
});

it('creates a db-only backup through the modal', function () {
    Gate::define('create-backup', fn (User $user) => true);
    Bus::fake();

    Livewire::test(Backups::class)->callAction(['create_backup', 'create_backup_db']);

    Bus::assertDispatchedAfterResponse(CreateBackupJob::class);
});

it('rejects a second backup while one is running', function () {
    Gate::define('create-backup', fn (User $user) => true);
    Bus::fake();

    CreateBackupJob::markAsRunning(null);

    Livewire::test(Backups::class)->callAction(['create_backup', 'create_backup_all']);

    Bus::assertNotDispatchedAfterResponse(CreateBackupJob::class);
});
