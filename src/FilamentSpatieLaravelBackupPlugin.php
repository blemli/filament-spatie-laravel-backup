<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups;

class FilamentSpatieLaravelBackupPlugin implements Plugin
{
    use EvaluatesClosures;

    protected bool | Closure $authorizeUsing = true;

    protected bool | Closure | null $authorizeCreateUsing = null;

    protected bool | Closure | null $authorizeDownloadUsing = null;

    protected bool | Closure | null $authorizeDeleteUsing = null;

    protected string $page = Backups::class;

    protected ?string $queue = null;

    protected ?string $interval = '4s';

    protected bool $hasStatusListRecordsTable = true;

    protected ?int $timeout = null;

    protected Closure | string | \BackedEnum $navigationIcon = 'heroicon-o-cog';

    protected string | Closure | null $navigationLabel = null;

    protected Closure | string | null $navigationGroup = null;

    protected bool $navigationGroupSet = false;

    protected Closure | int $navigationSort = 1;

    public function register(Panel $panel): void
    {
        $panel->pages([$this->getPage()]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function authorize(bool | Closure $callback = true): static
    {
        $this->authorizeUsing = $callback;

        return $this;
    }

    public function isAuthorized(): bool
    {
        return $this->evaluate($this->authorizeUsing) === true;
    }

    public function authorizeCreateUsing(bool | Closure $callback): static
    {
        $this->authorizeCreateUsing = $callback;

        return $this;
    }

    public function isCreateAuthorized(): bool
    {
        return $this->isActionAuthorized($this->authorizeCreateUsing, 'create-backup');
    }

    public function authorizeDownloadUsing(bool | Closure $callback): static
    {
        $this->authorizeDownloadUsing = $callback;

        return $this;
    }

    public function isDownloadAuthorized(): bool
    {
        return $this->isActionAuthorized($this->authorizeDownloadUsing, 'download-backup');
    }

    public function authorizeDeleteUsing(bool | Closure $callback): static
    {
        $this->authorizeDeleteUsing = $callback;

        return $this;
    }

    public function isDeleteAuthorized(): bool
    {
        return $this->isActionAuthorized($this->authorizeDeleteUsing, 'delete-backup');
    }

    protected function isActionAuthorized(bool | Closure | null $callback, string $ability): bool
    {
        if ($callback === null) {
            return auth()->user()?->can($ability) ?? false;
        }

        return $this->evaluate($callback) === true;
    }

    public static function get(): static
    {
        /** @var static $instance */
        $instance = filament(app(static::class)->getId());

        return $instance;
    }

    public function getId(): string
    {
        return 'filament-spatie-backup';
    }

    public static function make(): static
    {
        return new static;
    }

    public function usingPage(string $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getPage(): string
    {
        return $this->page;
    }

    public function usingQueue(string $queue): static
    {
        $this->queue = $queue;

        return $this;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Set the Livewire polling interval (e.g. '4s', '750ms') used to refresh the
     * backup tables. Pass null to disable polling entirely.
     */
    public function usingPollingInterval(?string $interval): static
    {
        $this->interval = $interval;

        return $this;
    }

    /**
     * @deprecated Use `usingPollingInterval()` instead.
     */
    public function usingPolingInterval(string $interval): static
    {
        return $this->usingPollingInterval($interval);
    }

    public function getPollingInterval(): ?string
    {
        return $this->interval;
    }

    /**
     * @deprecated Use `getPollingInterval()` instead.
     */
    public function getPolingInterval(): ?string
    {
        return $this->getPollingInterval();
    }

    /**
     * The backup listings are cached for one polling interval, so each poll hits
     * the (potentially remote) disk at most once, no matter how many clients poll.
     */
    public function getCacheTtlSeconds(): int
    {
        if ($this->interval === null || preg_match('/^(\d+(?:\.\d+)?)\s*(ms|s)$/', trim($this->interval), $matches) !== 1) {
            return 4;
        }

        $seconds = $matches[2] === 'ms' ? ((float) $matches[1]) / 1000 : (float) $matches[1];

        return max((int) ceil($seconds), 1);
    }

    /**
     * Set the timeout (in seconds) used for the backup job. If set to 0, the job will never timeout.
     *
     * @see https://www.php.net/manual/en/function.set-time-limit.php
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Make it so that the backup job will never timeout.
     *
     * @see https://www.php.net/manual/en/function.set-time-limit.php
     */
    public function noTimeout(): static
    {
        return $this->timeout(0);
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function statusListRecordsTable(bool $condition = true): static
    {
        $this->hasStatusListRecordsTable = $condition;

        return $this;
    }

    public function hasStatusListRecordsTable(): bool
    {
        return $this->hasStatusListRecordsTable;
    }

    public function getHeading(): string
    {
        return __('filament-spatie-backup::backup.pages.backups.heading');
    }

    public function navigationGroup(string | Closure | null $navigationGroup): static
    {
        $this->navigationGroup = $navigationGroup;
        $this->navigationGroupSet = true;

        return $this;
    }

    public function getNavigationGroup(): ?string
    {
        $navigationGroup = $this->evaluate($this->navigationGroup);

        if ($navigationGroup === null && $this->navigationGroupSet === false) {
            return __('filament-spatie-backup::backup.pages.backups.navigation.group');
        }

        return $navigationGroup;
    }

    public function navigationSort(int | Closure $navigationSort): static
    {
        $this->navigationSort = $navigationSort;

        return $this;
    }

    public function getNavigationSort(): int
    {
        return $this->evaluate($this->navigationSort);
    }

    public function navigationIcon(string | \BackedEnum | Closure $navigationIcon): static
    {
        $this->navigationIcon = $navigationIcon;

        return $this;
    }

    public function getNavigationIcon(): ?string
    {
        $icon = $this->evaluate($this->navigationIcon);

        return $icon instanceof \BackedEnum ? $icon->value : $icon;
    }

    public function navigationLabel(string | Closure | null $navigationLabel): static
    {
        $this->navigationLabel = $navigationLabel;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return $this->evaluate($this->navigationLabel) ?? __('filament-spatie-backup::backup.pages.backups.navigation.label');
    }
}
