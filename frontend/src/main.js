import { state, can, roles, permissions, signIn, log, required, validDate, addDonor, updateDonor, recordEligibility, addDonation, addUnit, changeUnit, publishSnapshot, reportRows, csv, nextId, requirePermission } from './model.js';
import { icon, donorTable, escapeHtml as e } from './ui.js';
import { overview, registry, profile, missing } from './views.js';
import { home, availability, availabilityResults, help, privacy, login, recovery } from './public.js';
import * as operations from './operations.js';
import { denied, previewState } from './components.js';
import { workflow, modal } from './forms.js';

const main = document.querySelector('#main');
const dialog = document.querySelector('#workflow-dialog');
const guide = document.querySelector('#guide-dialog');
if (!(main instanceof HTMLElement) || !(dialog instanceof HTMLDialogElement) || !(guide instanceof HTMLDialogElement)) throw new Error('Required interface elements are missing.');
const filters = { query: '', blood: '', status: '', page: 1 };
let route = { section: 'home', id: '', params: new URLSearchParams() };
let returnRoute = '';
let lastTrigger = null;
let toastTimer;
const publicRoutes = ['home', 'availability', 'help', 'privacy', 'login', 'recovery'];
const titles = { home: 'Home', availability: 'Blood availability', help: 'Help & information', privacy: 'Privacy', login: 'Staff demo', overview: 'Overview', donors: 'Donor registry', inventory: 'Blood inventory', reports: 'Reports & analytics', notifications: 'Notifications', users: 'Users & roles', audit: 'Audit trail', backups: 'Backup & recovery', settings: 'Settings' };

function icons() { document.querySelectorAll('[data-icon]').forEach(element => { element.innerHTML = icon(element.dataset.icon ?? 'info'); }); }
function notify(message) {
  const toast = document.querySelector('#toast'); toast.textContent = message; toast.hidden = false;
  clearTimeout(toastTimer); toastTimer = setTimeout(() => { toast.hidden = true; }, 6500);
}
function navigate(path) {
  const hash = `#/${path}`;
  if (location.hash === hash) render(); else location.hash = hash;
}
function renderResults() {
  const container = document.querySelector('#registry-results'); if (!container) return;
  const query = filters.query.trim().toLowerCase();
  const rows = state.donors.filter(donor => `${donor.name} ${donor.id} ${donor.area}`.toLowerCase().includes(query) && (!filters.blood || donor.blood === filters.blood) && (!filters.status || donor.status === filters.status));
  const pages = Math.max(1, Math.ceil(rows.length / 6)); filters.page = Math.min(filters.page, pages);
  const start = (filters.page - 1) * 6;
  container.innerHTML = rows.length ? donorTable(rows.slice(start, start + 6)) : '<div class="empty-state"><h2>No matching donors</h2><p>Try another search or clear your filters.</p><button class="button secondary" data-action="clear">Clear filters</button></div>';
  document.querySelector('#result-count').textContent = `${rows.length} donors found`;
  document.querySelector('#page-description').textContent = rows.length ? `Showing ${start + 1}–${Math.min(start + 6, rows.length)} of ${rows.length}` : 'No records to display';
  document.querySelector('#previous-page').disabled = filters.page <= 1; document.querySelector('#next-page').disabled = filters.page >= pages;
}
function render(focus = true) {
  const [path, query = ''] = (location.hash.slice(1) || '/home').split('?');
  const parts = path.split('/').filter(Boolean); const section = parts[0] || 'home';
  const params = new URLSearchParams(query); const id = parts[1] ?? '';
  const previous = route; route = { section, id, params };
  const publicPage = publicRoutes.includes(section) || !state.session;
  document.body.classList.toggle('public-view', publicPage);
  document.body.classList.remove('navigation-open');
  document.querySelector('#mobile-menu-toggle').setAttribute('aria-expanded', 'false');
  let title = titles[section] ?? 'Page not found';
  if (!publicRoutes.includes(section) && !state.session) { returnRoute = location.hash.slice(2); main.innerHTML = login(); title = 'Staff demo'; }
  else if (!publicRoutes.includes(section) && !can(section)) main.innerHTML = denied();
  else if (!publicRoutes.includes(section) && state.preview !== 'ready') main.innerHTML = previewState(state.preview);
  else if (parts.length > 2 || (id && !['donors', 'inventory'].includes(section))) main.innerHTML = missing();
  else switch (section) {
    case 'home': main.innerHTML = home(); break;
    case 'availability': main.innerHTML = availability(); break;
    case 'help': main.innerHTML = help(); break;
    case 'privacy': main.innerHTML = privacy(); break;
    case 'login': main.innerHTML = login(); break;
    case 'recovery': main.innerHTML = recovery(); title = 'Recover access'; break;
    case 'overview': main.innerHTML = overview(state.donors); break;
    case 'donors':
      if (id) { const donor = state.donors.find(item => item.id === id); main.innerHTML = donor ? profile(donor, params.get('tab')) : missing(); title = donor?.name ?? 'Donor not found'; }
      else { if (previous.section !== 'donors' || previous.params.get('status') !== params.get('status')) { filters.status = params.get('status') ?? ''; filters.page = 1; } main.innerHTML = registry(filters); renderResults(); }
      break;
    case 'inventory': { const unit = state.units.find(item => item.id === id); main.innerHTML = id ? unit ? operations.unitDetail(unit) : missing() : operations.inventory(params); break; }
    default: main.innerHTML = operations[section] ? operations[section](params) : missing();
  }
  document.title = `${title} | Blood Repository`;
  document.querySelector('#breadcrumb').textContent = title;
  document.querySelectorAll('[data-nav]').forEach(link => { if (link.dataset.nav === section) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current'); });
  document.querySelectorAll('[data-permission]').forEach(link => { link.hidden = !can(link.dataset.permission); });
  document.querySelector('#session-role').textContent = state.session?.role ?? 'Staff demo';
  document.querySelector('#workspace-preview').value = state.preview;
  document.querySelector('#chapter-label').textContent = state.settings.chapter;
  document.querySelector('.sidebar .brand').href = `#/${state.session ? permissions[state.session.role][0] : 'home'}`;
  icons();
  if (focus) { main.focus(); window.scrollTo(0, 0); }
}
function openWorkflow(kind, id, trigger) {
  const section = { register: 'donors', 'donor-edit': 'donors', eligibility: 'donors', donation: 'donors', 'unit-add': 'inventory', 'unit-verify': 'inventory', 'unit-change': 'inventory', publish: 'inventory', compose: 'notifications', 'user-add': 'users', 'user-edit': 'users', backup: 'backups', restore: 'backups' }[kind];
  if (section) requirePermission(section);
  const html = workflow(kind, id); if (!html) throw new Error('That sample record is no longer available.');
  lastTrigger = trigger; dialog.innerHTML = html; dialog.showModal();
}
function download(rows, name, reference) {
  if (!rows.length) { notify('No matching records to export.'); return; }
  const url = URL.createObjectURL(new Blob(['\uFEFF' + csv(rows)], { type: 'text/csv;charset=utf-8;' }));
  const anchor = document.createElement('a'); anchor.href = url; anchor.download = name; anchor.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000); log('Export sample CSV', reference); notify('Sample CSV downloaded.');
}
document.addEventListener('click', event => {
  if (!(event.target instanceof Element)) return;
  const close = event.target.closest('[data-close]');
  if (close) { const target = document.getElementById(close.dataset.close); if (target instanceof HTMLDialogElement) target.close(); return; }
  const trigger = event.target.closest('[data-action]'); if (!(trigger instanceof HTMLElement)) return;
  const action = trigger.dataset.action; const id = trigger.dataset.id ?? '';
  try {
    if (['register', 'donor-edit', 'eligibility', 'donation', 'unit-add', 'unit-verify', 'unit-change', 'publish', 'compose', 'user-add', 'user-edit', 'backup', 'restore'].includes(action)) { openWorkflow(action, id, trigger); return; }
    switch (action) {
      case 'guide': lastTrigger = trigger; guide.showModal(); break;
      case 'password': { const field = document.querySelector('#sample-password'); if (field instanceof HTMLInputElement) { const show = field.type === 'password'; field.type = show ? 'text' : 'password'; trigger.textContent = show ? 'Hide' : 'Show'; trigger.setAttribute('aria-pressed', String(show)); } break; }
      case 'menu': document.body.classList.toggle('navigation-open'); trigger.setAttribute('aria-expanded', String(document.body.classList.contains('navigation-open'))); break;
      case 'logout': state.session = null; state.preview = 'ready'; returnRoute = ''; navigate('home'); notify('Exited the demo workspace.'); break;
      case 'ready': state.preview = 'ready'; render(); break;
      case 'clear': Object.assign(filters, { query: '', blood: '', status: '', page: 1 }); main.innerHTML = registry(filters); renderResults(); document.querySelector('#donor-search').focus(); break;
      case 'previous': filters.page = Math.max(1, filters.page - 1); renderResults(); break;
      case 'next': filters.page += 1; renderResults(); break;
      case 'public-retry': state.publicMode = 'normal'; render(); break;
      case 'report-export': requirePermission('reports'); download(reportRows(id, route.params.get('from') ?? '', route.params.get('to') ?? ''), `sample-${id}.csv`, 'Reports'); break;
      case 'audit-export': requirePermission('audit'); download([...state.audit], 'sample-audit.csv', 'Audit trail'); break;
      case 'message': {
        requirePermission('notifications'); const item = state.notifications.find(message => message.id === id); if (!item) throw new Error('Message not found.');
        lastTrigger = trigger; dialog.innerHTML = modal('Sample notification', 'message-action', `<dl class="details-grid full"><div><dt>Recipient</dt><dd>${e(item.recipient)}</dd></div><div><dt>Message</dt><dd>${e(item.message)}</dd></div><div><dt>Status</dt><dd>${e(item.status)}</dd></div></dl><p class="notice full">This simulates ${item.status === 'Failed' ? 'requeueing a failed message' : 'a delivery result'}. No SMS is sent.</p>`, item.status === 'Simulated delivery' ? '' : item.status === 'Failed' ? 'Requeue sample message' : 'Record simulated delivery', id); dialog.showModal(); break;
      }
    }
  } catch (error) { notify(error instanceof Error ? error.message : 'The sample action could not be completed.'); }
});
document.addEventListener('input', event => { if (event.target instanceof HTMLInputElement && event.target.id === 'donor-search') { filters.query = event.target.value; filters.page = 1; renderResults(); } });
document.addEventListener('change', event => {
  const target = event.target; if (!(target instanceof HTMLSelectElement)) return;
  if (target.id === 'blood-filter' || target.id === 'status-filter') { filters[target.id === 'blood-filter' ? 'blood' : 'status'] = target.value; filters.page = 1; renderResults(); }
  if (target.id === 'workspace-preview') { state.preview = target.value; render(); }
  if (target.id === 'public-mode') state.publicMode = target.value;
  if (['public-mode', 'public-blood'].includes(target.id)) document.querySelector('#availability-results').innerHTML = availabilityResults(document.querySelector('#public-blood').value);
});
document.addEventListener('submit', event => {
  const form = event.target; if (!(form instanceof HTMLFormElement)) return; event.preventDefault();
  if (!form.reportValidity()) return;
  const values = Object.fromEntries(new FormData(form)); const errorRegion = form.querySelector('.form-error');
  try {
    if (form.id === 'auth-form' || form.id === 'recovery-form') {
      errorRegion.textContent = form.id === 'auth-form' ? 'Sign-in is not connected. Use the demo role selector below to explore without credentials.' : 'Recovery preview complete. No request was submitted and no email was sent.';
      errorRegion.tabIndex = -1; errorRegion.focus(); form.reset(); return;
    }
    if (form.dataset.filterForm) {
      if (values.from && !validDate(values.from) || values.to && !validDate(values.to) || values.from && values.to && values.from > values.to) throw new Error('Use valid dates with From on or before To.');
      const params = new URLSearchParams(Object.entries(values).filter(([, value]) => value)); navigate(`${form.dataset.filterForm}?${params}`); return;
    }
    if (form.id === 'login-form') { const first = signIn(values.role); const target = returnRoute && can(returnRoute.split(/[/?]/)[0]) ? returnRoute : first; returnRoute = ''; navigate(target); return; }
    if (form.id === 'settings-form') {
      requirePermission('settings'); const chapter = required(values.chapter, 'Chapter label');
      state.settings = { chapter, refreshMinutes: Number(values.refreshMinutes), alertDays: Number(values.alertDays), backupTime: values.backupTime, retentionDays: Number(values.retentionDays) };
      log('Update demo configuration', 'Settings'); render(); notify('Demo configuration saved in this tab.'); return;
    }
    if (form.id !== 'workflow-form') return;
    const id = form.dataset.id; const kind = form.dataset.kind; let destination = '';
    switch (kind) {
      case 'register': destination = `donors/${addDonor(values).id}`; break;
      case 'donor-edit': updateDonor(id, values); break;
      case 'eligibility': recordEligibility(id, values); break;
      case 'donation': addDonation(id, values); destination = `donors/${id}?tab=history`; break;
      case 'unit-add': destination = `inventory/${addUnit(values)}`; break;
      case 'unit-verify': changeUnit(id, { ...values, operation: 'verify' }); break;
      case 'unit-change': changeUnit(id, values); break;
      case 'publish': publishSnapshot(); break;
      case 'compose': requirePermission('notifications'); state.notifications.unshift({ id: nextId('SMS'), recipient: required(values.recipient, 'Group'), event: values.event, message: required(values.message, 'Message'), status: 'Queued', attempts: 0 }); log('Queue sample notification', 'Notification queue'); break;
      case 'message-action': { requirePermission('notifications'); const item = state.notifications.find(message => message.id === id); if (!item) throw new Error('Message not found.'); if (item.status === 'Simulated delivery') throw new Error('This simulated result has already been recorded.'); item.status = item.status === 'Failed' ? 'Queued' : 'Simulated delivery'; item.attempts += 1; log('Simulate notification result', id, item.status); break; }
      case 'user-add': case 'user-edit': {
        requirePermission('users'); if (!roles.includes(values.role)) throw new Error('Select a valid role.'); const record = { name: required(values.name, 'Display name'), role: values.role, active: values.active === 'Active' };
        if (kind === 'user-add') state.users.unshift({ id: nextId('USR'), ...record }); else { const user = state.users.find(item => item.id === id); if (!user) throw new Error('User not found.'); Object.assign(user, record); } log('Save demo user', id || 'New demo user'); break;
      }
      case 'backup': requirePermission('backups'); state.backups.unshift({ id: nextId('BKP'), timestamp: new Date().toISOString(), status: 'Sample success', records: state.donors.length, note: 'Simulated metadata; no file created.' }); log('Simulate backup metadata', 'Backup records'); break;
      case 'restore': requirePermission('backups'); log('Rehearse restore checklist', 'Recovery walkthrough', 'Checklist only; no restoration performed.'); break;
    }
    dialog.close(); if (destination) navigate(destination); else render(); notify(kind === 'restore' ? 'Checklist rehearsal recorded. No data restored.' : kind === 'backup' ? 'Sample metadata added. No backup file created.' : 'Sample changes recorded in this tab.');
  } catch (error) { if (errorRegion) { errorRegion.textContent = error instanceof Error ? error.message : 'Unable to complete this action.'; errorRegion.tabIndex = -1; errorRegion.focus(); } else notify('Unable to complete this action.'); }
});
for (const target of [dialog, guide]) target.addEventListener('close', () => { if (lastTrigger instanceof HTMLElement && lastTrigger.isConnected) lastTrigger.focus(); });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { document.body.classList.remove('navigation-open'); document.querySelector('#mobile-menu-toggle').setAttribute('aria-expanded', 'false'); } });
window.addEventListener('hashchange', () => render());
window.addEventListener('error', () => { main.innerHTML = '<section class="panel empty-state" role="alert"><h1>The interface could not load.</h1><p>Reload the page to reset the demo. No information has been sent.</p><a class="button primary" href="index.html">Reload prototype</a></section>'; });
render(false);

