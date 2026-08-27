<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Schemas\RestoreForm;

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
            Action::make('create_backup')
                ->label(__('filament-spatie-backup::backup.pages.backups.actions.create_backup'))
                ->color('primary')
                ->button()
                ->visible(fn (): bool => FilamentSpatieLaravelBackupPlugin::get()->isCreateAuthorized())
                // Also blocked during a restore: the database changes underneath
                // a running dump, and the archive that comes out is worthless.
                ->disabled(fn (): bool => $this->isBackupRunning() || $this->isRestoreRunning())
                ->tooltip(fn (): ?string => $this->isBackupRunning() ? __('filament-spatie-backup::backup.pages.backups.messages.backup_running_tooltip') : null)
                ->modalHeading(__('filament-spatie-backup::backup.pages.backups.modal.label'))
                ->modalWidth('lg')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalFooterActions([
                    Action::make('create_backup_db')
                        ->label(__('filament-spatie-backup::backup.pages.backups.modal.buttons.only_db'))
                        ->color('gray')
                        ->cancelParentActions()
                        ->action(fn () => $this->createBackup(Option::ONLY_DB)),
                    Action::make('create_backup_files')
                        ->label(__('filament-spatie-backup::backup.pages.backups.modal.buttons.only_files'))
                        ->color('gray')
                        ->cancelParentActions()
                        ->action(fn () => $this->createBackup(Option::ONLY_FILES)),
                    Action::make('create_backup_all')
                        ->label(__('filament-spatie-backup::backup.pages.backups.modal.buttons.db_and_files'))
                        ->color('primary')
                        ->cancelParentActions()
                        ->action(fn () => $this->createBackup(Option::ALL)),
                ]),

            $this->restoreBackupAction(),
        ];
    }

    /**
     * Upload an archive and restore the database from it. Everything currently
     * in the target connection is gone afterwards, so the operator types a
     * phrase rather than clicking through a confirmation they have stopped
     * reading, and the work happens on the queue — a restore outlives a request.
     */
    protected function restoreBackupAction(): Action
    {
        return Action::make('restore_backup')
            ->label(__('filament-spatie-backup::backup.pages.backups.actions.restore_backup'))
            ->color('danger')
            ->button()
            ->outlined()
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => FilamentSpatieLaravelBackupPlugin::get()->isRestoreAuthorized())
            ->disabled(fn (): bool => $this->isBackupRunning() || $this->isRestoreRunning())
            ->tooltip(fn (): ?string => $this->isRestoreRunning() ? __('filament-spatie-backup::backup.pages.backups.messages.restore_running_tooltip') : null)
            ->modalHeading(__('filament-spatie-backup::backup.pages.backups.restore_modal.label'))
            ->modalDescription(__('filament-spatie-backup::backup.pages.backups.restore_modal.description'))
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalIconColor('danger')
            ->modalSubmitActionLabel(__('filament-spatie-backup::backup.pages.backups.restore_modal.buttons.restore'))
            ->schema(RestoreForm::fields())
            ->action(fn (array $data) => $this->restoreBackup($data));
    }

    /**
     * @return list<string>
     */
    public static function restorableConnections(): array
    {
        return RestoreForm::connections();
    }

    public static function restoreConfirmationPhrase(): string
    {
        return RestoreForm::confirmationPhrase();
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

        $job = new CreateBackupJob($option, $plugin->getTimeout(), $plugin->getFullBackupCommand());

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

    protected function restoreBackup(array $data): void
    {
        RestoreForm::dispatch(
            disk: FilamentSpatieLaravelBackupPlugin::get()->getRestoreUploadDisk(),
            path: $data['archive'],
            data: $data,
            discardAfterwards: true,
        );
    }

    public function isRestoreRunning(): bool
    {
        return RestoreBackupJob::isRunning();
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
