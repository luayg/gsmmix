<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditAccessIntegrity extends Command
{
    protected $signature = 'auth:integrity-audit {--details=50 : Maximum risky rows to display}';

    protected $description = 'Read-only audit of roles, permissions, and access-control pivot references.';

    public function handle(): int
    {
        $state = [
            'roles_scanned' => 0,
            'permissions_scanned' => 0,
            'role_invalid_guard' => 0,
            'permission_invalid_guard' => 0,
            'model_role_missing_role' => 0,
            'model_role_missing_user' => 0,
            'model_permission_missing_permission' => 0,
            'model_permission_missing_user' => 0,
            'role_permission_missing_role' => 0,
            'role_permission_missing_permission' => 0,
        ];

        $details = [];
        $limit = max(0, min(200, (int) $this->option('details')));

        $roleIds = [];
        if (Schema::hasTable('roles')) {
            foreach (DB::table('roles')->orderBy('id')->get(['id', 'guard_name']) as $row) {
                $roleId = (int) $row->id;
                $roleIds[$roleId] = true;
                $state['roles_scanned']++;

                if ((string) $row->guard_name !== 'web') {
                    $state['role_invalid_guard']++;
                    $this->addDetail($details, $limit, 'roles', $roleId, 'role_invalid_guard');
                }
            }
        }

        $permissionIds = [];
        if (Schema::hasTable('permissions')) {
            foreach (DB::table('permissions')->orderBy('id')->get(['id', 'guard_name']) as $row) {
                $permissionId = (int) $row->id;
                $permissionIds[$permissionId] = true;
                $state['permissions_scanned']++;

                if ((string) $row->guard_name !== 'web') {
                    $state['permission_invalid_guard']++;
                    $this->addDetail($details, $limit, 'permissions', $permissionId, 'permission_invalid_guard');
                }
            }
        }

        $userIds = Schema::hasTable('users')
            ? DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->flip()->all()
            : [];
        $userModelType = User::class;

        if (Schema::hasTable('model_has_roles')) {
            $index = 0;
            foreach (DB::table('model_has_roles')->get(['role_id', 'model_type', 'model_id']) as $row) {
                $index++;
                $roleId = (int) ($row->role_id ?? 0);
                $modelId = (int) ($row->model_id ?? 0);

                if ($roleId <= 0 || !isset($roleIds[$roleId])) {
                    $state['model_role_missing_role']++;
                    $this->addDetail($details, $limit, 'model_has_roles', $index, 'model_role_missing_role');
                }

                if ((string) ($row->model_type ?? '') === $userModelType
                    && ($modelId <= 0 || !isset($userIds[$modelId]))) {
                    $state['model_role_missing_user']++;
                    $this->addDetail($details, $limit, 'model_has_roles', $index, 'model_role_missing_user');
                }
            }
        }

        if (Schema::hasTable('model_has_permissions')) {
            $index = 0;
            foreach (DB::table('model_has_permissions')->get(['permission_id', 'model_type', 'model_id']) as $row) {
                $index++;
                $permissionId = (int) ($row->permission_id ?? 0);
                $modelId = (int) ($row->model_id ?? 0);

                if ($permissionId <= 0 || !isset($permissionIds[$permissionId])) {
                    $state['model_permission_missing_permission']++;
                    $this->addDetail($details, $limit, 'model_has_permissions', $index, 'model_permission_missing_permission');
                }

                if ((string) ($row->model_type ?? '') === $userModelType
                    && ($modelId <= 0 || !isset($userIds[$modelId]))) {
                    $state['model_permission_missing_user']++;
                    $this->addDetail($details, $limit, 'model_has_permissions', $index, 'model_permission_missing_user');
                }
            }
        }

        if (Schema::hasTable('role_has_permissions')) {
            $index = 0;
            foreach (DB::table('role_has_permissions')->get(['role_id', 'permission_id']) as $row) {
                $index++;
                $roleId = (int) ($row->role_id ?? 0);
                $permissionId = (int) ($row->permission_id ?? 0);

                if ($roleId <= 0 || !isset($roleIds[$roleId])) {
                    $state['role_permission_missing_role']++;
                    $this->addDetail($details, $limit, 'role_has_permissions', $index, 'role_permission_missing_role');
                }

                if ($permissionId <= 0 || !isset($permissionIds[$permissionId])) {
                    $state['role_permission_missing_permission']++;
                    $this->addDetail($details, $limit, 'role_has_permissions', $index, 'role_permission_missing_permission');
                }
            }
        }

        $this->info('Read-only access control integrity audit');
        $this->table(
            ['State', 'Count'],
            collect($state)->map(fn ($value, $key) => [$key, $value])->values()->all()
        );

        if ($details !== []) {
            $this->newLine();
            $this->warn('Access-control risks (read-only):');
            $this->table(
                ['Entity', 'Row', 'Risk'],
                array_map(fn (array $row) => [$row['entity'], $row['id'], $row['risk']], $details)
            );
        }

        $problemKeys = array_values(array_diff(array_keys($state), ['roles_scanned', 'permissions_scanned']));

        return collect($problemKeys)->contains(fn ($key) => $state[$key] > 0)
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function addDetail(array &$details, int $limit, string $entity, int $id, string $risk): void
    {
        if (count($details) < $limit) {
            $details[] = compact('entity', 'id', 'risk');
        }
    }
}
