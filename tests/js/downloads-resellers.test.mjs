import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('download admin uses a modal with paid visibility and credit price', () => {
  const view = readFileSync('resources/views/admin/downloads/index.blade.php', 'utf8');
  assert.match(view, /data-bs-target="#createDownload"/);
  assert.match(view, /option value="paid"/);
  assert.match(view, /name="price"/);
  assert.match(view, /data-editor="summernote"/);
});

test('paid downloads use authenticated purchase and delivery endpoints', () => {
  const routes = readFileSync('routes/web.php', 'utf8');
  const controller = readFileSync('app/Http/Controllers/Customer/DownloadController.php', 'utf8');
  assert.match(routes, /downloads\.purchase/);
  assert.match(routes, /downloads\.download/);
  assert.match(controller, /lockForUpdate\(\)/);
  assert.match(controller, /DownloadPurchase::create/);
  assert.match(controller, /kind'=>'credit_remove'/);
});

test('public navigation exposes resellers and suppresses legacy pricing and support links', () => {
  const publicLayout = readFileSync('resources/views/layouts/site.blade.php', 'utf8');
  const customerLayout = readFileSync('resources/views/layouts/customer.blade.php', 'utf8');
  assert.match(publicLayout, /route\('site\.resellers'\)/);
  assert.match(publicLayout, /'pricing','support'/);
  assert.match(publicLayout, /general\.registration_enabled/);
  assert.match(customerLayout, /route\('home'\).*fas fa-globe.*Home/);
});

test('reseller images require genuine raster image validation', () => {
  const controller = readFileSync('app/Http/Controllers/Admin/ResellerController.php', 'utf8');
  const rule = readFileSync('app/Rules/SafeRasterImage.php', 'utf8');
  assert.match(controller, /new SafeRasterImage/);
  assert.match(rule, /getimagesize/);
  assert.match(rule, /image\/webp/);
});
