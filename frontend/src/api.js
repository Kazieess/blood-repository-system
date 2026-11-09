// Same-origin cookie session client. Never imports the fictional demo store.
export class ApiError extends Error {
  constructor(message, status, errors = {}, requestId = null) {
    super(message); this.name = 'ApiError'; this.status = status; this.errors = errors; this.requestId = requestId;
  }
}
function csrfToken() {
  const cookie = document.cookie.split('; ').find(value => value.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}
export async function request(path, { method = 'GET', body, signal, headers: extraHeaders = {}, timeoutMs = 15000 } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...extraHeaders };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (!['GET', 'HEAD'].includes(method)) headers['X-XSRF-TOKEN'] = csrfToken();
  const controller = new AbortController();
  let timedOut = false;
  const cancel = () => controller.abort();
  if (signal?.aborted) controller.abort(); else signal?.addEventListener('abort', cancel, { once: true });
  const timer = setTimeout(() => { timedOut = true; controller.abort(); }, timeoutMs);
  try {
    const response = await fetch(path, { method, headers, credentials: 'same-origin', body: body === undefined ? undefined : JSON.stringify(body), signal: controller.signal });
    let payload = null;
    if (response.status !== 204) {
      try { payload = await response.json(); }
      catch (error) {
        if (controller.signal.aborted) throw error;
        throw new ApiError('The server returned an unexpected response.', response.status, {}, response.headers.get('X-Request-ID'));
      }
    }
    if (!response.ok) throw new ApiError(payload?.message ?? 'The request could not be completed.', response.status, payload?.errors ?? {}, payload?.request_id ?? response.headers.get('X-Request-ID'));
    return payload;
  } catch (error) {
    if (error instanceof ApiError) throw error;
    if (timedOut) throw new ApiError('The server took too long to respond. Try again when your connection is stable.', 0);
    if (signal?.aborted || error instanceof Error && error.name === 'AbortError') throw error;
    throw new ApiError('Could not connect. Check your connection and try again.', 0);
  } finally {
    clearTimeout(timer); signal?.removeEventListener('abort', cancel);
  }
}
export const api = {
  csrf: () => request('/sanctum/csrf-cookie'),
  me: () => request('/api/v1/me'),
  async login(email, password) { await this.csrf(); return request('/api/v1/auth/login', { method: 'POST', body: { email, password } }); },
  logout: () => request('/api/v1/auth/logout', { method: 'POST' }),
  donors: (params = new URLSearchParams(), signal) => request(`/api/v1/donors?${params}`, { signal }),
  donor: (id, signal) => request(`/api/v1/donors/${encodeURIComponent(id)}`, { signal }),
  createDonor: (body, key) => request('/api/v1/donors', { method: 'POST', body, headers: { 'Idempotency-Key': key } }),
  updateDonor: (id, body) => request(`/api/v1/donors/${encodeURIComponent(id)}`, { method: 'PATCH', body }),
  audit: (params = new URLSearchParams(), signal) => request(`/api/v1/audit-logs?${params}`, { signal }),
};
