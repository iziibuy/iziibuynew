<?php

declare(strict_types=1);

use App\Models\CmsMenuItem;
use App\Services\Cms\LegacyAdminMenuSynchronizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_menus') || ! Schema::hasTable('cms_menu_items')) {
            return;
        }

        app(LegacyAdminMenuSynchronizer::class)->sync();
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_menu_items')) {
            return;
        }

        CmsMenuItem::query()
            ->where('title', 'All Charges')
            ->where('route_name', 'filament.admin.pages.all-charges')
            ->delete();
    }
};
