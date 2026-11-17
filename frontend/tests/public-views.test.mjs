import test from 'node:test';
import assert from 'node:assert/strict';
import { availabilityView } from '../src/public-views.js';
import { reports } from '../src/management-views.js';
test('public availability hides stale counts and escapes approved labels', () => {
  const data = [{ blood_type: 'O+', component: '<script>bad</script>', location: 'QA Center', units: 99 }];
  const result = { state: 'available', data, published_at: new Date().toISOString(), valid_until: new Date(Date.now() + 60000).toISOString() };
  const html = availabilityView(result); assert.ok(html.includes('&lt;script&gt;')); assert.ok(!html.includes('<script>')); assert.ok(html.includes('99'));
  assert.ok(!availabilityView({ ...result, state: 'stale' }).includes('99'));
  assert.ok(!availabilityView({ ...result, valid_until: '2020-01-01T00:00:00Z' }).includes('99'));
  assert.match(availabilityView({ ...result, state: 'unavailable' }), /not published/);
});
test('report pagination uses actual server page size and retains filters', () => {
  const html = reports({ section: 'reports', params: new URLSearchParams('kind=expiry&location=QA&page=2'), data: { data: [{ blood_type: 'O+', component: '<img>', location: 'QA', stock_state: 'expired', units: 2 }], meta: { total: 26, current_page: 2, last_page: 2 }, scope: 'Inventory aggregates', generated_at: '2026-11-09T00:00:00Z' } });
  assert.match(html, /26.*26 of 26/); assert.ok(html.includes('kind=expiry&amp;location=QA')); assert.ok(html.includes('&lt;img&gt;'));
});
