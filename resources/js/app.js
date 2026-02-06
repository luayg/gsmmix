// resources/js/admin.js  ← أو app.js إن كنت تستخدمه
import $ from 'jquery';
window.$ = window.jQuery = $;        // ← اجعل jQuery عالميًا مرة واحدة

import 'bootstrap';

// DataTables (مرة واحدة هنا)
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

// Select2 (مرة واحدة هنا)
import 'select2/dist/js/select2.full.js';
import 'select2/dist/css/select2.min.css';
import 'select2-bootstrap-5-theme/dist/select2-bootstrap-5-theme.min.css';

// أي تهيئة عامة…
import { initModalEditors } from './modal-editors';
window.initModalEditors = initModalEditors;