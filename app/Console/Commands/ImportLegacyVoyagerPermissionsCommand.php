<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\VoyagerPermissions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

/**
 * Imports Voyager {@code permissions} and {@code permission_role} into Spatie's tables (same {@code permissions}
 * / {@code role_has_permissions} names as Laravel).
 */
class ImportLegacyVoyagerPermissionsCommand extends Command
{
    protected $signature = 'iziibuy:import-voyager-permissions
                            {--source=legacy_iziibuy : Legacy database connection}
                            {--sync-users : Sync model_has_roles from users.role_id for all users}
                            {--dry-run : Show counts only}';

    protected $description = 'Import Voyager permissions and role_permission pivots into Spatie (web guard)';

    public function handle(): int
    {
        $source = (string) $this->option('source');
        $dry = (bool) $this->option('dry-run');

        if (! config("database.connections.$source")) {
            $this->error("Unknown connection [{$source}].");

            return self::FAILURE;
        }

        if (config("database.connections.$source.driver") !== 'mysql') {
            $this->error('This command currently requires MySQL for the source connection.');

            return self::FAILURE;
        }

        try {
            DB::connection($source)->getPdo();
        } catch (Throwable $e) {
            $this->error('Source DB connection failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! Schema::connection($source)->hasTable('permissions')) {
            $this->warn('Source has no `permissions` table — nothing to import.');

            return self::SUCCESS;
        }

        $permRows = DB::connection($source)->table('permissions')->orderBy('id')->get();
        $this->info('Legacy permissions rows: '.$permRows->count());

        $hasPivot = Schema::connection($source)->hasTable('permission_role');
        $pivotCount = $hasPivot ? (int) DB::connection($source)->table('permission_role')->count() : 0;
        $this->info('Legacy permission_role rows: '.$pivotCount);

        if ($dry) {
            $this->info('Dry run — no changes.');

            return self::SUCCESS;
        }

        $keyColumn = $this->detectPermissionKeyColumn($source);
        if ($keyColumn === null) {
            $this->error('Could not find a permission key column (tried: key, name).');

            return self::FAILURE;
        }

        $importedPerms = 0;
        foreach ($permRows as $row) {
            $row = (array) $row;
            $name = isset($row[$keyColumn]) ? trim((string) $row[$keyColumn]) : '';
            if ($name === '') {
                continue;
            }

            Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $importedPerms++;
        }

        $this->info("Permissions ensured in Spatie: {$importedPerms}");

        Role::query()
            ->where(fn ($q) => $q->whereNull('guard_name')->orWhere('guard_name', ''))
            ->update(['guard_name' => 'web']);

        Permission::query()
            ->where(fn ($q) => $q->whereNull('guard_name')->orWhere('guard_name', ''))
            ->update(['guard_name' => 'web']);

        if ($hasPivot && $pivotCount > 0) {
            $legacyPermissions = DB::connection($source)->table('permissions')->pluck($keyColumn, 'id');
            $pivots = DB::connection($source)->table('permission_role')->get();
            $linked = 0;
            foreach ($pivots as $pivot) {
                $p = (array) $pivot;
                $permissionId = isset($p['permission_id']) ? (int) $p['permission_id'] : null;
                $roleId = isset($p['role_id']) ? (int) $p['role_id'] : null;
                if (! $permissionId || ! $roleId) {
                    continue;
                }

                $permName = $legacyPermissions[$permissionId] ?? null;
                if (! is_string($permName) || $permName === '') {
                    continue;
                }

                $role = Role::query()->find($roleId);
                $permission = Permission::query()->where('name', $permName)->where('guard_name', 'web')->first();
                if (! $role || ! $permission) {
                    continue;
                }

                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
                $linked++;
            }

            $this->info("Role–permission links applied: {$linked}");
        }

        VoyagerPermissions::forgetCachedPermissions();

        if ($this->option('sync-users')) {
            $this->syncUsersRoleIdsToSpatie();
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function detectPermissionKeyColumn(string $source): ?string
    {
        $columns = Schema::connection($source)->getColumnListing('permissions');
        foreach (['key', 'name'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function syncUsersRoleIdsToSpatie(): void
    {
        $this->info('Syncing Spatie roles from users.role_id…');
        $updated = 0;

        User::query()
            ->whereNotNull('role_id')
            ->chunkById(200, function ($users) use (&$updated): void {
                foreach ($users as $user) {
                    $role = Role::query()->find($user->role_id);
                    if (! $role) {
                        continue;
                    }

                    $user->syncRoles([$role]);
                    $updated++;
                }
            });

        $this->info("Users synced: {$updated}");
        VoyagerPermissions::forgetCachedPermissions();
    }
}
