<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Components;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination as SpatieBackupDestination;

class BackupDestinationListRecords extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    /**
     * @var array<int|string, array<string, string>|string>
     */
    protected $queryString = [
        'tableSortColumn',
        'tableSortDirection',
        'tableSearchQuery' => ['except' => ''],
    ];

    public function render(): View
    {
        return view('filament-spatie-backup::components.backup-destination-list-records');
    }

    public function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            // The rows come from a cached listing of a handful of files, so a
            // filter can apply the moment it is picked — nothing to batch up.
            ->deferFilters(false)
            ->records(
                function (?string $sortColumn, ?string $sortDirection, ?string $search, ?array $filters) {
                    $ttl = FilamentSpatieLaravelBackupPlugin::get()->getCacheTtlSeconds();

                    $data = [];

                    foreach (FilamentSpatieLaravelBackup::getDisks() as $disk) {
                        $data = array_merge($data, FilamentSpatieLaravelBackup::getBackupDestinationData($disk, $ttl));
                    }

                    $selectedDisk = $filters['disk']['value'] ?? null;
                    $selectedType = $filters['type']['value'] ?? null;
                    $selectedCleanup = $filters['cleanup']['value'] ?? null;

                    return collect($data)
                        ->when(
                            filled($selectedDisk),
                            fn (Collection $data): Collection => $data->where('disk', $selectedDisk),
                        )
                        ->when(
                            filled($selectedType),
                            fn (Collection $data): Collection => $data->where('type', $selectedType),
                        )
                        ->when(
                            filled($selectedCleanup),
                            fn (Collection $data): Collection => $data->filter(
                                fn (array $record): bool => ($record['cleanup_at'] <= now()->getTimestamp()) === ($selectedCleanup === 'in_rotation'),
                            ),
                        )
                        ->when(
                            filled($sortColumn),
                            fn (Collection $data): Collection => $data->sortBy(
                                $sortColumn,
                                SORT_NATURAL,
                                $sortDirection === 'desc',
                            ),
                        )
                        ->when(
                            filled($search),
                            fn (Collection $data): Collection => $data->filter(
                                fn (array $record): bool => Str::contains(
                                    Str::lower($record['path'] . $record['disk'] . $record['date']),
                                    Str::lower($search),
                                ),
                            ),
                        );
                }
            )
            ->columns([
                TextColumn::make('path')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.path'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.disk'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.type'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'all' ? 'primary' : 'gray')
                    ->formatStateUsing(fn (string $state): string => __('filament-spatie-backup::backup.components.backup_destination_list.table.types.' . $state))
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.date'))
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.size'))
                    ->badge(),
                TextColumn::make('cleanup_at')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.cleanup_in'))
                    ->formatStateUsing(fn (int $state): string => $state <= now()->getTimestamp()
                        ? __('filament-spatie-backup::backup.components.backup_destination_list.table.cleanup.in_rotation')
                        : Carbon::createFromTimestamp($state)->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('disk')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.filters.disk'))
                    ->options(FilamentSpatieLaravelBackup::getFilterDisks())
                    // With one destination the column holds the same value on every
                    // row, so the filter could only ever narrow to everything.
                    ->visible(fn (): bool => count(FilamentSpatieLaravelBackup::getDisks()) > 1),
                SelectFilter::make('type')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.type'))
                    ->options([
                        'db' => __('filament-spatie-backup::backup.components.backup_destination_list.table.types.db'),
                        'files' => __('filament-spatie-backup::backup.components.backup_destination_list.table.types.files'),
                        'all' => __('filament-spatie-backup::backup.components.backup_destination_list.table.types.all'),
                    ]),
                SelectFilter::make('cleanup')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.fields.cleanup_in'))
                    ->options([
                        'retained' => __('filament-spatie-backup::backup.components.backup_destination_list.table.cleanup.retained'),
                        'in_rotation' => __('filament-spatie-backup::backup.components.backup_destination_list.table.cleanup.in_rotation'),
                    ]),
            ])
            ->recordActions([
                Action::make('download')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (): bool => FilamentSpatieLaravelBackupPlugin::get()->isDownloadAuthorized())
                    // A plain link instead of a Livewire action: streaming the file
                    // through Livewire fails for large backups, especially in SPA mode.
                    ->url(fn (array $record): string => FilamentSpatieLaravelBackup::getDownloadUrl($record['disk'], $record['path']))
                    ->openUrlInNewTab(),

                Action::make('delete')
                    ->label(__('filament-spatie-backup::backup.components.backup_destination_list.table.actions.delete'))
                    ->icon('heroicon-o-trash')
                    ->visible(fn (): bool => FilamentSpatieLaravelBackupPlugin::get()->isDeleteAuthorized())
                    ->requiresConfirmation()
                    ->color('danger')
                    ->modalIcon('heroicon-o-trash')
                    ->action(function (array $record) {
                        SpatieBackupDestination::create($record['disk'], config('backup.backup.name'))
                            ->backups()
                            ->first(function (Backup $backup) use ($record) {
                                return $backup->path() === $record['path'];
                            })
                            ->delete();

                        Notification::make()
                            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_delete_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                // ...
            ]);
    }

    #[Computed]
    public function interval(): ?string
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getPollingInterval();
    }
}
