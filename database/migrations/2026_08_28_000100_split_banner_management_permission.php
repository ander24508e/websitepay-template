<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const GRANULAR_PERMISSIONS = [
        'banners.create',
        'banners.update',
        'banners.delete',
    ];

    public function up(): void
    {
        $this->splitPermission('banners.manage', self::GRANULAR_PERMISSIONS);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->splitPermission(self::GRANULAR_PERMISSIONS, ['banners.manage']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function splitPermission(string|array $sources, array $destinations): void
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $permissionsTable = $tables['permissions'];
        $rolePermissionsTable = $tables['role_has_permissions'];
        $modelPermissionsTable = $tables['model_has_permissions'];
        $sourceNames = (array) $sources;

        $sourceIds = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $sourceNames)
            ->pluck('id');

        foreach ($destinations as $permission) {
            DB::table($permissionsTable)->updateOrInsert(
                ['name' => $permission, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }

        $destinationIds = DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $destinations)
            ->pluck('id');

        foreach ($sourceIds as $sourceId) {
            $roleAssignments = DB::table($rolePermissionsTable)
                ->where($permissionKey, $sourceId)
                ->get();
            $modelAssignments = DB::table($modelPermissionsTable)
                ->where($permissionKey, $sourceId)
                ->get();

            foreach ($destinationIds as $destinationId) {
                foreach ($roleAssignments as $assignment) {
                    $row = (array) $assignment;
                    $row[$permissionKey] = $destinationId;
                    DB::table($rolePermissionsTable)->insertOrIgnore($row);
                }

                foreach ($modelAssignments as $assignment) {
                    $row = (array) $assignment;
                    $row[$permissionKey] = $destinationId;
                    DB::table($modelPermissionsTable)->insertOrIgnore($row);
                }
            }
        }

        DB::table($permissionsTable)
            ->where('guard_name', 'web')
            ->whereIn('name', $sourceNames)
            ->delete();
    }
};
