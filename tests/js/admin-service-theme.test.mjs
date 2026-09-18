import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const index = readFileSync('resources/views/admin/services/_index.blade.php', 'utf8');
const modal = readFileSync('resources/views/admin/partials/service-modal.blade.php', 'utf8');

test('all service tables use dark readable rows with explicit text contrast', () => {
    assert.match(index, /svc-table tbody tr:nth-child\(odd\)\{background:rgba\(11,39,64/);
    assert.match(index, /svc-table tbody td\{color:#e3f0fb!important/);
    assert.match(index, /data-admin-theme="light".*svc-table tbody td\{color:#17364e!important/);
});

test('shared create and edit service modal no longer forces white or green surfaces', () => {
    assert.match(modal, /#serviceModal \.modal-body\{[^}]*background:#071827;color:#e7f3ff/);
    assert.match(modal, /#serviceModal \.modal-header\{background:linear-gradient\(90deg,#0b2740,#0d3858\)/);
    assert.doesNotMatch(modal, /#serviceModal \.modal-header\{background:#3bb37a/);
    assert.match(modal, /#serviceModal \.tabs-top button\.active\{background:#1976df;color:#fff/);
});
