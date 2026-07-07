<?php

declare(strict_types=1);
use App\Providers\Filament\AdminPanelProvider;
use App\Support\SiteSettings;
use Filament\Facades\Filament;

if (! function_exists('setting')) {
    /**
     * Replaces the Voyager global `setting()` helper.
     * Checks config('settings.*') first, then falls back to the database.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        $databaseValue = SiteSettings::get($key);
        if ($databaseValue !== null && $databaseValue !== '') {
            return $databaseValue;
        }

        return config("settings.{$key}") ?? $default;
    }
}

if (! function_exists('filament_panel_url')) {
    /**
     * Absolute URL under the default Filament admin panel (see {@see AdminPanelProvider}).
     * Replaces legacy `route('voyager.*')` links after Voyager was removed.
     */
    function filament_panel_url(string $path = ''): string
    {
        $panel = Filament::getPanel('admin');
        $base = rtrim($panel->getUrl(), '/');
        $path = ltrim($path, '/');

        return $path === '' ? $base : "{$base}/{$path}";
    }
}
