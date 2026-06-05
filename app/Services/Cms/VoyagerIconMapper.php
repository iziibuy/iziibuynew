<?php

declare(strict_types=1);

namespace App\Services\Cms;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

final class VoyagerIconMapper
{
    /**
     * @var array<string, Heroicon>
     */
    private const MAP = [
        'voyager-boat' => Heroicon::OutlinedHome,
        'voyager-person' => Heroicon::OutlinedUser,
        'voyager-list' => Heroicon::OutlinedListBullet,
        'voyager-settings' => Heroicon::OutlinedCog6Tooth,
        'voyager-file-text' => Heroicon::OutlinedDocumentText,
        'voyager-mail' => Heroicon::OutlinedEnvelope,
        'voyager-bag' => Heroicon::OutlinedShoppingBag,
        'voyager-basket' => Heroicon::OutlinedShoppingCart,
        'voyager-dollar' => Heroicon::OutlinedCurrencyDollar,
        'voyager-gift' => Heroicon::OutlinedGift,
        'voyager-people' => Heroicon::OutlinedUsers,
        'voyager-images' => Heroicon::OutlinedPhoto,
        'voyager-pie-graph' => Heroicon::OutlinedChartPie,
        'voyager-power' => Heroicon::OutlinedBolt,
        'voyager-credit-card' => Heroicon::OutlinedCreditCard,
        'voyager-credit-cards' => Heroicon::OutlinedCreditCard,
        'voyager-info-circled' => Heroicon::OutlinedInformationCircle,
        'voyager-archive' => Heroicon::OutlinedArchiveBox,
        'voyager-star-two' => Heroicon::OutlinedStar,
        'voyager-bookmark' => Heroicon::OutlinedBookmark,
        'voyager-study' => Heroicon::OutlinedAcademicCap,
        'voyager-character' => Heroicon::OutlinedUserCircle,
        'voyager-github' => Heroicon::OutlinedCodeBracket,
        'voyager-medal-rank-star' => Heroicon::OutlinedTrophy,
        'voyager-pirate' => Heroicon::OutlinedFlag,
        'voyager-tools' => Heroicon::OutlinedWrenchScrewdriver,
        'voyager-lock' => Heroicon::OutlinedLockClosed,
        'voyager-data' => Heroicon::OutlinedCircleStack,
        'voyager-compass' => Heroicon::OutlinedMap,
        'voyager-bread' => Heroicon::OutlinedTableCells,
        'voyager-youtube' => Heroicon::OutlinedPlayCircle,
    ];

    public function map(?string $icon): string|BackedEnum|null
    {
        if (blank($icon)) {
            return null;
        }

        if (class_exists($icon) && is_subclass_of($icon, BackedEnum::class)) {
            return $icon;
        }

        $normalized = strtolower(trim($icon));

        if (isset(self::MAP[$normalized])) {
            return self::MAP[$normalized];
        }

        if (str_starts_with($normalized, 'voyager-')) {
            return null;
        }

        return null;
    }
}
