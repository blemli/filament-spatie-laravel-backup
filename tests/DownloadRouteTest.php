<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\User;

beforeEach(function () {
    Storage::fake('backups-disk');
    Storage::disk('backups-disk')->put('test-app/2026-07-20-01-30-00.zip', 'full');

    $this->url = URL::signedRoute('filament-spatie-backup.download', [
        'disk' => 'backups-disk',
        'path' => 'test-app/2026-07-20-01-30-00.zip',
    ], now()->addMinutes(30));
});

it('rejects unsigned requests', function () {
    $this->get(route('filament-spatie-backup.download', [
        'disk' => 'backups-disk',
        'path' => 'test-app/2026-07-20-01-30-00.zip',
    ]))->assertForbidden();
});

it('rejects guests', function () {
    $this->get($this->url)->assertForbidden();
});

it('streams the backup to authenticated users', function () {
    $this->actingAs(new User)
        ->get($this->url)
        ->assertOk()
        ->assertDownload('2026-07-20-01-30-00.zip');
});

it('only serves configured backup disks', function () {
    config()->set('filesystems.disks.other-disk', [
        'driver' => 'local',
        'root' => storage_path('framework/testing/disks/other-disk'),
    ]);

    $url = URL::signedRoute('filament-spatie-backup.download', [
        'disk' => 'other-disk',
        'path' => 'test-app/2026-07-20-01-30-00.zip',
    ], now()->addMinutes(30));

    $this->actingAs(new User)->get($url)->assertNotFound();
});

it('returns 404 for missing backups', function () {
    $url = URL::signedRoute('filament-spatie-backup.download', [
        'disk' => 'backups-disk',
        'path' => 'test-app/nope.zip',
    ], now()->addMinutes(30));

    $this->actingAs(new User)->get($url)->assertNotFound();
});
