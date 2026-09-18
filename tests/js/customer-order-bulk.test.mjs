import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const view = readFileSync('resources/views/customer/orders/create.blade.php', 'utf8');
const controller = readFileSync('app/Http/Controllers/Admin/Orders/BaseOrdersController.php', 'utf8');

test('bulk-capable services still accept a single main-field order', () => {
    assert.match(view, /id="bulkMode" value="0"/);
    assert.doesNotMatch(view, /name="bulk" value="1"/);
    assert.match(view, /bulkEntries\?\.value\.trim\(\)\?'1':'0'/);
    assert.match(controller, /input\('devices', ''\)\) !== ''/);
});

test('the customer sees specific validation details before a generic message', () => {
    assert.match(view, /alert\(details\|\|j\.message/);
});
