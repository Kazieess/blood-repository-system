import { api, ApiError } from './api.js';
import { escapeHtml as e, icon, initials } from './ui.js';
import * as view from './workspace-ui.js';
import * as editor from './workspace-forms.js';
import { WorkspaceOperations, toInstant } from './workspace-operations.js';
import { labels as operationLabels } from './operations-views.js';

const main = document.querySelector('#main');
const dialog = document.querySelector('#record-dialog');
if (!(main instanceof HTMLElement) || !(dialog instanceof HTMLDialogElement)) throw new Error('Workspace elements are missing.');
let user = null; let currentDonor = null; let editingDonor = null; let latestConflict = null;
let pendingKey = null; let initialValues = ''; let loadController; let generation = 0; let lastTrigger;
let loadedAudit = []; let toastTimer; let renderedHash = ''; let loginBusy = false;
const sectionLabels = { overview: 'Overview', donors: 'Donor registry', audit: 'Audit trail', guide: 'Workspace guide', ...operationLabels };
const allowed = permission => view.has(user, permission);
const operations = new WorkspaceOperations({ user: () => user, dialog, toast, navigate, refresh: () => render(), expired: () => clearSession('Your session ended. Sign in again to continue.'), remember: trigger => { lastTrigger = trigger; } });
function route() {
  const [path, query = ''] = (location.hash.slice(1) || '/overview').split('?');
  const parts = path.split('/').filter(Boolean);
  return { section: parts[0] || 'overview', id: parts[1] || '', parts, params: new URLSearchParams(query) };
}
function navigate(path) { const target = '#/' + path; if (location.hash === target) render(); else location.hash = target; }
function toast(message) {
  const region = document.querySelector('#toast');
  document.querySelector('#toast-message').textContent = message; region.hidden = false;
  clearTimeout(toastTimer); toastTimer = setTimeout(() => { if (region.isConnected) region.hidden = true; }, 8000);
}
function errorText(error) { return error instanceof ApiError ? error.message + (error.requestId ? ` Reference: ${error.requestId}` : '') : 'The request could not be completed. Try again.'; }
function hydrate() { document.querySelectorAll('[data-icon]').forEach(item => { item.innerHTML = icon(item.dataset.icon); }); }
function updateShell(section) {
  document.body.classList.toggle('public-view', !user);
  document.querySelector('#account-label').textContent = user?.name ?? 'Account';
  document.querySelector('#account-initials').textContent = initials(user?.name ?? 'Staff account');
  document.querySelector('#workspace-title').textContent = sectionLabels[section] ?? 'Workspace';
  document.querySelector('.sidebar .brand').href = '#/overview';
  document.querySelectorAll('[data-feature]').forEach(link => { link.hidden = !allowed(link.dataset.feature); });
  document.querySelectorAll('[data-section]').forEach(link => { if (link.dataset.section === section) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current'); });
  hydrate();
  operations.watchAlerts();
}
function busy(form) { return Boolean(form?.querySelector('[aria-busy="true"]')); }
function isDirty() { const form = dialog.querySelector('#server-donor'); return Boolean(form && JSON.stringify(editor.formValues(form)) !== initialValues) || operations.isDirty(); }
function resetEditor() { editingDonor = null; latestConflict = null; pendingKey = null; initialValues = ''; }
function clearSession(message) {
  user = null; currentDonor = null; loadedAudit = []; generation += 1; loadController?.abort();
  operations.clear();
  resetEditor(); lastTrigger = null; dialog.close(); dialog.innerHTML = ''; render(); if (message) toast(message);
}
async function render(focus = true) {
  operations.clear();
  const requestId = ++generation; loadController?.abort(); loadController = new AbortController();
  const current = route(); currentDonor = null; loadedAudit = []; renderedHash = location.hash;
  updateShell(current.section);
  if (!user) {
    main.removeAttribute('aria-busy');
    main.innerHTML = view.loginView(); document.title = 'Staff sign-in | Blood Repository';
    if (focus) main.querySelector('input')?.focus(); return;
  }
  main.setAttribute('aria-busy', 'true'); main.innerHTML = view.loading();
  try {
    let html;
    if (current.parts.length > 2 || current.id && !['donors', 'donations', 'inventory', 'assignments', 'clinical'].includes(current.section) || ['donations', 'clinical'].includes(current.section) && !current.id) throw new ApiError('This page does not exist.', 404);
    if (current.section === 'login' || current.section === 'access') { navigate('overview'); return; }
    if (current.section === 'overview') {
      const [donors, activity] = await Promise.all([
        allowed('donors.read') ? api.donors(new URLSearchParams({ per_page: '5' }), loadController.signal) : null,
        allowed('audit.read') ? api.audit(new URLSearchParams({ per_page: '5' }), loadController.signal) : null,
      ]);
      if (requestId !== generation) return;
      loadedAudit = activity?.data ?? []; html = view.overview(user, donors, activity);
    } else if (current.section === 'donors') {
      if (!allowed('donors.read')) throw new ApiError('Your account cannot access donor records.', 403);
      if (current.id) {
        const result = await api.donor(current.id, loadController.signal); if (requestId !== generation) return;
        currentDonor = result.data; html = view.profile(currentDonor) + operations.profileLinks(currentDonor);
      } else html = view.registry(user, await api.donors(current.params, loadController.signal), current.params);
    } else if (current.section === 'audit') {
      if (!allowed('audit.read')) throw new ApiError('Your account cannot access the audit trail.', 403);
      const result = await api.audit(current.params, loadController.signal); if (requestId !== generation) return;
      loadedAudit = result.data; html = view.auditView(result, current.params);
    } else if (current.section === 'guide') html = view.guide(user) + operations.links();
    else if (operations.supports(current.section)) html = await operations.render(current.section, current.id, current.params, loadController.signal);
    else throw new ApiError('This page does not exist.', 404);
    if (requestId !== generation) return;
    main.innerHTML = html; document.title = `${currentDonor?.name ?? sectionLabels[current.section] ?? 'Workspace'} | Blood Repository`;
    if (focus) main.focus();
  } catch (error) {
    if (requestId !== generation || error?.name === 'AbortError') return;
    if (error.status === 401 || error.status === 419) { clearSession('Your session ended. Sign in again to continue.'); return; }
    main.innerHTML = view.failure(error instanceof ApiError ? error : new ApiError('The workspace could not read the server response. Try again.', 0)); if (focus) main.focus();
  } finally { if (requestId === generation) main.removeAttribute('aria-busy'); }
}
function openRecord(edit, trigger) {
  if (edit && !currentDonor?.can_update || !edit && !allowed('donors.create')) { toast('Your account cannot make this change.'); return; }
  lastTrigger = trigger; editingDonor = edit ? { ...currentDonor } : null; latestConflict = null;
  pendingKey = crypto.randomUUID(); dialog.innerHTML = editor.donorForm(editingDonor);
  initialValues = JSON.stringify(editor.formValues(dialog.querySelector('form'))); dialog.showModal();
}
function closeRequested() {
  if (dialog.querySelector('.operation-form') || operations.isDirty()) { operations.requestClose(); return; }
  const form = dialog.querySelector('#server-donor'); if (busy(dialog)) return;
  if (isDirty()) {
    const panel = form.querySelector('#discard-panel'); panel.hidden = false;
    panel.querySelector('[data-command="keep-editing"]').focus(); return;
  }
  dialog.close();
}
async function refreshSession() {
  try {
    const result = await api.me(); user = result.data; dialog.close(); await render(); toast('Your account access has been refreshed.');
  } catch (error) { if (error.status === 401 || error.status === 419) clearSession('Your session ended. Sign in again.'); else toast(errorText(error)); }
}
async function loadLatest(trigger) {
  const form = dialog.querySelector('#server-donor'); if (!form?.dataset.id || busy(dialog)) return;
  trigger.disabled = true; trigger.setAttribute('aria-busy', 'true');
  try {
    const result = await api.donor(form.dataset.id); latestConflict = result.data;
    if (!latestConflict.can_update) { clearSession('Your access changed. Sign in again before continuing.'); return; }
    editor.showComparison(form, latestConflict, editor.formValues(form));
  } catch (error) { if (error.status === 401 || error.status === 419) clearSession('Your session ended. Sign in again.'); else form.querySelector('.form-error').textContent = errorText(error); }
  finally { trigger.disabled = false; trigger.removeAttribute('aria-busy'); }
}
function applyLatest() {
  const form = dialog.querySelector('#server-donor'); if (!form || !latestConflict) return;
  const draft = editor.formValues(form);
  for (const [key] of editor.donorFields) form.elements.namedItem(key).value = form.elements.namedItem('resolve_' + key).value === 'draft' ? draft[key] : latestConflict[key];
  form.elements.namedItem('version').value = latestConflict.version; editingDonor = { ...latestConflict }; latestConflict = null;
  editor.resetErrors(form); editor.showDetails(form);
  form.querySelector('.workspace-form-status').textContent = 'Comparison choices applied. Review the entries and reason before saving.';
}
async function command(trigger) {
  if (busy(dialog) && ['close', 'discard', 'back-to-details', 'load-conflict', 'apply-conflict', 'keep-editing', 'logout'].includes(trigger.dataset.command)) return;
  switch (trigger.dataset.command) {
    case 'create': openRecord(false, trigger); break;
    case 'edit': openRecord(true, trigger); break;
    case 'close': closeRequested(); break;
    case 'discard': dialog.close(); break;
    case 'keep-editing': dialog.querySelector('#discard-panel').hidden = true; dialog.querySelector('[type="submit"]')?.focus(); break;
    case 'back-to-details': editor.showDetails(dialog.querySelector('form')); break;
    case 'load-conflict': await loadLatest(trigger); break;
    case 'apply-conflict': applyLatest(); break;
    case 'retry': await render(); break;
    case 'refresh-session': await refreshSession(); break;
    case 'dismiss-toast': document.querySelector('#toast').hidden = true; break;
    case 'toggle-password': {
      const field = document.querySelector('#staff-password'); const show = field.type === 'password'; field.type = show ? 'text' : 'password';
      trigger.textContent = show ? 'Hide' : 'Show'; trigger.setAttribute('aria-pressed', String(show)); break;
    }
    case 'copy-reference':
      try { if (!navigator.clipboard || !currentDonor) throw new Error('Clipboard unavailable'); await navigator.clipboard.writeText(currentDonor.id); toast('Donor reference copied.'); }
      catch { toast('Could not copy automatically. Select the displayed reference to copy it.'); } break;
    case 'account': lastTrigger = trigger; dialog.innerHTML = view.accountDetails(user); dialog.showModal(); break;
    case 'audit-details': {
      const item = loadedAudit.find(record => String(record.id) === trigger.dataset.id);
      if (item) { lastTrigger = trigger; dialog.innerHTML = view.auditDetails(item); dialog.showModal(); } break;
    }
    case 'logout':
      if (busy(dialog)) return; trigger.disabled = true;
      try { await api.logout(); clearSession('Signed out.'); location.hash = '#/login'; }
      catch (error) { if (error.status === 401 || error.status === 419) clearSession('Your session has ended.'); else toast(errorText(error)); }
      finally { trigger.disabled = false; } break;
  }
}
async function submitLogin(form) {
  if (loginBusy) return; loginBusy = true; const button = form.querySelector('[type="submit"]'); button.disabled = true; button.setAttribute('aria-busy', 'true');
  const region = form.querySelector('.form-error'); region.textContent = '';
  try {
    const values = Object.fromEntries(new FormData(form)); const result = await api.login(values.email, values.password); user = result.data; form.reset();
    const target = route(); if (!location.hash || target.section === 'login' || target.section === 'access') navigate('overview'); else await render();
  } catch (error) {
    region.textContent = errorText(error); editor.showFieldErrors(form, error.errors ?? {});
    form.elements.namedItem('password').value = ''; region.tabIndex = -1; region.focus();
  } finally { loginBusy = false; button.disabled = false; button.removeAttribute('aria-busy'); }
}
async function submitDonor(form) {
  if (busy(form) || form.dataset.stage === 'conflict') return;
  editor.resetErrors(form); const values = editor.formValues(form); const errors = editor.validateFields(form, values);
  if (Object.keys(errors).length) { editor.showDetails(form); editor.showFieldErrors(form, errors); return; }
  if (form.dataset.stage === 'details') { editor.showReview(form, values, editingDonor); return; }
  const button = form.querySelector('[type="submit"]'); button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Saving…';
  try {
    const result = form.dataset.id ? await api.updateDonor(form.dataset.id, values) : await api.createDonor(values, pendingKey);
    resetEditor(); dialog.close(); navigate(`donors/${encodeURIComponent(result.data.id)}`); toast('Saved to the database.');
  } catch (error) {
    if (error.status === 401 || error.status === 419) { clearSession('Your session expired. Sign in again before saving.'); return; }
    const region = form.querySelector('.form-error'); region.textContent = errorText(error);
    if (error.status === 409 && form.dataset.id) editor.showConflict(form);
    else if (error.status === 409) { region.textContent += ' This registration reference was already used. Check the registry before starting another registration.'; region.focus(); }
    else if (error.status === 422) { editor.showDetails(form); editor.showFieldErrors(form, error.errors ?? {}); }
    else { if (!form.dataset.id && error.status === 0) region.textContent += ' The save could not be confirmed. Retry this unchanged entry to avoid creating a duplicate.'; region.focus(); }
  } finally { button.disabled = false; button.removeAttribute('aria-busy'); if (form.dataset.stage === 'review') button.textContent = form.dataset.id ? 'Save correction' : 'Register donor'; }
}
document.addEventListener('click', event => {
  if (!(event.target instanceof Element)) return; const trigger = event.target.closest('[data-command]'); if (!(trigger instanceof HTMLElement)) return;
  command(trigger).catch(error => toast(errorText(error)));
});
document.addEventListener('submit', event => {
  const form = event.target; if (!(form instanceof HTMLFormElement)) return; event.preventDefault(); if (!form.reportValidity()) return;
  if (form.matches('.operation-form')) return;
  if (form.dataset.filterForm) {
    const query = new URLSearchParams([...new FormData(form)].filter(([, value]) => String(value).trim()));
    if (form.dataset.filterForm === 'inventory' && query.get('expires_before')) query.set('expires_before', toInstant(query.get('expires_before')));
    navigate(`${form.dataset.filterForm}?${query}`); return;
  }
  const operation = form.id === 'server-login' ? submitLogin(form) : form.id === 'server-donor' ? submitDonor(form) : null;
  operation?.catch(error => toast(errorText(error)));
});
dialog.addEventListener('cancel', event => { if (busy(dialog) || isDirty()) { event.preventDefault(); closeRequested(); } });
dialog.addEventListener('close', () => {
  operations.closed();
  resetEditor(); dialog.innerHTML = ''; if (lastTrigger?.isConnected) lastTrigger.focus(); lastTrigger = null;
  if (user && location.hash !== renderedHash) render();
});
window.addEventListener('hashchange', () => { if (!dialog.open) render(); });
window.addEventListener('beforeunload', event => { if (isDirty() || busy(dialog)) { event.preventDefault(); event.returnValue = ''; } });
function networkStatus() {
  const region = document.querySelector('#connection-notice'); region.hidden = navigator.onLine !== false;
  region.textContent = 'You appear to be offline. Unsaved entries stay in this tab; no save is confirmed until the server responds.';
}
window.addEventListener('offline', networkStatus);
window.addEventListener('online', () => { networkStatus(); toast('Connection restored. Retry or refresh the current view when ready.'); });
networkStatus(); hydrate();
try { const result = await api.me(); user = result.data; await api.csrf(); await render(false); }
catch (error) { user = null; await render(false); if (error.status !== 401) toast(errorText(error)); }
