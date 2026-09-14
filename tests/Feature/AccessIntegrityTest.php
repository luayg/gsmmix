<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class AccessIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
    }

    public function test_clean_access_references_pass_audit(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'x',
        ]);
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'administrator',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $permissionId = DB::table('permissions')->insertGetId([
            'name' => 'dashboard',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $roleId,
            'model_type' => User::class,
            'model_id' => $userId,
        ]);
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permissionId,
            'role_id' => $roleId,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('auth:integrity-audit', [], $output);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('role_permission_missing_permission', $output->fetch());
    }

    public function test_broken_access_references_fail_audit(): void
    {
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'bad-role',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('permissions')->insert([
            'id' => 1,
            'name' => 'bad-permission',
            'guard_name' => 'api',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => 999,
            'model_type' => User::class,
            'model_id' => 999,
        ]);
        DB::table('model_has_permissions')->insert([
            'permission_id' => 999,
            'model_type' => User::class,
            'model_id' => 999,
        ]);
        DB::table('role_has_permissions')->insert([
            'permission_id' => 999,
            'role_id' => 999,
        ]);

        $output = new BufferedOutput();
        $exit = Artisan::call('auth:integrity-audit', [], $output);

        $this->assertSame(1, $exit);
        $text = $output->fetch();
        $this->assertStringContainsString('role_invalid_guard', $text);
        $this->assertStringContainsString('permission_invalid_guard', $text);
        $this->assertStringContainsString('model_role_missing_user', $text);
    }

    public function test_assigned_role_cannot_be_deleted(): void
    {
        $role = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => 1,
        ]);

        $response = app(RoleController::class)->destroy($role);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_assigned_permission_cannot_be_deleted(): void
    {
        $permission = Permission::create(['name' => 'orders.view', 'guard_name' => 'web']);
        DB::table('role_has_permissions')->insert([
            'permission_id' => $permission->id,
            'role_id' => 1,
        ]);

        $response = app(PermissionController::class)->destroy($permission);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
    }
}
