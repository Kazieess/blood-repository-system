import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';

const browser = new Window({ url: 'http://localhost:8000/workspace' });
browser.document.write((await readFile(new URL('../connected.html', import.meta.url), 'utf8')).replace(/<script[\s\S]*?<\/script>/g, '').replace(/<link[^>]+>/g, ''));
for (const key of ['window', 'document', 'location', 'HTMLElement', 'Element', 'HTMLInputElement', 'HTMLSelectElement', 'HTMLFormElement', 'HTMLDialogElement', 'FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
Object.defineProperty(globalThis, 'navigator', { value: browser.navigator, configurable: true });
browser.scrollTo = () => {};
const document = browser.document;
let signedIn = false; let failList = false; let rejectLogin = false; let loseCreateReply = false; let lateResolve;
const records = new Map(); const creations = new Map();
const account = { name: 'Fictional Staff', email: 'staff@example.test', role: 'Red Cross Administrator', permissions: ['donors.read', 'donors.create', 'donors.update', 'donors.all', 'audit.read'] };
const audit = [{ id: 1, actor: 'Fictional Staff', action: 'donor.corrected', subject_id: 'record-reference', reason: 'Fictional correction reason', changed_fields: ['municipality'], request_id: 'request-reference', created_at: '2026-11-06T18:30:00Z' }];
const reply = (body, status = 200) => new Response(status === 204 ? null : JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
const collection = rows => ({ data: rows, meta: { total: rows.length, current_page: 1, last_page: 1, from: rows.length ? 1 : null } });
globalThis.fetch = async (path, options = {}) => {
  const url = new URL(path, 'http://localhost:8000');
  if (path === '/sanctum/csrf-cookie') { document.cookie = 'XSRF-TOKEN=sample%2Btoken; path=/'; return reply(null, 204); }
  if (path === '/api/v1/auth/login') { if (rejectLogin) return reply({ message: 'Check the submitted fields.', errors: { email: ['The provided credentials are invalid or unavailable.'] } }, 422); signedIn = true; return reply({ data: account }); }
  if (!signedIn) return reply({ message: 'Sign in to continue.' }, 401);
  if (path === '/api/v1/me') return reply({ data: account });
  if (path === '/api/v1/auth/logout') { signedIn = false; return reply(null, 204); }
  if (url.pathname === '/api/v1/donors' && options.method !== 'POST') {
    if (url.searchParams.get('q') === 'slow') return new Promise(resolve => { lateResolve = () => resolve(reply(collection([]))); });
    if (failList) return reply({ message: 'The service could not complete this request.', request_id: 'TEST-REF' }, 500);
    const q = (url.searchParams.get('q') || '').toLowerCase();
    const rows = [...records.values()].filter(item => (!q || (item.name + item.municipality).toLowerCase().includes(q)) && (!url.searchParams.get('blood_type') || item.blood_type === url.searchParams.get('blood_type')));
    return reply(collection(rows));
  }
  if (path === '/api/v1/donors' && options.method === 'POST') {
    assert.equal(options.headers['X-XSRF-TOKEN'], 'sample+token');
    const key = options.headers['Idempotency-Key']; assert.ok(key);
    if (creations.has(key)) return reply({ data: creations.get(key) });
    const input = JSON.parse(options.body);
    const id = `00000000-0000-4000-8000-${String(records.size + 1).padStart(12, '0')}`;
    const record = { ...input, id, record_status: 'for_review', version: 1, created_at: '2026-11-06T18:30:00Z', updated_at: '2026-11-06T18:30:00Z', can_update: true };
    records.set(id, record); creations.set(key, record);
    if (loseCreateReply) { loseCreateReply = false; throw new TypeError('Connection lost after save'); }
    return reply({ data: record }, 201);
  }
  if (path.startsWith('/api/v1/donors/')) {
    const record = records.get(path.split('/').at(-1));
    if (!record) return reply({ message: 'Record not found.' }, 404);
    if (options.method === 'PATCH') {
      const input = JSON.parse(options.body);
      if (input.version !== record.version) return reply({ message: 'The record changed.' }, 409);
      Object.assign(record, input, { version: record.version + 1 });
    }
    return reply({ data: { ...record } });
  }
  if (url.pathname === '/api/v1/audit-logs') return reply(collection(audit.filter(item => !url.searchParams.get('q') || item.action === url.searchParams.get('q'))));
  throw new Error('Unexpected API path: ' + path);
};
const tick = () => new Promise(resolve => setTimeout(resolve, 10));
async function go(path) { browser.location.hash = '#/' + path; await tick(); }
function click(selector) { const control = document.querySelector(selector); assert.ok(control, selector); control.click(); }
function submit(selector, values = {}) {
  const form = document.querySelector(selector); assert.ok(form, selector);
  for (const [name, value] of Object.entries(values)) { const field = form.elements.namedItem(name); assert.ok(field, name); field.value = value; }
  form.dispatchEvent(new browser.Event('submit', { bubbles: true, cancelable: true }));
}

test('Phase 2 connected workflow reviews saves, resolves conflicts, preserves drafts and clears expired sessions', async () => {
  await import('../src/connected.js');
  assert.ok(document.querySelector('#server-login'));
  click('[data-command="toggle-password"]'); assert.equal(document.querySelector('#staff-password').type, 'text'); click('[data-command="toggle-password"]');
  rejectLogin = true;
  submit('#server-login', { email: 'staff@example.test', password: 'fictional-only' }); await tick();
  assert.equal(document.querySelector('#staff-email').value, 'staff@example.test'); assert.equal(document.querySelector('#staff-password').value, '');
  assert.match(document.querySelector('#login-email-error').textContent, /invalid/);
  rejectLogin = false; submit('#server-login', { email: 'staff@example.test', password: 'fictional-only' }); await tick();
  assert.match(document.querySelector('main').textContent, /Good records start here/);
  assert.match(document.querySelector('main').textContent, /Accessible donor records0/);
  await go('donors');
  click('[data-command="create"]');
  submit('#server-donor', { name: '  ', municipality: 'Digos City', blood_type: 'O+' });
  assert.match(document.querySelector('[data-error="name"]').textContent, /two characters/);
  submit('#server-donor', { name: 'Fictional Persisted Donor', municipality: 'Digos City', blood_type: 'O+' });
  assert.equal(document.querySelector('#server-donor').dataset.stage, 'review'); assert.equal(records.size, 0);
  click('[data-command="back-to-details"]'); assert.equal(document.querySelector('[name="name"]').value, 'Fictional Persisted Donor');
  submit('#server-donor'); submit('#server-donor'); await tick();
  assert.equal(records.size, 1); assert.match(document.querySelector('main').textContent, /Fictional Persisted Donor/);
  assert.match(document.querySelector('main').textContent, /Nov 7, 2026, 2:30 AM PHT/);
  const first = [...records.values()][0];
  click('[data-command="edit"]');
  submit('#server-donor', { name: 'My corrected name', reason: 'Fictional correction' });
  Object.assign(first, { version: 2, name: 'Saved elsewhere', municipality: 'Hagonoy', blood_type: 'A+' });
  submit('#server-donor'); await tick();
  assert.equal(document.querySelector('#server-donor').dataset.stage, 'conflict');
  assert.equal(document.querySelector('[name="name"]').value, 'My corrected name');
  click('[data-command="load-conflict"]'); await tick();
  assert.match(document.querySelector('#save-review').textContent, /Saved elsewhere/);
  document.querySelector('[name="resolve_name"]').value = 'draft';
  click('[data-command="apply-conflict"]');
  assert.equal(document.querySelector('[name="name"]').value, 'My corrected name');
  assert.equal(document.querySelector('[name="municipality"]').value, 'Hagonoy');
  assert.equal(document.querySelector('[name="version"]').value, '2');
  submit('#server-donor'); submit('#server-donor'); await tick();
  assert.equal(first.version, 3); assert.equal(first.name, 'My corrected name'); assert.equal(first.municipality, 'Hagonoy');
  await go('donors'); click('[data-command="create"]');
  document.querySelector('[name="name"]').value = 'Unsaved entry'; click('[data-command="close"]');
  assert.equal(document.querySelector('#discard-panel').hidden, false); click('[data-command="keep-editing"]');
  assert.equal(document.querySelector('[name="name"]').value, 'Unsaved entry');
  click('[data-command="close"]'); click('[data-command="discard"]'); assert.equal(document.querySelector('#record-dialog').open, false);
  click('[data-command="create"]');
  submit('#server-donor', { name: 'Uncertain Save', municipality: 'Digos City', blood_type: 'B+' });
  loseCreateReply = true; submit('#server-donor'); await tick();
  assert.equal(records.size, 2); assert.match(document.querySelector('.form-error').textContent, /could not be confirmed/);
  submit('#server-donor'); await tick(); assert.equal(records.size, 2); assert.equal(creations.size, 2);
  await go('donors'); submit('[data-filter-form="donors"]', { q: 'Uncertain', blood_type: 'B+', per_page: '25' }); await tick();
  assert.match(browser.location.hash, /blood_type=B%2B/); assert.equal(document.querySelector('[name="q"]').value, 'Uncertain');
  assert.match(document.querySelector('main').textContent, /1 matching records/);
  await go('audit?q=donor.corrected'); click('[data-command="audit-details"]');
  assert.match(document.querySelector('#record-dialog').textContent, /Fictional correction reason/); click('[data-command="close"]');
  click('[data-command="account"]'); assert.match(document.querySelector('#record-dialog').textContent, /staff@example.test/); click('[data-command="close"]');
  await go('donors?q=slow'); assert.ok(document.querySelector('[role="status"]'));
  await go('guide'); lateResolve(); await tick(); assert.match(document.querySelector('main').textContent, /A clearer way to work/);
  account.permissions = account.permissions.filter(permission => permission !== 'audit.read');
  click('[data-command="refresh-session"]'); await tick();
  assert.ok([...document.querySelectorAll('[data-feature="audit.read"]')].every(link => link.hidden));
  assert.ok(!document.body.textContent.includes('Fictional correction reason'));
  await go('audit'); assert.match(document.querySelector('main').textContent, /Access is restricted/);
  account.permissions.push('audit.read'); await go('guide'); click('[data-command="refresh-session"]'); await tick();
  failList = true; await go('donors'); assert.match(document.querySelector('main').textContent, /TEST-REF/);
  failList = false; click('[data-command="retry"]'); await tick(); assert.match(document.querySelector('main').textContent, /My corrected name/);
  Object.defineProperty(browser.navigator, 'onLine', { value: false, configurable: true }); browser.dispatchEvent(new browser.Event('offline'));
  assert.equal(document.querySelector('#connection-notice').hidden, false);
  Object.defineProperty(browser.navigator, 'onLine', { value: true, configurable: true }); browser.dispatchEvent(new browser.Event('online'));
  assert.equal(document.querySelector('#connection-notice').hidden, true);
  signedIn = false; await go('donors/' + first.id);
  assert.ok(document.querySelector('#server-login')); assert.equal(document.querySelector('#main').hasAttribute('aria-busy'), false);
  assert.ok(!document.body.textContent.includes('My corrected name'));
  submit('#server-login', { email: 'staff@example.test', password: 'fictional-only' }); await tick();
  assert.match(document.querySelector('main').textContent, /My corrected name/);
  click('[data-command="logout"]'); await tick(); assert.ok(document.querySelector('#server-login'));
  assert.ok(!document.body.textContent.includes('My corrected name'));
  for (const name of ['workspace.js', 'workspace-ui.js', 'workspace-forms.js']) {
    const source = await readFile(new URL('../src/' + name, import.meta.url), 'utf8');
    assert.ok(!/from ['"].*\/(model|data|views|operations)\.js/.test(source), 'Connected modules must not import demo fixtures.');
  }
  await browser.happyDOM.close();
});
