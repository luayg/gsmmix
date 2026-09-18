import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

const css = readFileSync('resources/css/admin.css', 'utf8');

test('dark admin modals override legacy light surfaces with readable colors', () => {
    assert.match(css, /html:not\(\[data-admin-theme="light"\]\) \.modal \.order-view-card/);
    assert.match(css, /html:not\(\[data-admin-theme="light"\]\) \.modal \.order-edit-card/);
    assert.match(css, /html:not\(\[data-admin-theme="light"\]\) \.modal \.bg-light/);
    assert.match(css, /color:var\(--admin-text\)!important;\s*background:#081d30!important/);
});

test('dark admin modal tables use explicit high-contrast text and borders', () => {
    assert.match(css, /\.modal \.table td\{\s*color:#e3f0fb!important/);
    assert.match(css, /\.modal \.table th\{color:#b9d3e7!important\}/);
});
