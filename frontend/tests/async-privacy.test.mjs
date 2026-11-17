import test from 'node:test';
import assert from 'node:assert/strict';
import { Window } from 'happy-dom';
import { WorkspaceOperations } from '../src/workspace-operations.js';

function setup() {
  const browser = new Window({ url: 'http://127.0.0.1:8000/workspace' });
  browser.document.body.innerHTML = '<main id="main"></main><dialog id="record-dialog"></dialog>';
  for (const key of ['window', 'document', 'location', 'Element', 'HTMLElement', 'HTMLInputElement', 'HTMLSelectElement', 'HTMLFormElement', 'HTMLDialogElement', 'FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
  let user = { id: 1, permissions: ['reports.read', 'donors.read'] };
  const notices = [];
  const context = { user: () => user, dialog: browser.document.querySelector('dialog'), toast: value => notices.push(value), remember: () => {}, refresh: async () => {}, navigate: () => {}, expired: () => { user = null; } };
  return { browser, context, notices, operations: new WorkspaceOperations(context), signOut: () => { user = null; } };
}
const response = body => new Response(JSON.stringify(body), { status: 200 });

test('late clinical-history response cannot reopen private content after sign-out', async () => {
  const { browser, operations, context, signOut } = setup();
  let resolve;
  globalThis.fetch = () => new Promise(done => { resolve = done; });
  operations.state = { id: 'donor-id', category: 'screening', data: { data: [{ id: 'entry-id' }] } };
  const trigger = browser.document.createElement('button');
  trigger.dataset.op = 'clinical-history'; trigger.dataset.id = 'entry-id';
  const pending = operations.command(trigger);
  operations.clear(); signOut();
  resolve(response({ data: [{ version: 1, snapshot: { source_reference: 'PRIVATE-FICTIONAL-SOURCE', payload: { notes: 'Restricted fictional note' } } }], meta: { total: 1, current_page: 1, last_page: 1 } }));
  await pending;
  assert.equal(context.dialog.open, false);
  assert.equal(context.dialog.innerHTML, '');
  await browser.close();
});

test('late report export cannot download after navigating away', async () => {
  const { browser, operations, notices } = setup();
  let resolve; let downloads = 0;
  globalThis.fetch = () => new Promise(done => { resolve = done; });
  const original = URL.createObjectURL; URL.createObjectURL = () => { downloads += 1; return 'blob:fictional'; };
  try {
    operations.state = { kind: 'inventory', params: new URLSearchParams(), data: { data: [] } };
    const trigger = browser.document.createElement('button'); trigger.dataset.op = 'report-export';
    const pending = operations.command(trigger);
    operations.clear();
    resolve(response({ csv: 'Private fictional report', filename: 'inventory.csv', row_count: 1 }));
    await pending;
    assert.equal(downloads, 0); assert.equal(notices.length, 0);
  } finally { URL.createObjectURL = original; await browser.close(); }
});

test('report navigation rejects unsupported kinds before requesting data', async () => {
  const { browser, operations } = setup();
  let requests = 0; globalThis.fetch = async () => { requests += 1; return response({ data: [] }); };
  await assert.rejects(operations.render('reports', '', new URLSearchParams('kind=donations')), error => error.status === 404);
  assert.equal(requests, 0);
  await browser.close();
});

test('public counts still expire while a refresh response is pending', async () => {
  const { browser } = setup();
  let calls = 0; let resolve; let expire;
  const nativeTimeout = globalThis.setTimeout; const nativeClear = globalThis.clearTimeout; const expiryToken = {};
  globalThis.setTimeout = (callback, delay, ...args) => delay > 60000 ? (expire = callback, expiryToken) : nativeTimeout(callback, delay, ...args);
  globalThis.clearTimeout = token => { if (token === expiryToken) expire = null; else nativeClear(token); };
  globalThis.fetch = async () => {
    if (++calls === 1) return response({ state: 'available', data: [{ blood_type: 'O+', component: 'QA component', location: 'QA center', units: 77 }], published_at: new Date().toISOString(), valid_until: new Date(Date.now() + 3600000).toISOString() });
    return new Promise(done => { resolve = done; });
  };
  const waitFor = async predicate => { for (let i = 0; i < 100; i++) { if (predicate()) return; await new Promise(done => setTimeout(done, 5)); } assert.ok(predicate()); };
  try {
    await import('../src/availability.js');
    await waitFor(() => browser.document.querySelector('.availability-grid'));
    browser.document.querySelector('#refresh-availability').click();
    await waitFor(() => resolve);
    assert.equal(typeof expire, 'function', 'refresh must retain the existing expiry timer');
    expire();
    assert.equal(browser.document.querySelector('.availability-grid'), null);
    assert.match(browser.document.querySelector('#main').textContent, /needs an update/);
    resolve(response({ state: 'unavailable', data: [] }));
    await waitFor(() => browser.document.querySelector('#main').textContent.includes('not published'));
  } finally { browser.dispatchEvent(new browser.Event('pagehide')); await browser.close(); globalThis.setTimeout = nativeTimeout; globalThis.clearTimeout = nativeClear; }
});
