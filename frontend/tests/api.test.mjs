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
