import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('modal submit synchronizes every Summernote editor back to its textarea', () => {
  const source = readFileSync('resources/js/modal-editors.js', 'utf8');
  assert.match(source, /querySelectorAll\('textarea\[data-summernote="1"\], textarea\[data-editor="summernote"\]'\)/);
  assert.match(source, /ta\.value = window\.jQuery\(ta\)\.summernote\('code'\)/);
});
