import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const routes = readFileSync('routes/web.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/Admin/Orders/BaseOrdersController.php', 'utf8');
const index = readFileSync('resources/views/admin/orders/_index.blade.php', 'utf8');
const claim = readFileSync('app/Services/Orders/OrderDispatchClaimService.php', 'utf8');
const serviceModal = readFileSync('resources/views/admin/partials/service-modal.blade.php', 'utf8');
const dashboard = readFileSync('app/Http/Controllers/Admin/DashboardController.php', 'utf8');

test('all provider order kinds expose approval and rejection actions', () => {
    for (const kind of ['imei', 'server', 'file', 'smm']) {
        assert.match(routes, new RegExp(`/${kind}/\\{id\\}/approve`));
        assert.match(routes, new RegExp(`/${kind}/\\{id\\}/reject`));
    }
    assert.match(index, /route\(\$routePrefix\.'\.approve'/);
    assert.match(index, /route\(\$routePrefix\.'\.reject'/);
});

test('approval remains an atomic prerequisite for provider dispatch', () => {
    assert.match(claim, /service\?->needs_approval.*order->approved/);
    assert.match(controller, /function approve\(Request \$request, int \$id\)/);
    assert.match(controller, /\$row->approved = true/);
    assert.match(controller, /function reject\(Request \$request, int \$id\)/);
    assert.match(controller, /refundOrderIfNeeded\(\$row, 'approval_rejected'\)/);
});

test('native API source selections stay readable when their menu is white', () => {
    assert.match(serviceModal, /select option,#serviceModal select optgroup\{color:#102a3f!important;background:#fff!important\}/);
});

test('dashboard pending approval only contains unapproved orders from approval services', () => {
    assert.match(dashboard, /where\('approved', 0\)/);
    assert.match(dashboard, /needs_approval", 1\)/);
});

test('bulk and duplicate service options are enforced by order validation', () => {
    assert.match(controller, /service->allow_bulk.*bulkRequested/);
    assert.match(controller, /service->allow_duplicates/);
    assert.match(controller, /Duplicate bulk entries are not allowed/);
});
