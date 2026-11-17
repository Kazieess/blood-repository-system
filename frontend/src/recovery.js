import { api, request } from './api.js';
import { input } from './operations-views.js';
const main = document.querySelector('#main');
const params = new URLSearchParams(location.hash.slice(1));
const token = params.get('token'); const email = params.get('email') || ''; const change = location.hash === '#change';
if (token) history.replaceState(null, '', location.pathname);
async function start() {
  try {
    const status = await request('/api/v1/auth/recovery');
    if (change) await api.me();
    main.innerHTML = '<h1>' + (change ? 'Change your password' : token ? 'Choose a new password' : 'Recover your account') + '</h1><p>Use at least 12 characters, uppercase and lowercase letters, a number and a symbol.</p>' + (!token && !change && !status.email_recovery_enabled ? '<p class="notice">Email recovery is not configured. Contact your System Administrator for assistance. No email has been sent.</p>' : '<form class="recovery-form">' + (change ? input('current_password', 'Current password', '', 'password', 'required autocomplete="current-password"') : input('email', 'Staff email address', email, 'email', 'required maxlength="255" autocomplete="email"')) + (token || change ? input('password', 'New password', '', 'password', 'required minlength="12" maxlength="128" autocomplete="new-password"') + input('password_confirmation', 'Confirm new password', '', 'password', 'required minlength="12" maxlength="128" autocomplete="new-password"') : '') + '<p class="form-error" role="alert" tabindex="-1"></p><button class="button primary">' + (token || change ? 'Save password' : 'Request reset email') + '</button></form>') + '<p><a href="/workspace">Return to staff sign-in</a></p>';
  } catch (error) {
    main.innerHTML = '<h1>Account recovery unavailable</h1><p>Sign in before changing your password, or retry when connected.</p><button class="button secondary" id="retry-recovery">Try again</button><p><a href="/workspace">Staff sign-in</a></p>';
  }
}
main.addEventListener('click', event => { if (event.target.closest('#retry-recovery')) start(); });
main.addEventListener('submit', async event => {
  event.preventDefault(); const form = event.target; if (!form.reportValidity()) return;
  const button = form.querySelector('button'); if (button.disabled) return;
  button.disabled = true; button.setAttribute('aria-busy', 'true'); const errorRegion = form.querySelector('.form-error'); errorRegion.textContent = '';
  try {
    const body = Object.fromEntries(new FormData(form)); if (token) body.token = token;
    await api.csrf();
    const result = await request('/api/v1/auth/' + (change ? 'password' : token ? 'reset-password' : 'forgot-password'), { method: 'POST', body });
    form.reset(); main.innerHTML = '<h1>Request completed</h1><p role="status" id="recovery-result"></p><a href="/workspace">Return to staff sign-in</a>'; main.querySelector('#recovery-result').textContent = result.message; main.focus();
  } catch (error) {
    errorRegion.textContent = (error.message || 'The request could not complete.') + ' ' + Object.values(error.errors || {}).flat().join(' '); errorRegion.focus(); form.querySelectorAll('[type="password"]').forEach(field => { field.value = ''; });
  } finally { button.disabled = false; button.removeAttribute('aria-busy'); }
});
start();
