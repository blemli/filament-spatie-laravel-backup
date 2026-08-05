<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\Actions\RestoreMediaFiles;

/**
 * Spatie stores absolute paths by default, so an archive made in a container
 * carries /var/www/html/... while the disk here lives somewhere else entirely.
 */
function mediaArchive(string $prefix = 'var/www/html/', ?string $password = null): string
{
    $path = sys_get_temp_dir() . '/media-' . bin2hex(random_bytes(4)) . '.zip';

    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString($prefix . 'storage/app/public/covers/from-backup.jpg', 'cover');
    $zip->addFromString($prefix . 'storage/app/public/reading/sample.mp3', 'audio');
    // Must be ignored: not media, and restoring it would replace running code.
    $zip->addFromString($prefix . 'app/Models/Audiobook.php', '<?php class Audiobook {}');
    $zip->addFromString($prefix . 'db-dumps/sqlite-database.sql', 'INSERT INTO x VALUES (1);');

    if ($password !== null) {
        foreach (['covers/from-backup.jpg', 'reading/sample.mp3'] as $entry) {
            $zip->setEncryptionName($prefix . 'storage/app/public/' . $entry, ZipArchive::EM_AES_256, $password);
        }
    }

    $zip->close();

    return $path;
}

beforeEach(function () {
    // Rooted where a real 'public' disk sits — under base_path() as
    // storage/app/public — because that relative path is what the action
    // matches archive entries against.
    config()->set('filesystems.disks.media-disk', [
        'driver' => 'local',
        'root' => storage_path('app/public'),
    ]);

    Storage::forgetDisk('media-disk');
    File::deleteDirectory(storage_path('app/public'));
    File::ensureDirectoryExists(storage_path('app/public'));
});

afterEach(function () {
    File::deleteDirectory(storage_path('app/public'));
});

it('replaces the media directory with what the archive holds', function () {
    Storage::disk('media-disk')->put('covers/stale.jpg', 'written after the backup');
    Storage::disk('media-disk')->put('reading/stale.mp3', 'also stale');

    $written = (new RestoreMediaFiles)->execute(mediaArchive(), 'media-disk');

    expect($written)->toBe(2);

    Storage::disk('media-disk')->assertExists('covers/from-backup.jpg');
    Storage::disk('media-disk')->assertExists('reading/sample.mp3');

    // Emptied first: the disk is exactly what the backup had, nothing more.
    Storage::disk('media-disk')->assertMissing('covers/stale.jpg');
    Storage::disk('media-disk')->assertMissing('reading/stale.mp3');
});

it('never writes anything outside the media disk', function () {
    (new RestoreMediaFiles)->execute(mediaArchive(), 'media-disk');

    expect(Storage::disk('media-disk')->allFiles())
        ->toBe(['covers/from-backup.jpg', 'reading/sample.mp3']);
});

it('matches the media however the archive spells the absolute path', function () {
    (new RestoreMediaFiles)->execute(mediaArchive('Users/someone/projects/app/'), 'media-disk');

    Storage::disk('media-disk')->assertExists('covers/from-backup.jpg');
});

it('leaves the disk alone when the archive holds no media', function () {
    Storage::disk('media-disk')->put('covers/keep.jpg', 'still here');

    $path = sys_get_temp_dir() . '/dbonly-' . bin2hex(random_bytes(4)) . '.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('var/www/html/db-dumps/sqlite-database.sql', 'INSERT INTO x VALUES (1);');
    $zip->close();

    expect((new RestoreMediaFiles)->execute($path, 'media-disk'))->toBe(0);

    // A db-only backup must not be read as "the media directory was empty".
    Storage::disk('media-disk')->assertExists('covers/keep.jpg');
});

it('reads an encrypted archive with the password', function () {
    $written = (new RestoreMediaFiles)->execute(mediaArchive(password: 'hunter2'), 'media-disk', 'hunter2');

    expect($written)->toBe(2)
        ->and(Storage::disk('media-disk')->get('covers/from-backup.jpg'))->toBe('cover');
});

it('fails on a wrong password without emptying the disk first', function () {
    Storage::disk('media-disk')->put('covers/keep.jpg', 'still here');

    expect(fn () => (new RestoreMediaFiles)->execute(mediaArchive(password: 'hunter2'), 'media-disk', 'wrong'))
        ->toThrow(RuntimeException::class);

    // The archive is checked before the disk is touched, so a bad password
    // leaves the media exactly as it was rather than destroying it.
    Storage::disk('media-disk')->assertExists('covers/keep.jpg');
});
