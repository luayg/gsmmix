<?php

declare(strict_types=1);

namespace App\Support;

final class AdminPermissions
{
    public const ADMIN_ROLE = 'Administrator';

    public const MODULES = [
        'users', 'groups', 'roles', 'permissions', 'finances', 'services',
        'orders', 'store', 'apis', 'sources', 'replies', 'pages', 'downloads',
        'settings', 'system', 'reports', 'logs', 'uploads',
    ];

    public static function all(): array
    {
        $permissions = ['admin.access', 'dashboard.view'];
        foreach (self::MODULES as $module) {
            foreach (['view', 'create', 'edit', 'delete'] as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }
        return $permissions;
    }

    /** Unknown routes and unknown bulk actions are deliberately denied. */
    public static function required(?string $name, string $method, string $path, ?string $bulkAction = null): ?array
    {
        $method = strtoupper($method);
        $safe = in_array($method, ['GET', 'HEAD'], true);
        $path = trim($path, '/');

        // Unnamed routes inside the admin name group inherit its bare prefix.
        // Only the reviewed redirects below are permitted by path.
        if (!$name || $name === 'admin.') {
            if (!$safe) {
                return null;
            }
            if ($path === 'admin') {
                return ['dashboard.view'];
            }
            if ($path === 'admin/api') {
                return ['apis.view'];
            }
            if (preg_match('~^admin/services/(imei|server|file|smm|groups)$~D', $path)) {
                return ['services.view'];
            }
            return null;
        }

        if ($name === 'admin.dashboard') {
            return $safe ? ['dashboard.view'] : null;
        }
        if (!str_starts_with($name, 'admin.')) {
            return null;
        }

        $parts = explode('.', $name);
        $module = $parts[1] ?? '';
        $action = end($parts);
        if (str_starts_with($name, 'admin.users.finances.')) {
            $module = 'finances';
        } elseif (str_starts_with($name, 'admin.users.roles.')) {
            $module = 'roles';
        }
        if (!in_array($module, self::MODULES, true)) {
            return null;
        }

        // Importing services is not the same privilege as adding a provider.
        if ($module === 'apis' && in_array($action, ['import', 'import_wizard', 'import_page'], true)) {
            return ['apis.view', 'services.create'];
        }
        if (str_starts_with($name, 'admin.services.clone.')) {
            return $safe ? ['apis.view', 'services.create'] : null;
        }
        if ($name === 'admin.settings.mail.test') {
            return $method === 'POST' ? ['settings.edit'] : null;
        }
        if ($name === 'admin.finances.invoices.payments.store') {
            return $method === 'POST' ? ['finances.edit'] : null;
        }
        if ($name === 'admin.users.reset_verification') {
            return $method === 'POST' ? ['users.edit'] : null;
        }
        if ($name === 'admin.users.modal.reset_verification') {
            return $safe ? ['users.view'] : null;
        }
        if (in_array($name, ['admin.finances.payment-reviews.approve', 'admin.finances.payment-reviews.reject'], true)) {
            return $method === 'POST' ? ['finances.edit'] : null;
        }
        if ($action === 'bulk') {
            if ($safe || !in_array($bulkAction, ['active', 'inactive', 'delete'], true)) {
                return null;
            }
            return [$module . ($bulkAction === 'delete' ? '.delete' : '.edit')];
        }

        if ($method === 'DELETE' || $action === 'destroy' || $action === 'delete') {
            return [$module . '.delete'];
        }
        if (in_array($action, ['create', 'store'], true) || $module === 'uploads') {
            return [$module . '.create'];
        }
        if ($action === 'edit' || str_contains($name, '.form.')) {
            return [$module . '.edit'];
        }
        if (!$safe) {
            return in_array($action, ['update', 'import', 'toggle', 'sync', 'syncFields', 'set_overdraft', 'add_remove', 'add_payment', 'cancel', 'cache'], true)
                ? [$module . '.edit']
                : null;
        }

        $readActions = [
            'index', 'data', 'options', 'show', 'view', 'json', 'roles', 'groups',
            'summary', 'statement', 'modal', 'services', 'perms', 'imei', 'server',
            'file', 'smm', 'provider_services', 'services_json', 'users', 'products',
            'access', 'activity', 'error', 'general', 'banners', 'mail', 'payment', 'languages',
            'currencies', 'filemanager', 'update', 'maintenance', 'backups',
            'translations', 'preview', 'export',
            'print',
            'download',
            'proof',
        ];
        return in_array($action, $readActions, true) ? [$module . '.view'] : null;
    }

    /** Account/role administration must not be delegated via users.edit alone. */
    public static function administratorOnly(?string $name): bool
    {
        if (!$name) {
            return false;
        }
        foreach (['admin.roles.', 'admin.permissions.', 'admin.settings.', 'admin.system.', 'admin.users.roles.'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }
        return in_array($name, [
            'admin.users.store', 'admin.users.update', 'admin.users.destroy',
            'admin.users.modal.create', 'admin.users.modal.edit', 'admin.users.modal.delete',
            'admin.users.reset_verification', 'admin.users.modal.reset_verification',
        ], true);
    }
}
