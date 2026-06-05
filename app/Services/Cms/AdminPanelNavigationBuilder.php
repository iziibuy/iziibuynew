<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Enums\MenuContext;
use App\Enums\MenuLinkType;
use App\Models\CmsMenu;
use App\Models\CmsMenuItem;
use BackedEnum;
use Closure;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

use function Filament\Support\original_request;

final class AdminPanelNavigationBuilder
{
    public function __construct(
        private readonly MenuTreeBuilder $treeBuilder,
        private readonly MenuUrlResolver $urlResolver,
        private readonly VoyagerIconMapper $iconMapper,
        private readonly MenuItemActiveMatcher $activeMatcher,
    ) {}

    /**
     * When no custom admin menu replaces navigation, return false to use Filament defaults.
     */
    public function resolve(): NavigationBuilder|bool
    {
        $menu = CmsMenu::query()
            ->forContext(MenuContext::Admin)
            ->active()
            ->where('replaces_panel_navigation', true)
            ->orderBy('id')
            ->first();

        if (! $menu) {
            return false;
        }

        return $this->build($menu);
    }

    public function build(CmsMenu $menu): NavigationBuilder
    {
        $tree = $this->treeBuilder->build($menu);
        $builder = new NavigationBuilder;

        $tree
            ->sortBy(fn (array $node): int => $node['item']->sort_order)
            ->each(function (array $node) use ($builder): void {
                if ($node['children']->isNotEmpty()) {
                    $group = $this->makeNavigationGroup($node);

                    if ($group !== null) {
                        $builder->group($group);
                    }

                    return;
                }

                $item = $this->makeNavigationItem($node);

                if ($item === null) {
                    return;
                }

                $builder->group(
                    NavigationGroup::make()->items([$item]),
                );
            });

        return $builder;
    }

    /**
     * Register extra navigation links from active admin menus that do not replace the sidebar.
     */
    public function registerSupplementaryItems(): void
    {
        $menus = CmsMenu::query()
            ->forContext(MenuContext::Admin)
            ->active()
            ->where('replaces_panel_navigation', false)
            ->get();

        foreach ($menus as $menu) {
            $tree = $this->treeBuilder->build($menu);

            foreach ($this->flattenTree($tree) as $node) {
                if ($node['children']->isNotEmpty()) {
                    continue;
                }

                $item = $this->makeNavigationItem($node);

                if ($item !== null) {
                    Filament::registerNavigationItems([$item]);
                }
            }
        }
    }

    /**
     * @param  array{item: CmsMenuItem, url: ?string, children: Collection}  $node
     */
    private function makeNavigationGroup(array $node): ?NavigationGroup
    {
        $items = $node['children']
            ->sortBy(fn (array $child): int => $child['item']->sort_order)
            ->map(fn (array $child): ?NavigationItem => $this->makeNavigationItem($child))
            ->filter()
            ->values()
            ->all();

        if ($items === []) {
            return null;
        }

        /** @var CmsMenuItem $record */
        $record = $node['item'];

        return NavigationGroup::make($record->title)
            ->icon($this->resolveIcon($record) ?? Heroicon::OutlinedFolder)
            ->items($items)
            ->collapsible();
    }

    /**
     * @param  array{item: CmsMenuItem, url: ?string, children: Collection}  $node
     */
    private function makeNavigationItem(array $node): ?NavigationItem
    {
        /** @var CmsMenuItem $record */
        $record = $node['item'];
        $url = $node['url'] ?? $this->urlResolver->resolve($record);

        if (blank($url)) {
            return null;
        }

        $label = $record->title;
        $icon = $this->resolveIcon($record);
        $activeIcon = null;
        $isActiveWhen = $this->resolveIsActiveWhen($record, $url);

        if ($record->link_type === MenuLinkType::Resource && filled($record->resource_class)) {
            $resourceClass = $record->resource_class;

            if (class_exists($resourceClass) && is_subclass_of($resourceClass, Resource::class)) {
                $label = $record->title ?: $resourceClass::getNavigationLabel();
                $icon ??= $resourceClass::getNavigationIcon();
                $activeIcon = $resourceClass::getActiveNavigationIcon();
                $isActiveWhen = fn (): bool => $this->activeMatcher->matchesResource($resourceClass, $url);
            }
        }

        $navigationItem = NavigationItem::make($label)
            ->sort($record->sort_order)
            ->url($url, $record->open_new_tab)
            ->isActiveWhen($isActiveWhen);

        if ($icon !== null) {
            $navigationItem->icon($icon);
        }

        if ($activeIcon !== null) {
            $navigationItem->activeIcon($activeIcon);
        }

        if (filled($record->navigation_group)) {
            $navigationItem->group($record->navigation_group);
        }

        return $navigationItem;
    }

    private function resolveIsActiveWhen(CmsMenuItem $record, string $url): Closure
    {
        if ($record->link_type === MenuLinkType::Route && filled($record->route_name)) {
            return fn (): bool => original_request()->routeIs($record->route_name);
        }

        return fn (): bool => $this->activeMatcher->matchesUrl($url);
    }

    private function resolveIcon(CmsMenuItem $item): string|BackedEnum|null
    {
        return $this->iconMapper->map($item->icon);
    }

    /**
     * @param  Collection<int, array{item: CmsMenuItem, url: ?string, children: Collection}>  $tree
     * @return Collection<int, array{item: CmsMenuItem, url: ?string, children: Collection}>
     */
    private function flattenTree(Collection $tree): Collection
    {
        $flat = collect();

        foreach ($tree as $node) {
            $flat->push($node);

            if ($node['children']->isNotEmpty()) {
                $flat = $flat->merge($this->flattenTree($node['children']));
            }
        }

        return $flat;
    }
}
