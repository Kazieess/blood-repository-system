import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { Window } from 'happy-dom';
import { WorkspaceOperations } from '../src/workspace-operations.js';
import { loginView } from '../src/workspace-ui.js';

function setup() {
  const browser = new Window({ url: 'http://127.0.0.1:8000/workspace' });
  for (const key of ['window', 'document', 'location', 'Element', 'HTMLElement', 'HTMLInputElement', 'HTMLSelectElement', 'HTMLFormElement', 'HTMLDialogElement', 'FormData']) globalThis[key] = key === 'window' ? browser : browser[key];
  Object.defineProperty(globalThis, 'navigator', { value: browser.navigator, configurable: true });
  return browser;
}
const reply = data => new Response(JSON.stringify(data));

test('authenticated workspace renders its initial page without a CSRF round trip', async () => {
  const browser = setup(); const calls = [];
  browser.document.write((await readFile(new URL('../connected.html', import.meta.url), 'utf8')).replace(/<script[\s\S]*?<\/script>/g, '').replace(/<link[^>]+>/g, ''));
  globalThis.fetch = async path => {
    calls.push(path);
    if (path === '/api/v1/me') return reply({ data: { id: 1, name: 'Fictional reader', permissions: ['donors.read'] } });
    if (path.startsWith('/api/v1/donors?')) return reply({ data: [], meta: { total: 0, current_page: 1, last_page: 1 } });
    throw new Error('Unexpected startup request: ' + path);
  };
  try {
    await import('../src/workspace.js');
    assert.equal(calls.length, 2);
    assert.match(browser.document.querySelector('main').textContent, /Good records start here/);
    assert.equal(browser.document.querySelector('main').hasAttribute('aria-busy'), false);
  } finally { browser.dispatchEvent(new browser.Event('pagehide')); await browser.close(); }
});

test('donation and source-link pages start their independent reads together', async () => {
  for (const section of ['donations', 'trace']) {
    const browser = setup(); const pending = [];
    browser.document.body.innerHTML = '<dialog></dialog>';
    const operations = new WorkspaceOperations({ user: () => ({ id: 1, permissions: ['donors.read', 'inventory.read'] }), dialog: browser.document.querySelector('dialog') });
    globalThis.fetch = path => new Promise(resolve => { pending.push({ path, resolve }); });
    try {
      const result = operations.render(section, 'fictional-donor', new URLSearchParams());
      assert.equal(pending.length, 2, 'history must start before the donor response arrives');
      assert.equal(pending[0].path, '/api/v1/donors/fictional-donor');
      assert.match(pending[1].path, section === 'trace' ? /\/units\?/ : /\/donations\?/);
      pending[0].resolve(reply({ data: { id: 'fictional-donor', name: 'Fictional donor', can_update: false } }));
      pending[1].resolve(reply({ data: [], completed_count: 0, meta: { total: 0, current_page: 1, last_page: 1 } }));
      assert.match(await result, /Fictional donor/);
    } finally { operations.stopAlerts(); await browser.close(); }
  }
});

test('sign-in keeps the form beside its introduction and recovery help inside the form', async () => {
  const browser = setup();
  try {
    browser.document.body.innerHTML = loginView();
    const layout = browser.document.querySelector('.login-layout');
    assert.equal(layout.children.length, 2);
    assert.equal(layout.children[1].id, 'server-login');
    assert.ok(layout.querySelector('#server-login a[href="/recovery"]'));
  } finally { await browser.close(); }
});
