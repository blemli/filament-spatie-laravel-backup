<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;

class Backups extends Page
{
    protected string $view = 'filament-spatie-backup::pages.backups';

    public function getHeading(): string | Htmlable
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getHeading();
    }

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationSort();
    }

    public static function getNavigationIcon(): string | \BackedEnum | null
    {
        return FilamentSpatieLaravelBackupPlugin::get()->getNavigationIcon();
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('create_backup_all')
                    ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup'))
                    ->color('primary')
                    ->extraAttributes(['class' => 'fsb-split-main'])
                    ->disabled(fn (): bool => $this->isBackupRunning())
                    ->tooltip(fn (): ?string => $this->isBackupRunning() ? __('filament-spatie-backup::backup.pages.backups.messages.backup_running_tooltip') : null)
                    ->action(fn () => $this->createBackup(Option::ALL)),
                ActionGroup::make([
                    Action::make('create_backup_db')
                        ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup_db'))
                        ->disabled(fn (): bool => $this->isBackupRunning())
                        ->action(fn () => $this->createBackup(Option::ONLY_DB)),
                    Action::make('create_backup_files')
                        ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup_files'))
                        ->disabled(fn (): bool => $this->isBackupRunning())
                        ->action(fn () => $this->createBackup(Option::ONLY_FILES)),
                ])
                    ->tooltip(fn (): ?string => $this->isBackupRunning() ? __('filament-spatie-backup::backup.pages.backups.messages.backup_running_tooltip') : null)
                    ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup_options'))
                    ->hiddenLabel()
                    ->icon('heroicon-m-chevron-down')
                    ->color('primary')
                    ->extraAttributes(['class' => 'fsb-split-more']),
            ])
                ->buttonGroup()
                ->visible(fn (): bool => FilamentSpatieLaravelBackupPlugin::get()->isCreateAuthorized()),
        ];
    }

    public function isBackupRunning(): bool
    {
        return CreateBackupJob::isRunning();
    }

    protected function createBackup(Option $option): void
    {
        $plugin = FilamentSpatieLaravelBackupPlugin::get();

        abort_unless($plugin->isCreateAuthorized(), 403);

        if (CreateBackupJob::isRunning()) {
            Notification::make()
                ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_running'))
                ->warning()
                ->send();

            return;
        }

        CreateBackupJob::markAsRunning($plugin->getTimeout());

        $job = new CreateBackupJob($option, $plugin->getTimeout());

        if ($plugin->getQueue() !== null) {
            // afterResponse() would run the job inside the web process and ignore the
            // queue entirely, so only use it when no queue has been configured.
            dispatch($job)->onQueue($plugin->getQueue());
        } else {
            dispatch($job)->afterResponse();
        }

        Notification::make()
            ->title(__('filament-spatie-backup::backup.pages.backups.messages.backup_success'))
            ->success()
            ->send();
    }

    public function shouldDisplayStatusListRecords(): bool
    {
        return FilamentSpatieLaravelBackupPlugin::get()->hasStatusListRecordsTable();
    }

    public static function canAccess(): bool
    {
        return FilamentSpatieLaravelBackupPlugin::get()->isAuthorized();
    }
}
