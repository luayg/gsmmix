<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Isolated auth/RBAC fixture; never imports the real SQL dump or contacts providers. */
abstract class SecurityTestCase extends TestCase
{
    private string $sessionDirectory;
    private int $userSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->sessionDirectory = sys_get_temp_dir() . '/gsmmix-sessions-' . bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->sessionDirectory);
        config([
            'app.key' => 'base64:' . base64_encode(random_bytes(32)),
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'file',
            'session.files' => $this->sessionDirectory,
            'session.secure' => false,
            'cache.default' => 'array',
            'hashing.bcrypt.rounds' => 4,
        ]);
        app('db')->purge('sqlite');
        $this->resetSessionRuntime();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->unique()->nullable();
            $table->string('status')->default('active');
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        foreach (['roles', 'permissions'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('guard_name');
                $table->timestamps();
                $table->unique(['name', 'guard_name']);
            });
        }
        foreach (['roles' => 'role_id', 'permissions' => 'permission_id'] as $name => $column) {
            Schema::create('model_has_' . $name, function (Blueprint $table) use ($column): void {
                $table->unsignedBigInteger($column);
                $table->string('model_type');
                $table->unsignedBigInteger('model_id');
                $table->primary([$column, 'model_id', 'model_type']);
            });
        }
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RbacSeeder)->run();
    }

    protected function user(?string $role = null, string $status = 'active'): User
    {
        $number = ++$this->userSequence;
        $user = User::create([
            'name' => "Test account {$number}",
            'email' => "account{$number}@example.test",
            'username' => "account{$number}",
            'status' => $status,
            'password' => 'A-strong-test-password-123!',
        ]);
        if ($role !== null) {
            $user->assignRole($role);
        }
        return $user;
    }

    /** Rebuild auth/session services to catch pre-StartSession middleware regressions. */
    protected function resetSessionRuntime(): void
    {
        Auth::forgetGuards();
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
    }

    protected function rememberSessionCookie(): void
    {
        // JSON test requests omit cookies unless credentials are explicitly enabled.
        $this->withCredentials();
        $this->withCookie(config('session.cookie'), app('session')->getId());
        $this->resetSessionRuntime();
    }

    protected function tearDown(): void
    {
        if (isset($this->sessionDirectory)) {
            File::deleteDirectory($this->sessionDirectory);
        }
        parent::tearDown();
    }
}
