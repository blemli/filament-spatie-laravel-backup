<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->plugin(FilamentSpatieLaravelBackupPlugin::make());
    }
}
