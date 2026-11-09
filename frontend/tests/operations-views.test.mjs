import test from 'node:test';
import assert from 'node:assert/strict';
import * as view from '../src/operations-views.js';
import { toInstant } from '../src/workspace-operations.js';

test('clinical values and historical definitions escape private entered content', () => {
  const html = view.clinicalValues({ notes: '<script>private()</script>', results: { qa: '<img onerror=alert(1)>' }, definitions: [{ key: 'qa', label: '<unsafe>', reference_text: '<reference>', unit: '<unit>' }] });
  assert.ok(!html.includes('<script>')); assert.ok(!html.includes('<img')); assert.ok(html.includes('&lt;unsafe&gt;')); assert.ok(html.includes('&lt;reference&gt;'));
});
test('Philippine input timestamps round-trip and invalid values remain safe', () => {
  assert.equal(toInstant('2026-11-09T08:30'), '2026-11-09T00:30:00.000Z');
  assert.equal(view.phtInput('2026-11-09T00:30:00Z'), '2026-11-09T08:30');
  assert.equal(view.phtInput('invalid'), '');
});
test('operations lists have semantic mobile cards as well as desktop records', () => {
  const html = view.records(['Reference', 'State'], [['QA-001', 'Spoiled']], 'Fictional units');
  assert.ok(html.includes('<table>')); assert.ok(html.includes('ops-cards')); assert.ok(html.includes('<dt>State</dt>')); assert.ok(html.includes('Spoiled'));
});
