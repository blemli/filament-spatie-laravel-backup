<x-filament-panels::page>
    <div x-data="{}" x-load-css="[@js(\Filament\Support\Facades\FilamentAsset::getStyleHref('filament-spatie-backup-styles', package: 'filament-spatie-backup'))]">
        <div class="fsb-flex fsb-flex-col fsb-gap-y-8">
            @if ($this->shouldDisplayStatusListRecords())
                <div class="fsb-mb-10">
                    @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationStatusListRecords::class)
                </div>
            @endif
            <div>
                @livewire(ShuvroRoy\FilamentSpatieLaravelBackup\Components\BackupDestinationListRecords::class)
            </div>
        </div>
    </div>
</x-filament-panels::page>
