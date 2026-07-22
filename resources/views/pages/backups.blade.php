<x-filament-panels::page>
    {{-- While a backup is running, poll so the create button unlocks itself once the job finishes. --}}
    <div
        x-data="{}"
        x-load-css="[@js(\Filament\Support\Facades\FilamentAsset::getStyleHref('filament-spatie-backup-styles', package: 'filament-spatie-backup'))]"
        @if ($this->isBackupRunning() && ($pollingInterval = ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin::get()->getPolingInterval()))
            wire:poll.{{ $pollingInterval }}
        @endif
    >
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
