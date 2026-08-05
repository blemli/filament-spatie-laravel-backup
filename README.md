# Filament Spatie Laravel Backup

[![PHP Version Require](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/require/php)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Latest Stable Version](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/v)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![Total Downloads](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/downloads)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)
[![License](https://poser.pugx.org/shuvroroy/filament-spatie-laravel-backup/license)](https://packagist.org/packages/shuvroroy/filament-spatie-laravel-backup)

This package provides a Filament page that you can create backup of your application. You'll find installation instructions and full documentation on [spatie/laravel-backup](https://spatie.be/docs/laravel-backup/v8/introduction).

<img width="1481" alt="Screenshot 2023-08-05 at 2 42 10 PM" src="https://github.com/shuvroroy/filament-spatie-laravel-backup/assets/21066418/68fe1c0b-a130-41ce-8c7f-e5182d743225">

## Installation

You can install the package via composer:

```bash
composer require shuvroroy/filament-spatie-laravel-backup
```

Publish the package's assets:

```bash
php artisan filament:assets
```

You can publish the lang file with:

```bash
php artisan vendor:publish --tag="filament-spatie-backup-translations"
```

## Usage

You first need to register the plugin with Filament. This can be done inside of your `PanelProvider`, e.g. `AdminPanelProvider`.

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(FilamentSpatieLaravelBackupPlugin::make());
    }
}
```

If you want to override the default `Backups` page icon, heading then you can extend the page class and override the `navigationIcon` property and `getHeading` method and so on.

```php
<?php

namespace App\Filament\Pages;

use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cpu-chip';

    public function getHeading(): string | Htmlable
    {
        return 'Application Backups';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Core';
    }
}
```
Then register the extended page class on `AdminPanelProvider` class.

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use App\Filament\Pages\Backups;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(Backups::class)
            );
    }
}
```

## Permissions Setup (for Creating, Downloading & Deleting backups)

If you're using [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) or [Filament Shield](https://github.com/bezhansalleh/filament-shield), you need to manually define the permissions used by this backup panel.

### Required Permissions

- `download-backup` – Allows downloading existing backups.
- `delete-backup` – Allows deleting backups from the panel.
- `create-backup` – Allows creating new backups from the panel.
- `restore-backup` – Allows uploading an archive and restoring the database from it.

### Seeder Example

You can create a seeder to register these permissions and assign them to a role:

```php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class BackupPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'download-backup',
            'delete-backup',
            'create-backup',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign to a role (optional)
        $role = Role::firstOrCreate(['name' => 'backup']);
        $role->givePermissionTo($permissions);

        // Assign role to a user (optional)
        $user = \App\Models\User::find(1); // Change ID as needed
        
        if ($user && !$user->hasRole('backup')) {
            $user->assignRole('backup');
        }
    }
}
```

Run the seeder using:

```bash
php artisan db:seed --class=BackupPermissionSeeder
```

After this, users with the `backup` role will have full access to the backup panel.

### Customising action authorization

If you don't use a permission system (or want different rules), you can replace the
gate checks per action with a boolean or closure on the plugin:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->authorizeCreateUsing(fn (): bool => auth()->user()->isAdmin())
    ->authorizeDownloadUsing(true)
    ->authorizeDeleteUsing(false)
    ->authorizeRestoreUsing(fn (): bool => auth()->user()->isOwner())
```

Without these hooks the default gate checks (`create-backup`, `download-backup`,
`delete-backup`, `restore-backup`) stay in effect. The checks are enforced
server-side, not just by hiding buttons.


## Customising navigation

You can customise the navigation icon, label, group, and sort order directly on the plugin without extending the page class:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->navigationIcon('heroicon-o-cpu-chip')
                    ->navigationLabel('Backups')
                    ->navigationGroup('Settings')
                    ->navigationSort(3)
            );
    }
}
```

All navigation methods also accept closures for dynamic values:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->navigationLabel(fn (): string => __('custom.backups'))
    ->navigationGroup(fn (): ?string => auth()->user()->isAdmin() ? 'Admin' : 'Tools')
```

Pass `null` to `navigationGroup()` to remove the page from any navigation group.

## Customising the polling interval

The backup tables poll for changes (so backups created elsewhere, e.g. via
`php artisan backup:run`, show up automatically), and the backup listings are cached
for one polling interval so each poll hits the storage disk at most once no matter
how many browsers are polling. You can customise the interval by following the steps
below:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPollingInterval('10s') // default value is 4s
            );
    }
}
```

Pass `null` to disable polling entirely. (The misspelled `usingPolingInterval()` is
still supported but deprecated.)

## Customising the queue

By default the backup job runs in the web process after the response has been sent.
If you configure a queue, the job is genuinely dispatched to it instead — recommended
for anything but small backups, since queue workers aren't bound by web timeouts:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingQueue('my-queue') // default value is null
            );
    }
}
```

## Customising the timeout

You can customise the timeout for the backup job by following the steps below:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->timeout(120) // default value is max_execution_time from php.ini, or 30s if it wasn't defined
            );
    }
}
```

The timeout also applies when the job runs on a queue: workers honor it instead of
killing long backups after their default 60 seconds.

For more details refer to the [set_time_limit](https://www.php.net/manual/en/function.set-time-limit.php) function.

You can also disable the timeout altogether to let the job run as long as needed:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->noTimeout()
            );
    }
}
```

## Backup list columns

Besides path, disk, date and size, the list shows:

- **Type** — whether the backup contains only the database, only files, or both
  (derived from the filename prefix the panel gives partial backups; backups created
  by `php artisan backup:run` count as full backups).
- **Cleanup** — when the backup leaves spatie's `keep_all_backups_for_days` window.
  Older backups show *In rotation*: the cleanup strategy then thins them out on its
  daily/weekly/monthly/yearly schedule, so there is no fixed deletion date.

## Downloads

The download action links the browser straight to the file instead of streaming it
through Livewire (which fails for large archives and in `spa()` mode). Disks that
support temporary URLs (e.g. S3) get a presigned URL; other disks are served by a
signed package route that requires a logged-in panel user. Both URL types expire
after 30 minutes — refresh the page if a long-idle download link has gone stale.

## Concurrent backups

While a backup is running, the create button is disabled and a server-side guard
rejects further runs. The lock clears when the job finishes or fails, and expires on
its own (after at least 30 minutes, or the configured timeout if longer) so a crashed
worker can never block the button permanently.

A restore takes the same kind of lock, and the two exclude each other: no restore
starts while a backup runs, and no backup starts while a restore runs — a dump taken
while the database is being replaced is worthless.

## Restoring a backup

**Restoring destroys data.** The archive replaces everything in the target
connection, and anything written since the backup was taken is gone.

The `Restore Backup` button uploads an archive and restores from it, via
[wnx/laravel-backup-restore](https://github.com/stefanzweifel/laravel-backup-restore).
Before it runs, the operator has to type a confirmation phrase (`overwrite
everything`); the modal also asks for the archive password when
`backup.backup.password` is set, and for the connection when
`backup.backup.source.databases` lists more than one — a restore covers one
connection per run.

The work happens on the queue, because a restore of any size outlives a web
request. The upload is parked on a disk of its own (`local` by default) rather
than on a backup destination, and is deleted as soon as the restore finishes,
succeed or fail — an uploaded archive is a full copy of the database:

```php
FilamentSpatieLaravelBackupPlugin::make()
    ->restoreUploadDisk('restore-uploads')
```

The app goes into maintenance mode for the duration and comes back on its own.
That also parks the queue workers — a worker checks for maintenance between jobs
and sleeps — so nothing writes while the database is being replaced. A worker
killed outright (OOM, SIGKILL) runs neither the cleanup nor `failed()`, and the
app stays down until someone runs `php artisan up`.

Ticking **Also restore the media files** empties the media disk
(`restoreMediaDisk()`, default `public`) and refills it from the archive.
Only that disk: the archive also holds the application itself, and replacing
running code with an older copy of it from a zip is not a thing to do behind a
button. A wrong password fails before the disk is touched.

Note that `backup:restore` needs the database CLI for the connection it restores
(`mysql`, `psql`, `sqlite3`) and `gunzip` available in the container.

**If the queue driver is `database` and you restore that same connection**, the
`jobs` table is replaced underneath the worker running the restore. Expect the
queue state to come back as it was in the backup.

## Customising who can access the page

You can customise who can access the `Backups` page by adding an `authorize` method to the plugin.
The method should return a boolean indicating whether the user is authorised to access the page.

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ...
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                     ->authorize(fn (): bool => auth()->user()->email === 'admin@example.com'),
            );
    }
}
```

## Upgrading

Please see [UPGRADE](UPGRADE.md) for details on how to upgrade 1.X to 2.0.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Shuvro Roy](https://github.com/shuvroroy)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
