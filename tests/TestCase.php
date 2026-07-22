<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use RyanChandler\BladeCaptureDirective\BladeCaptureDirectiveServiceProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupServiceProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\AdminPanelProvider;
use Spatie\Backup\BackupServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function getPackageProviders($app)
    {
        return [
            ActionsServiceProvider::class,
            BladeCaptureDirectiveServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            // Livewire must register after Filament's SupportServiceProvider (as it
            // does with real package discovery): Filament re-binds Livewire mechanisms,
            // and Livewire's own singleton instances have to win.
            LivewireServiceProvider::class,
            BackupServiceProvider::class,
            FilamentSpatieLaravelBackupServiceProvider::class,
            AdminPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        config()->set('database.default', 'testing');
        config()->set('backup.backup.name', 'test-app');
        config()->set('backup.backup.destination.disks', ['backups-disk']);
        config()->set('filesystems.disks.backups-disk', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/backups-disk'),
        ]);
    }
}
