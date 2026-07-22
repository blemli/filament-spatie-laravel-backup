<?php

namespace ShuvroRoy\FilamentSpatieLaravelBackup\Http\Controllers;

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackup;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBackupController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = (string) $request->query('disk');
        $path = (string) $request->query('path');

        abort_unless($this->isAuthenticated(), 403);
        abort_unless(in_array($disk, FilamentSpatieLaravelBackup::getDisks(), true), 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->download($path);
    }

    // The download URL is not tied to one panel, so accept a login on any panel's guard.
    protected function isAuthenticated(): bool
    {
        return collect(Filament::getPanels())
            ->contains(fn (Panel $panel): bool => auth($panel->getAuthGuard())->check());
    }
}
