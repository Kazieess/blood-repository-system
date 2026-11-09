import test, { beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { state, roles, signIn, can, addDonor, updateDonor, recordEligibility, addDonation, addUnit, changeUnit, availableUnits, publishSnapshot, reportRows, csv, validDate } from '../src/model.js';
import { escapeHtml } from '../src/ui.js';
import { home, availabilityResults } from '../src/public.js';

const baseline = structuredClone(state);
beforeEach(() => { Object.assign(state, structuredClone(baseline)); });
test('all five demo roles expose their intended workspace and deny unrelated writes', () => {
  for (const role of roles) {
    signIn(role);
    if (['Report Viewer', 'System Administrator'].includes(role)) assert.throws(() => addDonor({ name: 'Sample', area: 'Digos', blood: 'O+' }), /permission/);
  }
  signIn('Report Viewer'); assert.equal(can('reports'), true); assert.equal(can('inventory'), false);
  state.session = null; assert.throws(() => addUnit({}), /permission/);
});
test('creating and correcting a donor validates values and records activity', () => {
  signIn(roles[0]);
  assert.throws(() => addDonor({ name: '   ', area: 'Digos', blood: 'O+' }), /Full name/);
  assert.throws(() => addDonor({ name: 'Sample', area: 'Digos', blood: 'X' }), /blood type/);
  const donor = addDonor({ name: 'Demo Person', area: 'Digos', blood: 'O+' });
  assert.equal(donor.status, 'For review');
  assert.throws(() => updateDonor(donor.id, { name: 'Changed', area: 'Digos', blood: 'A+', reason: '' }), /reason/i);
  assert.equal(donor.name, 'Demo Person');
  updateDonor(donor.id, { name: 'Changed', area: 'Digos', blood: 'A+', reason: 'Sample correction' });
  assert.equal(donor.name, 'Changed'); assert.equal(state.audit[0].reference, donor.id);
});
test('staff assessments are explicit and donation records update the profile without duplicate submissions', () => {
  signIn(roles[0]); const donor = state.donors[0]; const count = donor.donations;
  recordEligibility(donor.id, { status: 'Deferred', until: '2026-12-01', reason: 'Demo assessment REF-1' });
  assert.equal(donor.status, 'Deferred');
  addDonation(donor.id, { date: '2026-11-01', location: 'Demo site' });
  assert.equal(donor.donations, count + 1); assert.equal(donor.lastDonation, '2026-11-01');
  assert.equal(donor.status, 'Deferred', 'Recording history does not automatically approve eligibility.');
  assert.throws(() => addDonation(donor.id, { date: '2026-11-01', location: 'Demo site' }), /already exists/);
  assert.throws(() => addDonation(donor.id, { date: '2027-01-01', location: 'Demo site' }), /sample date/);
});
test('inventory needs verification before availability and prevents repeat release', () => {
  signIn(roles[0]); const before = availableUnits().length;
  const id = addUnit({ reference: 'DEMO-UNIT', blood: 'O+', collected: '2026-11-01', expiry: '2026-11-28', location: 'Demo shelf' });
  assert.equal(availableUnits().length, before);
  assert.throws(() => changeUnit(id, { status: 'Released', reason: 'Sample release' }), /verified/);
  assert.throws(() => changeUnit(id, { operation: 'verify', reason: ' ' }), /Reason/);
  changeUnit(id, { operation: 'verify', reason: 'Sample authorized confirmation' });
  assert.equal(availableUnits().length, before + 1);
  changeUnit(id, { status: 'Released', reason: 'Sample release' });
  assert.equal(availableUnits().length, before);
  assert.throws(() => changeUnit(id, { status: 'Released', reason: 'Retry' }), /already left/);
  assert.match(state.audit[0].reason, /Quantity: 1/);
});
test('invalid collection/expiry and duplicate bag references are rejected', () => {
  signIn(roles[0]);
  const values = { reference: 'DEMO-UNIT', blood: 'O+', collected: '2026-11-01', expiry: '2026-10-01', location: 'Demo shelf' };
  assert.throws(() => addUnit(values), /Expiry/);
  addUnit({ ...values, expiry: '2026-11-28' });
  assert.throws(() => addUnit({ ...values, reference: 'demo-unit', expiry: '2026-11-28' }), /already in use/);
});
test('publication uses an explicit aggregate snapshot and excludes unavailable units', () => {
  signIn(roles[0]); publishSnapshot(); const first = state.publicSnapshot.find(item => item.type === 'O+').units;
  const unit = state.units.find(item => item.blood === 'O+'); changeUnit(unit.id, { status: 'Quarantined', reason: 'Sample review' });
  assert.equal(state.publicSnapshot.find(item => item.type === 'O+').units, first);
  publishSnapshot(); assert.equal(state.publicSnapshot.find(item => item.type === 'O+').units, first - 1);
  assert.deepEqual(Object.keys(state.publicSnapshot[0]).sort(), ['type', 'units']);
  const publicHtml = availabilityResults(''); for (const donor of state.donors) assert.ok(!publicHtml.includes(donor.name));
  state.publicMode = 'stale'; assert.ok(!availabilityResults('').includes('availability-card'));
  state.publicMode = 'offline'; assert.match(availabilityResults(''), /temporarily unavailable/);
});
test('report date filters use the correct date field and donation totals reconcile', () => {
  const rows = reportRows('donations', '', '');
  assert.equal(rows.length, state.donors.reduce((total, donor) => total + donor.donations, 0));
  assert.equal(reportRows('donors', '2026-11-01', '2026-11-06').length, 2);
  assert.equal(reportRows('expiry', '2026-11-09', '2026-11-09').length, 8);
  assert.equal(reportRows('inventory', '2026-12-01', '').length, 0);
});
test('CSV quoting and HTML output do not interpret user text as formulas or markup', () => {
  const output = csv([{ name: '=1+1', area: 'A,"B"\nC', phone: '+123' }]);
  assert.ok(output.includes("'=1+1")); assert.ok(output.includes("'+123")); assert.ok(output.includes('""B""'));
  assert.ok(csv([{ value: '  =1+1' }]).includes("'  =1+1"));
  assert.equal(escapeHtml('<img onerror="bad">'), '&lt;img onerror=&quot;bad&quot;&gt;');
});
test('dates reject rollover and landing page has a hero and real route destinations', () => {
  assert.equal(validDate('2026-02-30'), false); assert.equal(validDate('2026-11-06'), true);
  assert.match(home(), /class="hero"/); assert.match(home(), /#\/availability/); assert.match(home(), /#\/login/);
});
