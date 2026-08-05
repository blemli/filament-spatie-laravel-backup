<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Schemas;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\CreateBackupJob;
use ShuvroRoy\FilamentSpatieLaravelBackup\Jobs\RestoreBackupJob;

/**
 * The restore modal, shared by the two ways in: uploading an archive from
 * elsewhere, and putting back one that is already on a backup destination.
 * Only the source differs, so only the upload field is optional here.
 */
class RestoreForm
{
    /**
     * The connections the backup dumps, and therefore the only ones an archive
     * can hold a dump for.
     *
     * @return list<string>
     */
    public static function connections(): array
    {
        return array_values(array_filter((array) config('backup.backup.source.databases', [])));
    }

    public static function confirmationPhrase(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.restore_modal.confirmation_phrase');
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(bool $withUpload = true): array
    {
        return array_values(array_filter([
            $withUpload ? FileUpload::make('archive')
                ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.archive'))
                ->required()
                ->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                ->disk(fn (): string => FilamentSpatieLaravelBackupPlugin::get()->getRestoreUploadDisk())
                ->directory('filament-spatie-backup-restore')
                // A dump of the whole database — never reachable over a URL.
                ->visibility('private') : null,

            Select::make('connections')
                ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.connections'))
                ->multiple()
                ->options(fn (): array => array_combine(static::connections(), static::connections()))
                // Everything the archive holds, because that is the usual intent;
                // deselect to put a single database back.
                ->default(fn (): array => static::connections())
                ->required()
                // Only worth asking when the backup covers more than one.
                ->visible(fn (): bool => count(static::connections()) > 1),

            TextInput::make('password')
                ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.password'))
                ->password()
                ->revealable()
                // Left empty the configured password is used. Deliberately not
                // prefilled: that would ship the secret to the browser every time
                // the modal opens, and it is only worth typing when the archive
                // predates a password change.
                ->placeholder(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.password_placeholder'))
                ->helperText(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.password_helper'))
                ->visible(fn (): bool => filled(config('backup.backup.password'))),

            Toggle::make('reset')
                ->label(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.reset'))
                ->helperText(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.reset_helper'))
                ->default(true),

            TextInput::make('confirmation')
                ->label(fn (): string => __('filament-spatie-backup::backup.pages.backups.restore_modal.fields.confirmation', [
                    'phrase' => static::confirmationPhrase(),
                ]))
                ->required()
                ->autocomplete(false)
                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                    if (trim((string) $value) !== static::confirmationPhrase()) {
                        $fail(__('filament-spatie-backup::backup.pages.backups.restore_modal.fields.confirmation_mismatch', [
                            'phrase' => static::confirmationPhrase(),
                        ]));
                    }
                }),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function dispatch(string $disk, string $path, array $data, bool $discardAfterwards): void
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
            disk: $disk,
            path: $path,
            databaseConnections: array_values((array) ($data['connections'] ?? static::connections())),
            // Empty means the archive still carries the configured password.
            password: filled($data['password'] ?? null) ? $data['password'] : config('backup.backup.password'),
            reset: (bool) ($data['reset'] ?? true),
            discardAfterwards: $discardAfterwards,
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
}
