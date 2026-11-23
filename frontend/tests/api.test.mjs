import test from 'node:test';
import assert from 'node:assert/strict';
import { request, ApiError } from '../src/api.js';

globalThis.document = { cookie: 'XSRF-TOKEN=abc%2B123' };
test('API client sends decoded CSRF for cookie-authenticated writes', async () => {
  let stored = null;
  globalThis.fetch = async (path, options) => { assert.equal(options.headers['X-XSRF-TOKEN'], 'abc+123'); assert.equal(options.credentials, 'same-origin'); stored = JSON.parse(options.body); return new Response(JSON.stringify({ data: stored }), { status: 201 }); };
  const result = await request('/api/v1/donors', { method: 'POST', body: { name: 'Fictional' } });
  assert.equal(result.data.name, 'Fictional'); assert.equal(stored.name, 'Fictional');
});
test('API client returns structured validation errors and correlation reference', async () => {
  globalThis.fetch = async () => new Response(JSON.stringify({ message: 'Check fields.', errors: { name: ['Name required.'] }, request_id: 'request-reference' }), { status: 422 });
  await assert.rejects(request('/api/v1/donors'), error => error instanceof ApiError && error.status === 422 && error.requestId === 'request-reference' && error.errors.name[0] === 'Name required.');
});
test('API client handles non-JSON service failures safely', async () => {
  globalThis.fetch = async () => new Response('<html>Server failed</html>', { status: 503 });
  await assert.rejects(request('/api/v1/donors'), error => error instanceof ApiError && error.status === 503 && !error.message.includes('<html>'));
});
test('API timeout stops an unresponsive request without retrying a write', async () => {
  let calls = 0;
  globalThis.fetch = (path, { signal }) => { calls++; return new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true })); };
  await assert.rejects(request('/api/v1/donors', { method: 'POST', body: { name: 'Fictional' }, timeoutMs: 5 }), error => error instanceof ApiError && error.status === 0 && /too long/.test(error.message));
  assert.equal(calls, 1);
});
test('navigation cancellation remains an abort instead of a service error', async () => {
  const controller = new AbortController();
  globalThis.fetch = (path, { signal }) => new Promise((resolve, reject) => signal.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')), { once: true }));
  const operation = request('/api/v1/donors', { signal: controller.signal }); controller.abort();
  await assert.rejects(operation, error => error.name === 'AbortError' && !(error instanceof ApiError));
});

test('read requests do not wait for CSRF, while simultaneous first writes share preparation', async () => {
  document.cookie = '';
  const calls = []; let completeCsrf;
  globalThis.fetch = async (path, options) => {
    calls.push(path);
    if (path === '/sanctum/csrf-cookie') return new Promise(resolve => { completeCsrf = () => { document.cookie = 'XSRF-TOKEN=prepared%2Btoken'; resolve(new Response(null, { status: 204 })); }; });
    if (options.method === 'POST') assert.equal(options.headers['X-XSRF-TOKEN'], 'prepared+token');
    return new Response(JSON.stringify({ data: [] }));
  };
  try {
    await request('/api/v1/donors');
    assert.deepEqual(calls, ['/api/v1/donors']);
    const first = request('/api/v1/donors', { method: 'POST', body: { name: 'Fictional first' } });
    const second = request('/api/v1/inventory', { method: 'POST', body: { reference: 'Fictional second' } });
    assert.deepEqual(calls, ['/api/v1/donors', '/sanctum/csrf-cookie']);
    completeCsrf(); await Promise.all([first, second]);
    assert.equal(calls.filter(path => path === '/sanctum/csrf-cookie').length, 1);
    assert.equal(calls.length, 4);
  } finally { document.cookie = 'XSRF-TOKEN=abc%2B123'; }
});
test('failed CSRF preparation never sends a write and can be retried', async () => {
  document.cookie = ''; let calls = 0;
  globalThis.fetch = async path => { calls++; assert.equal(path, '/sanctum/csrf-cookie'); return new Response(JSON.stringify({ message: 'Unavailable' }), { status: 503 }); };
  try {
    await assert.rejects(request('/api/v1/donors', { method: 'POST', body: {} }), error => error.status === 503);
    await assert.rejects(request('/api/v1/donors', { method: 'POST', body: {} }), error => error.status === 503);
    assert.equal(calls, 2);
  } finally { document.cookie = 'XSRF-TOKEN=abc%2B123'; }
});

test('a cancelled or timed-out write leaves shared CSRF preparation available to another caller', async () => {
  for (const mode of ['cancel', 'timeout']) {
    document.cookie = '';
    const calls = []; let completeCsrf; let guard;
    const controller = new AbortController();
    globalThis.fetch = async (path, options) => {
      calls.push(path);
      if (path === '/sanctum/csrf-cookie') return new Promise(resolve => {
        completeCsrf = () => { document.cookie = 'XSRF-TOKEN=prepared'; resolve(new Response(null, { status: 204 })); };
      });
      assert.equal(options.headers['X-XSRF-TOKEN'], 'prepared');
      return new Response(JSON.stringify({ data: [] }));
    };
    const abandoned = request('/api/v1/abandoned', { method: 'POST', body: {}, signal: controller.signal, timeoutMs: mode === 'timeout' ? 5 : 15000 });
    const remaining = request('/api/v1/remaining', { method: 'POST', body: {} });
    const rejected = assert.rejects(abandoned, error => mode === 'cancel' ? error.name === 'AbortError' : error instanceof ApiError && /too long/.test(error.message));
    try {
      if (mode === 'cancel') controller.abort();
      await Promise.race([rejected, new Promise((resolve, reject) => { guard = setTimeout(() => reject(new Error('Caller waited for shared CSRF after cancellation.')), 1000); })]);
      assert.deepEqual(calls, ['/sanctum/csrf-cookie']);
      completeCsrf(); await remaining;
      assert.deepEqual(calls, ['/sanctum/csrf-cookie', '/api/v1/remaining']);
    } finally {
      clearTimeout(guard); completeCsrf(); await Promise.allSettled([abandoned, remaining, rejected]);
      document.cookie = 'XSRF-TOKEN=abc%2B123';
    }
  }
});
