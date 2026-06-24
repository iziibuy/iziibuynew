<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\VoyagerPermissions;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Adds new-app tables to a legacy iziibuy database without altering existing legacy tables,
 * then marks skipped migrations as run so a full {@see migrate} will not drop Voyager tables
 * or change legacy column definitions.
 */
class MigrateMissingAppTablesCommand extends Command
{
    protected $signature = 'iziibuy:migrate-missing-tables
                            {--skip-cms-import : Do not import Voyager settings/menus into CMS tables}
                            {--skip-permission-pivots : Do not copy permission_role → role_has_permissions}
                            {--dry-run : Show missing tables and pending migrations only}';

    protected $description = 'Create missing new-app tables on the legacy database without altering existing tables';

    private const MISSING_TABLES_MIGRATION = '2026_06_24_125152_create_missing_app_tables_for_legacy_database';

    /** @var list<string> */
    private const MIGRATIONS_TO_SKIP = [
        '0001_01_01_000000_create_users_table',
        '0001_01_01_000001_create_cache_table',
        '0001_01_01_000002_create_jobs_table',
        '2023_04_21_000000_create_service_store_table',
        '2023_09_21_084150_create_payment_method_accesses_table',
        '2026_05_10_105147_create_permission_tables',
        '2026_05_10_120000_create_posts_and_pages_tables',
        '2026_05_10_130000_drop_legacy_voyager_metadata_tables',
        '2026_05_10_180000_create_site_cms_and_settings_tables',
        '2026_05_12_131007_add_details_to_site_settings_table',
        '2026_05_13_144035_align_shops_and_legacy_nullable_columns_with_legacy_iziibuy_database',
        '2026_05_16_115656_add_menu_context_and_link_fields_to_cms_menus',
        '2026_06_08_210637_add_elavon_plan_and_subscription_ids_to_shops_table',
    ];

    public function handle(Migrator $migrator): int
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $this->info("Target database: `{$database}`");

        $missing = $this->missingAppTables();
        $this->newLine();
        $this->table(['Missing table'], array_map(fn (string $t): array => [$t], $missing));

        $pendingSkip = array_values(array_filter(
            self::MIGRATIONS_TO_SKIP,
            fn (string $name): bool => ! $this->migrationHasRun($name)
        ));

        if ($pendingSkip !== []) {
            $this->newLine();
            $this->warn('These migrations will be marked as run without executing (legacy-safe):');
            foreach ($pendingSkip as $name) {
                $this->line("  • {$name}");
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        if ($missing === [] && $this->migrationHasRun(self::MISSING_TABLES_MIGRATION)) {
            $this->info('All new-app tables already exist.');
        } else {
            $exit = Artisan::call('migrate', [
                '--path' => 'database/migrations/'.self::MISSING_TABLES_MIGRATION.'.php',
                '--force' => true,
            ], $this->output);

            if ($exit !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        $this->markSkippedMigrationsAsRun($migrator, $pendingSkip);

        if (! $this->option('skip-permission-pivots')) {
            $this->syncLegacyPermissionPivots();
            $this->syncUsersToModelHasRoles();
        }

        if (! $this->option('skip-cms-import')) {
            $this->importLegacyCms();
        }

        if (! $this->migrationHasRun('2026_05_16_121500_sync_admin_cms_menu_for_filament')) {
            Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_05_16_121500_sync_admin_cms_menu_for_filament.php',
                '--force' => true,
            ], $this->output);
        } else {
            Artisan::call('cms:sync-admin-menu', [], $this->output);
        }

        $this->newLine();
        $this->info('Legacy database is ready with new-app tables only — existing tables were not altered.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function missingAppTables(): array
    {
        $expected = [
            'cache',
            'cache_locks',
            'job_batches',
            'password_reset_tokens',
            'sessions',
            'post_categories',
            'site_settings',
            'cms_menus',
            'cms_menu_items',
            'faqs',
            'site_plugins',
            'payment_badges',
            'model_has_permissions',
            'model_has_roles',
            'role_has_permissions',
        ];

        return array_values(array_filter($expected, fn (string $table): bool => ! Schema::hasTable($table)));
    }

    /**
     * @param  list<string>  $migrations
     */
    private function markSkippedMigrationsAsRun(Migrator $migrator, array $migrations): void
    {
        if ($migrations === []) {
            return;
        }

        $batch = ((int) DB::table('migrations')->max('batch')) + 1;
        $repository = $migrator->getRepository();

        foreach ($migrations as $migration) {
            if ($this->migrationHasRun($migration)) {
                continue;
            }

            $repository->log($migration, $batch);
            $this->line("Marked as run (skipped): <fg=gray>{$migration}</>");
        }
    }

    private function migrationHasRun(string $migration): bool
    {
        if (! Schema::hasTable('migrations')) {
            return false;
        }

        return DB::table('migrations')->where('migration', $migration)->exists();
    }

    private function syncLegacyPermissionPivots(): void
    {
        if (! Schema::hasTable('permission_role') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $linked = 0;
        DB::table('permission_role')
            ->orderBy('permission_id')
            ->lazy(200)
            ->each(function (object $row) use (&$linked): void {
                $inserted = DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $row->permission_id,
                    'role_id' => $row->role_id,
                ]);
                if ($inserted > 0) {
                    $linked++;
                }
            });

        $this->info("Role–permission pivots copied from permission_role: {$linked} new row(s).");
        VoyagerPermissions::forgetCachedPermissions();
    }

    private function syncUsersToModelHasRoles(): void
    {
        if (! Schema::hasTable('model_has_roles')) {
            return;
        }

        $this->info('Syncing model_has_roles from users.role_id…');
        $updated = 0;

        User::query()
            ->whereNotNull('role_id')
            ->chunkById(200, function ($users) use (&$updated): void {
                foreach ($users as $user) {
                    $inserted = DB::table('model_has_roles')->insertOrIgnore([
                        'role_id' => $user->role_id,
                        'model_type' => User::class,
                        'model_id' => $user->id,
                    ]);
                    if ($inserted > 0) {
                        $updated++;
                    }
                }
            });

        $this->info("Users linked in model_has_roles: {$updated} new row(s).");
        VoyagerPermissions::forgetCachedPermissions();
    }

    private function importLegacyCms(): void
    {
        $source = 'legacy_iziibuy';
        if (! config("database.connections.{$source}")) {
            $this->warn('No legacy_iziibuy connection — skipping CMS import.');

            return;
        }

        if ((string) config("database.connections.{$source}.database") !== (string) config('database.connections.'.config('database.default').'.database')) {
            $this->warn('Legacy connection points to a different database — run iziibuy:import-legacy-cms manually if needed.');

            return;
        }

        try {
            Artisan::call('iziibuy:import-legacy-cms', [
                '--source' => $source,
                '--with-post-categories-from-categories' => true,
            ], $this->output);
        } catch (Throwable $e) {
            $this->warn('CMS import failed: '.$e->getMessage());
        }
    }
}
