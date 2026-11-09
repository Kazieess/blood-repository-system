import test from 'node:test';
import assert from 'node:assert/strict';
import { overview, registry, profile, auditDetails, timestamp, pager } from '../src/workspace-ui.js';

test('overview uses server totals rather than the size of the recent-record page', () => {
  const user = { name: 'Fictional', role: 'Coordinator', permissions: ['donors.read'] };
  const html = overview(user, { data: [], meta: { total: 417 } }, null);
  assert.match(html, /<strong>417<\/strong>/);
  assert.match(html, /Records assigned to your account/);
  assert.ok(!html.includes('Recorded activity events'));
});
test('an account with no module permissions gets a useful overview without fabricated counts', () => {
  const html = overview({ name: 'Fictional', role: 'Report Viewer', permissions: [] }, null, null);
  assert.match(html, /no operational module/);
  assert.ok(!html.includes('Accessible donor records'));
});
test('empty filtered results distinguish an empty registry from a failed search', () => {
  const user = { permissions: ['donors.read', 'donors.create'] };
  const data = { data: [], meta: { total: 0, current_page: 1, last_page: 1 } };
  assert.match(registry(user, data, new URLSearchParams()), /first donor record/);
  assert.match(registry(user, data, new URLSearchParams({ q: 'nothing' })), /No donors match/);
});
test('out-of-range pagination provides recovery without misreporting the registry as empty', () => {
  const data = { data: [], meta: { total: 3, current_page: 9, last_page: 1 } };
  const html = registry({ permissions: ['donors.read'] }, data, new URLSearchParams({ page: '9', blood_type: 'O+' }));
  assert.match(html, /No records on this page/); assert.match(html, /page=1/); assert.ok(!html.includes('first donor record'));
});
test('pagination keeps the search and blood type intact when changing pages', () => {
  const html = pager({ total: 51, current_page: 2, last_page: 3 }, new URLSearchParams({ q: 'Digos City', blood_type: 'O+', per_page: '25' }), 'donors', 25);
  assert.match(html, /q=Digos\+City/); assert.match(html, /blood_type=O%2B/); assert.match(html, /page=3/); assert.match(html, /Showing 26–50 of 51/);
});
test('profile and audit values are escaped and read-only records have no correction control', () => {
  const html = profile({ id: '<reference>', name: '<img onerror="bad">', municipality: '<area>', blood_type: 'O+', record_status: 'for_review', version: 1, created_at: '2026-11-06T18:30:00Z', can_update: false });
  assert.ok(!html.includes('<img onerror=')); assert.match(html, /&lt;img/); assert.ok(!html.includes('data-command="edit"'));
  const audit = auditDetails({ actor: '<actor>', action: 'donor.corrected', reason: '<script>bad</script>', changed_fields: ['municipality'], created_at: '2026-11-06T18:30:00Z', request_id: '<request>' });
  assert.ok(!audit.includes('<script>')); assert.match(audit, /&lt;script/);
});
test('UTC timestamps cross midnight correctly in Philippine time and invalid dates are safe', () => {
  assert.match(timestamp('2026-11-06T18:30:00Z'), /Nov 7, 2026, 2:30 AM PHT/);
  assert.equal(timestamp(undefined), 'Not recorded');
});
