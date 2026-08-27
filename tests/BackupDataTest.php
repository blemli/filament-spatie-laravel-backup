<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;

it('maps backups including type and cleanup timestamp', function () {
    Storage::fake('backups-disk');

    Storage::disk('backups-disk')->put('test-app/2026-07-20-01-30-00.zip', 'full');
    Storage::disk('backups-disk')->put('test-app/only-db-2026-07-21-02-00-00.zip', 'db');
    Storage::disk('backups-disk')->put('test-app/only-files-2026-07-19-03-00-00.zip', 'files');

    $data = collect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups-disk'))
        ->keyBy(fn (array $record): string => basename($record['path']));

    expect($data)->toHaveCount(3)
        ->and($data['2026-07-20-01-30-00.zip']['type'])->toBe('all')
        ->and($data['only-db-2026-07-21-02-00-00.zip']['type'])->toBe('db')
        ->and($data['only-files-2026-07-19-03-00-00.zip']['type'])->toBe('files')
        ->and($data['2026-07-20-01-30-00.zip']['disk'])->toBe('backups-disk')
        ->and($data['2026-07-20-01-30-00.zip']['size'])->not->toBeEmpty();

    // Spatie parses the date of conventionally named backups from the filename;
    // the cleanup timestamp adds the keep_all_backups_for_days window (7 by default).
    expect($data['2026-07-20-01-30-00.zip']['cleanup_at'])
        ->toBe(Carbon::parse('2026-07-27 01:30:00')->getTimestamp());
});

it('detects the type behind a configured filename prefix', function () {
    // Spatie prepends filename_prefix to every zip, including ones created
    // with an explicit --filename, so type detection must strip it first.
    config()->set('backup.backup.destination.filename_prefix', 'test-app-');

    Storage::fake('backups-disk');

    Storage::disk('backups-disk')->put('test-app/test-app-2026-07-20-01-30-00.zip', 'full');
    Storage::disk('backups-disk')->put('test-app/test-app-only-db-2026-07-21-02-00-00.zip', 'db');
    Storage::disk('backups-disk')->put('test-app/test-app-only-files-2026-07-19-03-00-00.zip', 'files');

    $data = collect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups-disk'))
        ->keyBy(fn (array $record): string => basename($record['path']));

    expect($data)->toHaveCount(3)
        ->and($data['test-app-2026-07-20-01-30-00.zip']['type'])->toBe('all')
        ->and($data['test-app-only-db-2026-07-21-02-00-00.zip']['type'])->toBe('db')
        ->and($data['test-app-only-files-2026-07-19-03-00-00.zip']['type'])->toBe('files');
});

it('generates a signed download url for disks without temporary urls', function () {
    // Not faked on purpose: Storage::fake() disks provide temporary URLs,
    // and this test covers the signed-route fallback for plain local disks.
    $url = FilamentSpatieLaravelBackup::getDownloadUrl('backups-disk', 'test-app/2026-07-20-01-30-00.zip');

    expect($url)->toContain('/filament-spatie-backup/download')
        ->toContain('signature=')
        ->toContain('expires=');
});

it('lists every monitored destination, not just the primary one', function () {
    config()->set('backup.monitor_backups', [
        ['name' => 'test-app', 'disks' => ['backups-disk'], 'health_checks' => []],
        ['name' => 'test-app-full', 'disks' => ['backups-disk'], 'health_checks' => []],
    ]);

    Storage::fake('backups-disk');

    Storage::disk('backups-disk')->put('test-app/only-db-2026-07-21-02-00-00.zip', 'db');
    Storage::disk('backups-disk')->put('test-app-full/2026-07-20-01-30-00.zip', 'full');

    $data = collect(FilamentSpatieLaravelBackup::getBackupDestinationData('backups-disk'))
        ->keyBy(fn (array $record): string => $record['path']);

    expect($data)->toHaveCount(2)
        ->and($data['test-app/only-db-2026-07-21-02-00-00.zip']['name'])->toBe('test-app')
        ->and($data['test-app-full/2026-07-20-01-30-00.zip']['name'])->toBe('test-app-full')
        ->and($data['test-app-full/2026-07-20-01-30-00.zip']['type'])->toBe('all');
});
