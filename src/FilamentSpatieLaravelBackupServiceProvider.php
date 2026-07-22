<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords;
use ShuvroRoy\FilamentSpatieLaravelBackup\Http\Controllers\DownloadBackupController;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentSpatieLaravelBackupServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-spatie-backup')
            ->hasTranslations()
            ->hasViews();
    }

    public function packageBooted(): void
    {
        Livewire::component('backup-destination-list-records', BackupDestinationListRecords::class);
        Livewire::component('backup-destination-status-list-records', BackupDestinationStatusListRecords::class);

        FilamentAsset::register([
            Css::make('filament-spatie-backup-styles', __DIR__ . '/../resources/dist/plugin.css')->loadedOnRequest(),
        ], package: 'filament-spatie-backup');

        // Signed download route so backups stream directly to the browser instead of
        // through Livewire, which chokes on large files (especially in SPA mode).
        Route::get('/filament-spatie-backup/download', DownloadBackupController::class)
            ->name('filament-spatie-backup.download')
            ->middleware(['web', 'signed']);
    }
}
