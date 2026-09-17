import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('public navbar remains visible alongside Tailwind collapse utilities', () => {
  const css = readFileSync('resources/css/app.css', 'utf8');
  const layout = readFileSync('resources/views/layouts/site.blade.php', 'utf8');

  assert.match(css, /\.public-site-body \.site-nav \.navbar-collapse\{visibility:visible\}/);
  assert.match(layout, /navbar-expand-lg/);
  assert.match(layout, /data-bs-target="#siteNav"/);
  assert.match(layout, /route\('site\.services'\)/);
  assert.match(layout, /route\('site\.store'\)/);
  assert.match(layout, /route\('site\.resellers'\)/);
  assert.match(layout, /general\.registration_enabled/);
});
