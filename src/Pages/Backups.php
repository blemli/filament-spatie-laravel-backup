<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Pages;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use ShuvroRoy\FilamentSpatieLaravelBackup\Enums\Option;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;

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
            ->schema([
                FileUpload::make('archive')
                    ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.archive'))
                    ->required()
                    ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                    ->disk(fn (): string => FilamentSpatieLaravelBackupPlugin::get()->getRestoreUploadDisk())
                    ->directory('filament-spatie-backup-restore')
                    // A dump of the whole database — never reachable over a URL.
                    ->visibility('private'),

                Select::make('connection')
                    ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.connection'))
                    ->options(fn (): array => array_combine(
                        static::restorableConnections(),
                        static::restorableConnections(),
                    ))
                    ->default(fn (): ?string => static::restorableConnections()[0] ?? null)
                    ->required()
                    // One connection per run, so it only needs asking when the
                    // backup covers more than one.
                    ->visible(fn (): bool => count(static::restorableConnections()) > 1),

                TextInput::make('password')
                    ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.password'))
                    ->password()
                    ->revealable()
                    ->helperText(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.password_helper'))
                    ->visible(fn (): bool => filled(config('backup.backup.password'))),

                Toggle::make('reset')
                    ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.reset'))
                    ->helperText(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.reset_helper'))
                    ->default(true),

                TextInput::make('confirmation')
                    ->label(fn (): string => __('filament-spatie-backup::backup.pages.backups.restore_modal.fields.confirmation', [
                        'phrase' => static::restoreConfirmationPhrase(),
                    ]))
                    ->required()
                    ->autocomplete(false)
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                        if (trim((string) $value) !== static::restoreConfirmationPhrase()) {
                            $fail(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.confirmation_mismatch', [
                                'phrase' => static::restoreConfirmationPhrase(),
                            ]));
                        }
                    }),
            ])
            ->action(fn (array $data) => $this->restoreBackup($data));
    }

    /**
     * The connections the backup dumps, and therefore the only ones an archive
     * can hold a dump for.
     *
     * @return list<string>
     */
    public static function restorableConnections(): array
    {
        return array_values(array_filter((array) config('backup.backup.source.databases', [])));
    }

    public static function restoreConfirmationPhrase(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.restore_modal.confirmation_phrase');
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

    protected function restoreBackup(array $data): void
    {
        $plugin = FilamentSpatieLaravelBackupPlugin::get();

        abort_unless($plugin->isRestoreAuthorized(), 403);

        if (RestoreBackupJob::isRunning() || CreateBackupJob::isRunning()) {
            Notification::make()
                ->title(__('filament-spatie-backup::backup.pages.backups.messages.restore_blocked'))
                ->warning()
                ->send();

            return;
        }

        RestoreBackupJob::markAsRunning($plugin->getTimeout());

        $job = new RestoreBackupJob(
            disk: $plugin->getRestoreUploadDisk(),
            path: $data['archive'],
            databaseConnection: $data['connection'] ?? (static::restorableConnections()[0] ?? null),
            password: filled($data['password'] ?? null) ? $data['password'] : null,
            reset: (bool) ($data['reset'] ?? true),
            timeout: $plugin->getTimeout(),
        );

        if ($plugin->getQueue() !== null) {
            // afterResponse() would run the job inside the web process and ignore the
            // queue entirely, so only use it when no queue has been configured.
            dispatch($job)->onQueue($plugin->getQueue());
        } else {
            dispatch($job)->afterResponse();
        }

        Notification::make()
            ->title(__('filament-spatie-backup::backup.pages.backups.messages.restore_success'))
            ->success()
            ->send();
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
