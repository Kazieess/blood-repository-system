import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';

test('connected operations review writes, retain uncertain drafts, scope clinical controls and clear sessions', async () => {
  const browser = new Window({ url: 'http://localhost:8000/workspace' });
  browser.document.write((await readFile(new URL('../connected.html', import.meta.url), 'utf8')).replace(/<script[\s\S]*?<\/script>/g, '').replace(/<link[^>]+>/g, ''));
  for (const key of ['window', 'document', 'location', 'HTMLElement', 'Element', 'HTMLInputElement', 'HTMLSelectElement', 'HTMLFormElement', 'HTMLDialogElement', 'FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
  Object.defineProperty(globalThis, 'navigator', { value: browser.navigator, configurable: true });
  browser.scrollTo = () => {};
  const document = browser.document;
  const donorId = '10000000-0000-4000-8000-000000000001';
  const unitId = '20000000-0000-4000-8000-000000000001';
  const entryId = '30000000-0000-4000-8000-000000000001';
  const grants = ['screening.read', 'screening.write', 'screening.review', 'laboratory.read', 'laboratory.write', 'laboratory.review', 'eligibility.read', 'eligibility.write', 'eligibility.review'];
  const user = { id: 11, name: 'Fictional QA Staff', email: 'qa@example.test', role: 'Frontend capability fixture', permissions: ['donors.read', 'donors.create', 'donors.update', 'inventory.read', 'inventory.write', 'assignments.manage', 'alerts.manage', 'clinical.catalog', 'backups.manage', 'audit.read'] };
  const donor = { id: donorId, name: 'Fictional Assigned Donor', blood_type: 'O+', municipality: 'Fictional municipality', can_update: true, record_status: 'for_review', version: 1, donation_count: 0, clinical_permissions: grants, created_at: '2026-11-01T00:00:00Z', updated_at: '2026-11-01T00:00:00Z' };
  const donations = []; const units = []; const catalog = []; const entries = []; const backups = []; const writes = [];
  const staff = [{ id: 11, name: 'Fictional QA Staff' }, { id: 12, name: 'Fictional QA Reviewer' }];
  const assignments = [{ user_id: 11, active: true, clinical_permissions: grants, version: 1 }];
  let signedIn = true; let loseReply = true; let alertUnread = 1;
  const reply = (body, status = 200) => new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
  const collection = data => ({ data, meta: { total: data.length, current_page: 1, last_page: 1 } });
  globalThis.fetch = async (path, options = {}) => {
    const url = new URL(path, 'http://localhost:8000'); const route = url.pathname; const method = options.method ?? 'GET'; const body = options.body ? JSON.parse(options.body) : {};
    if (route === '/sanctum/csrf-cookie') { document.cookie = 'XSRF-TOKEN=qa; path=/'; return reply(null, 204); }
    if (!signedIn) return reply({ message: 'Sign in to continue.' }, 401);
    if (route === '/api/v1/me') return reply({ data: user });
    if (method !== 'GET') writes.push({ route, method, body, key: options.headers['Idempotency-Key'] });
    if (route === '/api/v1/auth/logout') { signedIn = false; return reply(null, 204); }
    if (route === '/api/v1/donors') return reply(collection([donor]));
    if (route === '/api/v1/audit-logs') return reply(collection([]));
    if (route === '/api/v1/stock-alerts') return reply({ ...collection([{ id: 1, blood_type: 'O+', component: 'Fictional component', location: 'Fictional store', condition: 'low_stock', available_count: 1, threshold: 2, open: true, acknowledged_at: alertUnread ? null : '2026-11-09T00:00:00Z', updated_at: '2026-11-09T00:00:00Z' }]), configured_scopes: 1, unacknowledged_open_count: alertUnread });
    if (route === '/api/v1/stock-alerts/1/acknowledge') { alertUnread = 0; return reply({ data: { acknowledged: true } }); }
    if (route === `/api/v1/donors/${donorId}`) return reply({ data: donor });
    if (route === `/api/v1/donors/${donorId}/donations`) {
      if (method === 'POST') {
        if (loseReply) { loseReply = false; throw new TypeError('Fictional lost network response'); }
        donations.push({ ...body, id: 'event-1', status: 'completed', version: 1 }); donor.donation_count = 1; return reply({ data: donations[0] }, 201);
      }
      return reply({ ...collection(donations), completed_count: donor.donation_count });
    }
    if (route === '/api/v1/inventory') {
      if (method === 'POST') { units.push({ ...body, id: unitId, status: 'pending', expired: false, verified_at: null, version: 1, history: [] }); return reply({ data: units[0] }, 201); }
      return reply({ ...collection(units), available_total: units.filter(row => row.status === 'available').length, components: [], locations: [] });
    }
    if (route === `/api/v1/inventory/${unitId}`) return reply({ data: units[0] });
    if (route === `/api/v1/inventory/${unitId}/transitions`) { units[0].status = 'available'; units[0].version = 2; units[0].verified_at = '2026-11-02T00:00:00Z'; return reply({ data: units[0] }); }
    if (route === '/api/v1/assignments') return reply({ ...collection([{ id: donorId, assignments_count: assignments.length }]), staff, clinical_permissions: grants });
    if (route === `/api/v1/assignments/${donorId}`) { if (method === 'PUT') assignments.push({ ...body, active: !body.revoke, version: 1 }); return reply({ data: assignments }); }
    if (route === '/api/v1/clinical-catalog') {
      if (method === 'POST') { catalog.push({ ...body, id: 1, version: 1 }); return reply({ data: catalog[0] }); }
      return reply({ data: catalog });
    }
    if (route === `/api/v1/donors/${donorId}/clinical/laboratory`) {
      if (method === 'POST') { entries.push({ ...body, id: entryId, category: 'laboratory', version: 1, status: 'recorded', recorded_by: 11, updated_by: 11, payload: { ...body.payload, definitions: catalog } }); return reply({ data: entries[0] }, 201); }
      return reply({ ...collection(entries), catalog: catalog.filter(field => field.active), allow_self_review: false });
    }
    if (route === `/api/v1/donors/${donorId}/clinical/laboratory/${entryId}/review`) { entries[0].status = 'reviewed'; entries[0].version = 2; entries[0].reviewed_by = user.id; return reply({ data: entries[0] }); }
    if (route === `/api/v1/donors/${donorId}/clinical/laboratory/${entryId}/history`) return reply({ data: [{ version: 2, snapshot: { ...entries[0], reason: 'Fictional revision reason' } }], meta: { total: 6, current_page: Number(url.searchParams.get('page') || 1), last_page: 2 } });
    if (route === '/api/v1/backups') {
      if (method === 'POST') { backups.push({ id: options.headers['Idempotency-Key'], status: 'completed', bytes: 1200, completed_at: '2026-11-09T00:00:00Z' }); return reply({ data: backups[0] }); }
      return reply({ ...collection(backups), supported: true, schedule_pht: null });
    }
    if (route.endsWith('/restore-copy')) return reply({ data: { copy_id: 'isolated-qa-copy' } });
    throw new Error(`Unplanned frontend fixture request: ${method} ${route}`);
  };
  const waitFor = async predicate => { for (let count = 0; count < 100; count++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } assert.ok(predicate(), 'UI did not reach the expected state'); };
  const go = async route => { browser.location.hash = '#/' + route; await waitFor(() => !document.querySelector('#main[aria-busy="true"]') && document.querySelector('#workspace-title').textContent !== 'Overview'); await new Promise(resolve => setTimeout(resolve, 20)); };
  const click = selector => { const button = document.querySelector(selector); assert.ok(button, selector); button.click(); };
  const fill = (name, value) => { const input = document.querySelector(`#record-dialog [name="${name}"]`); assert.ok(input, name); input.value = value; };
  const submit = async () => { document.querySelector('.operation-form').requestSubmit(); await new Promise(resolve => setTimeout(resolve, 25)); };
  await import('../src/workspace.js');
  await waitFor(() => document.querySelector('#stock-alert-notice a'));
  assert.match(document.querySelector('#stock-alert-notice').textContent, /1 stock alert/);
  await go(`donations/${donorId}`);
  click('[data-op="donation-new"]'); fill('reference', 'QA-DOC-001'); fill('donated_on', '2026-11-01'); fill('location', 'Fictional collection');
  await submit(); assert.equal(writes.length, 0, 'review must not issue a write');
  await submit(); assert.match(document.querySelector('.form-error').textContent, /not confirmed/); assert.equal(document.querySelector('[name="reference"]').value, 'QA-DOC-001');
  await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  const donationWrites = writes.filter(row => row.route.endsWith('/donations'));
  assert.equal(donationWrites[0].key, donationWrites[1].key, 'unchanged retry retains its key');
  assert.equal(donations.length, 1);
  click('[data-op="donation-new"]'); fill('reference', 'QA-UNSAVED'); click('#record-dialog [data-op="close"]');
  assert.equal(document.querySelector('.ops-discard').hidden, false); click('[data-op="keep"]'); assert.equal(document.querySelector('[name="reference"]').value, 'QA-UNSAVED');
  click('#record-dialog [data-op="close"]'); click('[data-op="discard"]'); await waitFor(() => !document.querySelector('#record-dialog').open);
  await go('inventory'); click('[data-op="unit-new"]'); fill('reference', 'QA-BAG-001'); fill('component', 'Fictional component'); fill('location', 'Fictional store'); fill('collected_at', '2026-11-01T10:00'); fill('expires_at', '2026-11-20T10:00'); fill('reason', 'Fictional source record');
  await submit(); assert.equal(units.length, 0); await submit(); await waitFor(() => document.querySelector('[data-op="unit-action"]'));
  assert.equal(units[0].collected_at, '2026-11-01T02:00:00.000Z');
  click('[data-op="unit-action"]'); document.querySelector('[name="verification_confirmed"]').checked = true; fill('reason', 'Authorized verification recorded'); await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open); assert.equal(units[0].status, 'available');
  await go(`assignments/${donorId}`); click('[data-op="assignment-new"]'); fill('user_id', '12'); document.querySelector('[name="grant_laboratory_read"]').checked = true; fill('reason', 'Assigned by Red Cross administrator'); await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  assert.deepEqual(assignments[1].clinical_permissions, ['laboratory.read']);
  await go('catalog'); click('[data-op="catalog-new"]'); fill('key', 'qa_measurement'); fill('label', 'Fictional QA measurement'); fill('format', 'number'); fill('reason', 'Fictional catalog definition'); await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  await go(`clinical/${donorId}?category=laboratory`); click('[data-op="clinical-new"]'); fill('source_reference', 'QA-LAB-SOURCE'); fill('observed_at', '2026-11-01T10:00'); fill('result_qa_measurement', '12.5'); fill('notes', '<img src=x onerror=alert(1)>'); await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  assert.equal(document.querySelector('img[onerror]'), null); assert.equal(document.querySelector('[data-op="clinical-review"]'), null, 'self-review is not offered');
  user.id = 12; click('[data-command="account"]'); click('[data-command="refresh-session"]'); await waitFor(() => document.querySelector('[data-op="clinical-review"]'));
  click('[data-op="clinical-review"]'); fill('reason', 'Checked fictional source'); await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open); assert.equal(entries[0].status, 'reviewed');
  click('[data-op="clinical-history"]'); await waitFor(() => document.querySelector('#record-dialog').textContent.includes('Page 1.'));
  click('[data-op="clinical-history"][data-page="2"]'); await waitFor(() => document.querySelector('#record-dialog').textContent.includes('Page 2.'));
  assert.equal(document.querySelector('#record-dialog').open, true); click('#record-dialog [data-op="close"]'); await waitFor(() => !document.querySelector('#record-dialog').open);
  catalog[0].active = false; click('[data-command="account"]'); click('[data-command="refresh-session"]'); await waitFor(() => document.querySelector('[data-op="clinical-edit"]') && !document.querySelector('#record-dialog').open);
  click('[data-op="clinical-edit"]'); assert.equal(document.querySelector('#record-dialog').open, false); assert.match(document.querySelector('#toast-message').textContent, /retired result fields/);
  await go('alerts'); click('[data-op="acknowledge"]'); await waitFor(() => document.querySelector('#stock-alert-notice').hidden); assert.equal(alertUnread, 0);
  await go('backups'); click('[data-op="backup-new"]'); fill('reason', 'Fictional recovery rehearsal'); await submit(); assert.equal(backups.length, 0); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  click('[data-op="backup-restore"]'); document.querySelector('[name="isolated_copy_confirmed"]').checked = true; await submit(); await submit(); await waitFor(() => !document.querySelector('#record-dialog').open);
  assert.deepEqual(writes.at(-1).body, { isolated_copy_confirmed: true });
  click('[data-command="logout"]'); await waitFor(() => document.querySelector('#server-login'));
  assert.equal(document.querySelector('#record-dialog').innerHTML, ''); assert.ok(!document.querySelector('#main').textContent.includes('QA-LAB-SOURCE'));
  assert.equal(document.querySelector('#stock-alert-notice').hidden, true);
  await browser.close();
});
