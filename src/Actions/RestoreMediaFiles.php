<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Actions;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Put the media files of a disk back from a backup archive.
 *
 * Only the files: the archive also holds the application itself, and replacing
 * running code with an older copy of it from a zip is not a thing to do behind
 * a button. The disk is emptied first, so what is left is exactly what the
 * backup held — anything uploaded since is gone, which is the point.
 */
class RestoreMediaFiles
{
    /**
     * @return int the number of files written
     */
    public function execute(string $archivePath, string $diskName, ?string $password = null): int
    {
        $disk = Storage::disk($diskName);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Could not open the backup archive at [{$archivePath}].");
        }

        if ($password !== null && $password !== '') {
            $zip->setPassword($password);
        }

        $marker = static::markerFor($diskName);
        $entries = static::entriesUnder($zip, $marker);

        if ($entries === []) {
            $zip->close();

            // A db-only backup holds no files at all. Leaving the disk untouched
            // beats emptying it and calling that a restore.
            return 0;
        }

        // Prove the archive is readable before touching anything. A wrong
        // password otherwise empties the media directory and only then finds
        // out it cannot refill it.
        $probe = $zip->getStream((string) array_key_first($entries));

        if ($probe === false) {
            $zip->close();

            throw new RuntimeException('Could not read the archive — wrong password?');
        }

        fclose($probe);

        static::emptyDisk($disk);

        $written = 0;

        foreach ($entries as $entryName => $relativePath) {
            $stream = $zip->getStream($entryName);

            if ($stream === false) {
                $zip->close();

                throw new RuntimeException("Could not read [{$entryName}] from the archive — wrong password?");
            }

            // Streamed, not read into memory: a media directory is bigger than
            // any sane memory_limit and the files arrive one at a time anyway.
            $disk->writeStream($relativePath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            $written++;
        }

        $zip->close();

        return $written;
    }

    /**
     * The archive stores whatever `backup.source.files.relative_path` produced —
     * absolute paths by default, so `/var/www/html/storage/app/public/…` in one
     * environment and `/Users/…/storage/app/public/…` in another. Matching on the
     * disk's path relative to the project root keeps both working.
     */
    protected static function markerFor(string $diskName): string
    {
        $root = rtrim((string) Storage::disk($diskName)->path(''), '/');
        $base = rtrim(base_path(), '/');

        $relative = str_starts_with($root, $base . '/')
            ? substr($root, strlen($base) + 1)
            : basename($root);

        return trim($relative, '/') . '/';
    }

    /**
     * @return array<string, string> entry name => path relative to the disk root
     */
    protected static function entriesUnder(ZipArchive $zip, string $marker): array
    {
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || str_ends_with($name, '/')) {
                continue;
            }

            $position = strpos($name, $marker);

            if ($position === false) {
                continue;
            }

            // Only when the marker sits at the start or on a path boundary, so
            // a stray "…/other-storage/app/public/…" cannot match.
            if ($position !== 0 && $name[$position - 1] !== '/') {
                continue;
            }

            $relative = substr($name, $position + strlen($marker));

            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $entries[$name] = $relative;
        }

        return $entries;
    }

    protected static function emptyDisk(FilesystemAdapter $disk): void
    {
        foreach ($disk->directories() as $directory) {
            $disk->deleteDirectory($directory);
        }

        $disk->delete($disk->files());
    }
}
