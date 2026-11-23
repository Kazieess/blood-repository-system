import { request } from './api.js';
import { availabilityView } from './public-views.js';
const main = document.querySelector('#main');
let controller; let timer; let expiryTimer;
async function refresh() {
  controller?.abort(); controller = new AbortController(); const current = controller;
  clearTimeout(timer); main.setAttribute('aria-busy', 'true');
  try {
    const result = await request('/api/v1/public/availability', { signal: current.signal });
    if (current.signal.aborted) return;
    clearTimeout(expiryTimer);
    main.innerHTML = availabilityView(result);
    if (result.state === 'available') expiryTimer = setTimeout(() => { main.innerHTML = availabilityView({ ...result, state: 'stale', data: [] }); }, Math.max(1, new Date(result.valid_until).getTime() - Date.now()));
  } catch (error) {
    if (current.signal.aborted) return;
    clearTimeout(expiryTimer);
    main.innerHTML = '<h1>Availability could not be refreshed</h1><p>Check your connection and try again. Current counts are unavailable.</p><button class="button secondary" id="refresh-availability">Try again</button>';
  } finally {
    if (!current.signal.aborted) { main.removeAttribute('aria-busy'); if (!document.hidden) timer = setTimeout(refresh, 60000); }
  }
}
main.addEventListener('click', event => { if (event.target.closest('#refresh-availability')) refresh(); });
document.addEventListener('visibilitychange', () => { if (document.hidden) { clearTimeout(timer); controller?.abort(); } else refresh(); });
window.addEventListener('pagehide', () => { controller?.abort(); clearTimeout(timer); clearTimeout(expiryTimer); });
window.addEventListener('pageshow', event => { if (event.persisted) refresh(); });
refresh();
