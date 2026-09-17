import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('download edit embeds raw JSON that the button handler can parse',()=>{const view=readFileSync('resources/views/admin/downloads/index.blade.php','utf8');assert.match(view,/json_encode\(\$downloadEditData/);assert.match(view,/JSON\.parse\(document\.getElementById/);assert.doesNotMatch(view,/Js::from\(\['name'=>\$d->name/)});
test('latest products reserve compact artwork and visible details',()=>{const css=readFileSync('resources/css/app.css','utf8');assert.match(css,/grid-template-rows:150px 1fr/);assert.match(css,/\.cyber-product \.product-visual\{height:150px/)});
test('admin dashboard exposes business and operational metrics',()=>{const view=readFileSync('resources/views/admin/dashboard.blade.php','utf8');for(const label of ['Today orders','Today registration','Today payments','Online users','Acceptance rate','Orders requiring attention'])assert.match(view,new RegExp(label))});
test('general settings omits social links and module switches',()=>{const view=readFileSync('resources/views/admin/settings/general.blade.php','utf8');assert.doesNotMatch(view,/Social links/);assert.doesNotMatch(view,/Modules and display/)});
test('access logs provide managed IP blocking',()=>{const view=readFileSync('resources/views/admin/logs/access.blade.php','utf8');const middleware=readFileSync('app/Http/Middleware/RejectBlockedIp.php','utf8');assert.match(view,/Block IP/);assert.match(view,/Unblock/);assert.match(middleware,/blocked_ips/)});
