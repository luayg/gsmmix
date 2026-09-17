<?php

namespace Tests\Unit;

use App\Support\AdminPermissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdminPermissionsTest extends TestCase
{
    #[DataProvider('routeCases')]
    public function test_permission_mapping(?string $name, string $method, string $path, ?string $action, ?array $expected): void
    {
        $this->assertSame($expected, AdminPermissions::required($name, $method, $path, $action));
    }

    public static function routeCases(): iterable
    {
        foreach (AdminPermissions::MODULES as $module) {
            if ($module === 'uploads') {
                continue;
            }
            foreach (['GET' => ['index', 'view'], 'POST' => ['store', 'create'], 'PUT' => ['update', 'edit'], 'DELETE' => ['destroy', 'delete']] as $method => [$action, $permission]) {
                yield "$module-$method" => ["admin.$module.$action", $method, "admin/$module", null, ["$module.$permission"]];
            }
        }
        yield 'dashboard' => ['admin.dashboard', 'GET', 'admin/dashboard', null, ['dashboard.view']];
        yield 'root redirect' => [null, 'GET', 'admin', null, ['dashboard.view']];
        yield 'api redirect' => [null, 'GET', 'admin/api', null, ['apis.view']];
        yield 'services redirect' => [null, 'GET', 'admin/services/imei', null, ['services.view']];
        yield 'finance nesting' => ['admin.users.finances.statement', 'GET', '', null, ['finances.view']];
        yield 'finance mutation' => ['admin.users.finances.add_payment', 'POST', '', null, ['finances.edit']];
        yield 'finance form' => ['admin.users.finances.form.add_remove', 'GET', '', null, ['finances.edit']];
        yield 'user role assignment' => ['admin.users.roles.sync', 'POST', '', null, ['roles.edit']];
        yield 'bulk delete' => ['admin.services.imei.bulk', 'POST', '', 'delete', ['services.delete']];
        yield 'bulk enable' => ['admin.services.file.bulk', 'POST', '', 'active', ['services.edit']];
        yield 'bulk invalid' => ['admin.services.file.bulk', 'POST', '', 'unexpected', null];
        yield 'bulk absent' => ['admin.services.file.bulk', 'POST', '', null, null];
        yield 'import' => ['admin.apis.services.import', 'POST', '', null, ['apis.view', 'services.create']];
        yield 'upload' => ['admin.uploads.summernote', 'POST', '', null, ['uploads.create']];
        yield 'mail test' => ['admin.settings.mail.test', 'POST', '', null, ['settings.edit']];
        yield 'mail test wrong method' => ['admin.settings.mail.test', 'GET', '', null, null];
        yield 'reseller settings' => ['admin.settings.resellers', 'GET', '', null, ['settings.view']];
        yield 'unknown module' => ['admin.unknown.index', 'GET', '', null, null];
        yield 'unknown action' => ['admin.users.backdoor', 'GET', '', null, null];
        yield 'unnamed mutation' => [null, 'POST', 'admin', null, null];
        yield 'unknown unnamed route' => [null, 'GET', 'admin/unreviewed', null, null];
    }

    public function test_sensitive_account_and_role_operations_are_administrator_only(): void
    {
        foreach (['admin.roles.index', 'admin.permissions.update', 'admin.users.roles.sync', 'admin.users.update', 'admin.users.store', 'admin.system.backups'] as $route) {
            $this->assertTrue(AdminPermissions::administratorOnly($route));
        }
        foreach (['admin.users.index', 'admin.users.finances.statement', 'admin.orders.imei.update'] as $route) {
            $this->assertFalse(AdminPermissions::administratorOnly($route));
        }
    }
}
