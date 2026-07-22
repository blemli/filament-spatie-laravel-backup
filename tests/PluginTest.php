<?php

use Illuminate\Support\Facades\Gate;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\Tests\Fixtures\User;

it('has sensible defaults', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->getPollingInterval())->toBe('4s')
        ->and($plugin->getCacheTtlSeconds())->toBe(4)
        ->and($plugin->getQueue())->toBeNull()
        ->and($plugin->getTimeout())->toBeNull()
        ->and($plugin->hasStatusListRecordsTable())->toBeTrue();
});

it('derives the cache ttl from the polling interval', function (?string $interval, int $ttl) {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    if ($interval !== 'default') {
        $plugin->usingPollingInterval($interval);
    }

    expect($plugin->getCacheTtlSeconds())->toBe($ttl);
})->with([
    'seconds' => ['30s', 30],
    'milliseconds round up' => ['750ms', 1],
    'disabled polling' => [null, 4],
    'unparseable' => ['keep-alive', 4],
]);

it('can disable polling', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()->usingPollingInterval(null);

    expect($plugin->getPollingInterval())->toBeNull();
});

it('still supports the misspelled polling methods', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()->usingPolingInterval('10s');

    expect($plugin->getPolingInterval())->toBe('10s')
        ->and($plugin->getPollingInterval())->toBe('10s');
});

it('denies actions to guests by default', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->isCreateAuthorized())->toBeFalse()
        ->and($plugin->isDownloadAuthorized())->toBeFalse()
        ->and($plugin->isDeleteAuthorized())->toBeFalse();
});

it('authorizes actions through gates by default', function () {
    Gate::define('create-backup', fn (User $user) => true);
    Gate::define('download-backup', fn (User $user) => false);

    $this->actingAs(new User);

    $plugin = FilamentSpatieLaravelBackupPlugin::make();

    expect($plugin->isCreateAuthorized())->toBeTrue()
        ->and($plugin->isDownloadAuthorized())->toBeFalse()
        ->and($plugin->isDeleteAuthorized())->toBeFalse();
});

it('lets closures override the gate checks', function () {
    $plugin = FilamentSpatieLaravelBackupPlugin::make()
        ->authorizeCreateUsing(true)
        ->authorizeDownloadUsing(fn (): bool => true)
        ->authorizeDeleteUsing(false);

    expect($plugin->isCreateAuthorized())->toBeTrue()
        ->and($plugin->isDownloadAuthorized())->toBeTrue()
        ->and($plugin->isDeleteAuthorized())->toBeFalse();
});
