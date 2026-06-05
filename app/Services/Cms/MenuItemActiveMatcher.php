<?php

declare(strict_types=1);

namespace App\Services\Cms;

use Filament\Resources\Resource;

use function Filament\Support\original_request;

final class MenuItemActiveMatcher
{
    /**
     * Query parameters that distinguish sibling menu items sharing the same path.
     *
     * @var list<string>
     */
    private const DISTINGUISHING_QUERY_PARAMETERS = [
        'active',
        'demo',
    ];

    public function matchesUrl(string $url): bool
    {
        $parsed = parse_url($url);
        $targetPath = trim($parsed['path'] ?? '', '/');
        $currentPath = original_request()->path();

        $pathMatches = $currentPath === $targetPath
            || str_starts_with($currentPath, $targetPath.'/');

        if (! $pathMatches) {
            return false;
        }

        $targetQuery = [];

        if (filled($parsed['query'] ?? null)) {
            parse_str($parsed['query'], $targetQuery);
        }

        if ($targetQuery !== []) {
            foreach ($targetQuery as $key => $value) {
                if ((string) original_request()->query($key, '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        }

        foreach (self::DISTINGUISHING_QUERY_PARAMETERS as $parameter) {
            if (original_request()->has($parameter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    public function matchesResource(string $resourceClass, string $url): bool
    {
        if (! is_subclass_of($resourceClass, Resource::class)) {
            return false;
        }

        if (! original_request()->routeIs($resourceClass::getNavigationItemActiveRoutePattern())) {
            return false;
        }

        return $this->matchesUrl($url);
    }
}
