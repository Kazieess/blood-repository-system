// DOM integration checks. Install the locked development dependencies with npm ci.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';
const browser = new Window({ url: 'http://localhost:8080' });
const html = (await readFile(new URL('../index.html', import.meta.url), 'utf8')).replace(/<script[\s\S]*?<\/script>/g, '').replace(/<link[^>]+>/g, '');
browser.document.write(html);
for (const key of ['window', 'document', 'location', 'HTMLElement', 'Element', 'HTMLInputElement', 'HTMLSelectElement', 'HTMLFormElement', 'HTMLDialogElement', 'FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
browser.scrollTo = () => {};
const { state } = await import('../src/model.js');
await import('../src/main.js');
const document = browser.document;
const tick = () => new Promise(resolve => setTimeout(resolve, 5));
async function go(route) { browser.location.hash = '#/' + route; await tick(); }
function click(selector) { const target = document.querySelector(selector); assert.ok(target, `Missing control: ${selector}`); target.click(); }
function submit(selector, values = {}) {
  const form = document.querySelector(selector); assert.ok(form, `Missing form: ${selector}`);
  for (const [name, value] of Object.entries(values)) { const field = form.elements.namedItem(name); assert.ok(field, `Missing field: ${name}`); field.value = value; }
  form.querySelectorAll('input[type="checkbox"][required]').forEach(field => { field.checked = true; });
  form.dispatchEvent(new browser.Event('submit', { bubbles: true, cancelable: true }));
}
test('connected public and staff frontend workflows', async () => {
  assert.ok(document.querySelector('.hero h1'));
  for (const route of ['availability', 'help', 'privacy', 'login', 'recovery']) { await go(route); assert.ok(document.querySelector('main h1, main h2')); }
  submit('#recovery-form', { email: 'demo@example.test' }); assert.match(document.querySelector('.form-error').textContent, /No request was submitted/);
  await go('login'); click('[data-action="password"]'); assert.equal(document.querySelector('#sample-password').type, 'text');
  click('[data-action="password"]'); assert.equal(document.querySelector('#sample-password').type, 'password');
  submit('#auth-form', { email: 'demo@example.test', password: 'sample-only-123' }); assert.match(document.querySelector('#auth-form .form-error').textContent, /not connected/);
  assert.equal(state.session, null);
  await go('availability');
  assert.equal(document.querySelectorAll('.availability-card').length, 8);
  const blood = document.querySelector('#public-blood'); blood.value = 'O+'; blood.dispatchEvent(new browser.Event('change', { bubbles: true }));
  assert.equal(document.querySelectorAll('.availability-card').length, 1);
  const mode = document.querySelector('#public-mode'); mode.value = 'stale'; mode.dispatchEvent(new browser.Event('change', { bubbles: true }));
  assert.equal(document.querySelectorAll('.availability-card').length, 0);
  await go('login'); submit('#login-form', { role: 'Red Cross Administrator' }); await tick();
  assert.ok(document.querySelector('.stats')); assert.equal(document.body.classList.contains('public-view'), false);
  await go('donors');
  const search = document.querySelector('#donor-search'); search.value = 'Elena'; search.dispatchEvent(new browser.Event('input', { bubbles: true }));
  assert.match(document.querySelector('#result-count').textContent, /1 donors/);
  click('[data-action="clear"]'); click('[data-action="next"]'); assert.match(document.querySelector('#page-description').textContent, /7–10/);
  click('[data-action="register"]');
  submit('#workflow-form', { name: '  ', area: 'Digos City', blood: 'O+' }); assert.match(document.querySelector('.form-error').textContent, /Full name/);
  submit('#workflow-form', { name: '<Demo Donor>', area: 'Digos City', blood: 'O+' }); await tick();
  assert.equal(document.querySelector('.profile-heading h1').textContent, '<Demo Donor>');
  assert.equal(document.querySelectorAll('demo').length, 0);
  click('[data-action="donation"]'); submit('#workflow-form', { date: '2026-11-06', location: 'Demo site' }); await tick();
  assert.match(document.querySelector('main').textContent, /Demo site/);
  await go('inventory'); click('[data-action="unit-add"]');
  submit('#workflow-form', { reference: 'UI-DEMO-1', blood: 'B+', collected: '2026-11-01', expiry: '2026-11-30', location: 'Demo shelf' }); await tick();
  assert.match(document.querySelector('main').textContent, /UI-DEMO-1/);
  click('[data-action="unit-verify"]'); submit('#workflow-form', { reason: 'Sample confirmation' }); await tick();
  assert.equal(state.units.find(unit => unit.id === 'UI-DEMO-1').status, 'Available');
  click('[data-action="unit-change"]'); submit('#workflow-form', { status: 'Released', reason: 'Sample release' }); await tick();
  assert.equal(state.units.find(unit => unit.id === 'UI-DEMO-1').status, 'Released');
  assert.equal(document.querySelector('[data-action="unit-change"]'), null);
  await go('reports'); submit('[data-filter-form="reports"]', { type: 'expiry', from: '2026-11-09', to: '2026-11-09' }); await tick();
  assert.match(document.querySelector('main').textContent, /8 records/);
  await go('notifications'); click('[data-action="compose"]'); submit('#workflow-form', { recipient: 'Demo donor group', event: 'Donor reminder', message: 'Sample message only' }); await tick();
  assert.equal(state.notifications[0].status, 'Queued');
  click(`[data-action="message"][data-id="${state.notifications[0].id}"]`); submit('#workflow-form'); await tick();
  assert.equal(state.notifications[0].status, 'Simulated delivery');
  await go('audit'); assert.match(document.querySelector('main').textContent, /Sample stock: Released/);
  await go('settings'); submit('#settings-form', { chapter: 'Sample chapter label' });
  assert.equal(document.querySelector('#chapter-label').textContent, 'Sample chapter label');
  await go('login'); submit('#login-form', { role: 'System Administrator' }); await tick();
  assert.match(document.querySelector('main').textContent, /Access with intention/);
  click('[data-action="user-add"]'); submit('#workflow-form', { name: 'Sample reviewer', role: 'Report Viewer', active: 'Active' }); await tick();
  assert.ok(state.users.some(user => user.name === 'Sample reviewer'));
  await go('backups'); click('[data-action="backup"]'); submit('#workflow-form'); await tick();
  assert.equal(state.backups.length, 2);
  click('[data-action="restore"]'); submit('#workflow-form'); await tick();
  assert.equal(state.audit[0].action, 'Rehearse restore checklist');
  await go('donors'); assert.match(document.querySelector('main').textContent, /restricted/);
  await go('login'); submit('#login-form', { role: 'Report Viewer' }); await tick();
  await go('inventory'); assert.match(document.querySelector('main').textContent, /restricted/);
  await go('reports'); const preview = document.querySelector('#workspace-preview'); preview.value = 'error'; preview.dispatchEvent(new browser.Event('change', { bubbles: true }));
  assert.match(document.querySelector('main').textContent, /workspace is unavailable/); click('[data-action="ready"]');
  assert.match(document.querySelector('main').textContent, /Turn records/);
  click('[data-action="logout"]'); await tick(); assert.ok(document.querySelector('.hero'));
  await browser.happyDOM.close();
});
