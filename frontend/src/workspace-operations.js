import { request, ApiError } from './api.js';
import { escapeHtml as e } from './ui.js';
import * as view from './operations-views.js';
import * as management from './management-views.js';
import { bloodTypes, timestamp } from './workspace-ui.js';

const permissions = { donations: 'donors.read', inventory: 'inventory.read', assignments: 'assignments.manage', alerts: 'inventory.read', 'stock-rules': 'alerts.manage', clinical: 'donors.read', catalog: 'clinical.catalog', backups: 'backups.manage', trace: 'inventory.read', reports: 'reports.read', 'donation-reports': 'donors.read', publications: 'availability.publish', staff: 'users.manage' };
const get = (path, signal) => request('/api/v1' + path, { signal });
const enc = encodeURIComponent;
export const toInstant = value => new Date(`${value}${value.length === 16 ? ':00' : ''}+08:00`).toISOString();
export class WorkspaceOperations {
  constructor(context) {
    this.context = context;
    this.state = null;
    this.formState = null;
    document.addEventListener('click', event => {
      const trigger = event.target instanceof Element ? event.target.closest('[data-op]') : null;
      if (trigger instanceof HTMLElement) this.command(trigger).catch(error => this.failure(error));
    });
    document.addEventListener('submit', event => {
      if (!(event.target instanceof HTMLFormElement) || !event.target.matches('.operation-form')) return;
      event.preventDefault();
      this.submit(event.target).catch(error => this.failure(error));
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) this.stopAlerts(); else this.watchAlerts(); });
    window.addEventListener('pagehide', () => this.stopAlerts());
    window.addEventListener('pageshow', () => this.watchAlerts());
    window.addEventListener('online', () => { this.stopAlerts(); this.watchAlerts(); });
  }
  stopAlerts() { clearTimeout(this.alertTimer); this.alertTimer = null; this.alertUser = null; this.alertController?.abort(); this.alertController = null; }
  watchAlerts() {
    const region = document.querySelector('#stock-alert-notice');
    if (!region) return;
    if (!this.allowed('inventory.read') || document.hidden || navigator.onLine === false) { this.stopAlerts(); region.hidden = true; region.innerHTML = ''; return; }
    const id = this.context.user().id;
    if (this.alertUser === id && (this.alertTimer || this.alertController)) return;
    this.stopAlerts(); this.alertUser = id;
    const poll = async () => {
      this.alertTimer = null; const controller = new AbortController(); this.alertController = controller;
      try {
        const result = await get('/stock-alerts', controller.signal);
        if (controller.signal.aborted || this.alertUser !== id || this.context.user()?.id !== id) return;
        const count = Math.max(0, Number(result.unacknowledged_open_count) || 0);
        region.hidden = count === 0; region.innerHTML = '';
        if (count) { const link = document.createElement('a'); link.href = '#/alerts'; link.textContent = `${count} stock alert${count === 1 ? '' : 's'} need attention. Open alerts`; region.append(link); }
      } catch (error) {
        if (controller.signal.aborted || this.alertUser !== id) return;
        if ([401, 419].includes(error.status)) { this.context.expired(); return; }
        region.hidden = false; region.textContent = 'Stock alerts could not be refreshed. Reconnect and check the Alerts page.';
      } finally {
        if (this.alertController === controller) this.alertController = null;
        if (!controller.signal.aborted && this.alertUser === id && this.context.user()?.id === id) { this.alertTimer = setTimeout(poll, 60000); this.alertTimer?.unref?.(); }
      }
    };
    poll();
  }
  supports(section) { return Object.hasOwn(permissions, section); }
  clear() { this.state = null; this.formState = null; }
  closed() { this.formState = null; }
  allowed(permission) { return Boolean(this.context.user()?.permissions.includes(permission)); }
  async failure(error) {
    if ([401, 419].includes(error.status)) { this.context.expired(); return; }
    if ([403, 404].includes(error.status)) {
      this.context.dialog.close(); this.clear(); await this.context.refresh();
      this.context.toast('Access changed or the record is no longer available. The workspace has been refreshed.'); return;
    }
    this.context.toast(error instanceof ApiError ? error.message + (error.requestId ? ` Reference: ${error.requestId}` : '') : 'The operation could not be completed. Retry when ready.');
  }
  async render(section, id, params, signal) {
    if (!this.allowed(permissions[section])) throw new ApiError('Your account cannot access this workspace.', 403);
    const query = '?' + params;
    const state = { section, id, params: new URLSearchParams(params) };
    if (section === 'donations' || section === 'clinical' || section === 'trace') {
      if (section === 'trace' || section === 'donations') {
        const [donor, records] = await Promise.all([
          get(`/donors/${enc(id)}`, signal),
          get(`/donors/${enc(id)}/${section === 'trace' ? 'units' : 'donations'}${query}`, signal),
        ]);
        state.donor = donor.data; state.data = records;
      } else {
        state.donor = (await get(`/donors/${enc(id)}`, signal)).data;
        state.category = params.get('category') || ['screening', 'laboratory', 'eligibility'].find(category => state.donor.clinical_permissions.includes(category + '.read')) || 'screening';
        state.data = await get(`/donors/${enc(id)}/clinical/${enc(state.category)}${query}`, signal);
      }
    } else if (section === 'inventory') state.data = await get(id ? `/inventory/${enc(id)}` : '/inventory' + query, signal);
    else if (section === 'assignments') {
      if (id) {
        [state.data, state.directory] = await Promise.all([get(`/assignments/${enc(id)}`, signal), get('/assignments?donor_id=' + enc(id), signal)]);
      } else state.data = await get('/assignments' + query, signal);
    } else if (section === 'reports' || section === 'donation-reports') {
      state.kind = section === 'donation-reports' ? 'donations' : params.get('kind') || 'inventory';
      if (section === 'reports' && !['inventory', 'expiry'].includes(state.kind)) throw new ApiError('This report does not exist.', 404);
      state.data = await get('/reports/' + enc(state.kind) + query, signal);
    } else state.data = await get(({ staff: '/staff', publications: '/publications', alerts: '/stock-alerts', 'stock-rules': '/stock-rules', catalog: '/clinical-catalog', backups: '/backups' })[section] + query, signal);
    if (signal?.aborted) throw new DOMException('Navigation changed.', 'AbortError');
    this.state = state;
    if (section === 'staff') return management.staff(state);
    if (section === 'publications') return management.publications(state);
    if (section === 'reports' || section === 'donation-reports') return management.reports(state);
    if (section === 'trace') return view.trace(state);
    if (section === 'donations') return view.donations(state, state.donor.can_update);
    if (section === 'inventory') return id ? view.unit(state, this.allowed('inventory.write')) : view.inventory(state, this.allowed('inventory.write'));
    if (section === 'assignments') return view.assignments(state);
    if (section === 'alerts') return view.alerts(state, this.allowed('alerts.manage'));
    if (section === 'stock-rules') return view.stockRules(state);
    if (section === 'clinical') return view.clinical(state, this.context.user());
    if (section === 'catalog') return view.catalog(state);
    return view.backups(state);
  }
  profileLinks(donor) {
    const grants = donor.clinical_permissions ?? [];
    return `<section class="panel padded section-gap"><h2>History & assigned access</h2><p>${Number(donor.donation_count ?? 0)} recorded completed donations. Historical coverage may be incomplete.</p><div class="ops-actions"><a href="#/donations/${e(donor.id)}" class="button secondary">Donation history</a>${this.allowed('inventory.read') ? `<a href="#/trace/${e(donor.id)}" class="button secondary">Linked blood units</a>` : ''}${grants.some(permission => permission.endsWith('.read')) ? `<a href="#/clinical/${e(donor.id)}" class="button secondary">Restricted clinical records</a>` : ''}${this.allowed('assignments.manage') ? `<a href="#/assignments/${e(donor.id)}" class="button secondary">Manage staff assignments</a>` : ''}</div></section>`;
  }
  links() {
    return `<section class="panel padded section-gap"><h2>Connected operations</h2><nav class="ops-links" aria-label="Operational workspaces">${[['reports', 'reports.read'], ['donation-reports', 'donors.read'], ['publications', 'availability.publish'], ['staff', 'users.manage'], ['inventory', 'inventory.read'], ['assignments', 'assignments.manage'], ['alerts', 'inventory.read'], ['stock-rules', 'alerts.manage'], ['catalog', 'clinical.catalog'], ['backups', 'backups.manage']].filter(([, permission]) => this.allowed(permission)).map(([section]) => `<a class="button secondary" href="#/${section}">${e(view.labels[section])}</a>`).join('')}</nav><p><a class="text-link" href="/recovery#change">Change your password</a></p><p>Donation history and permitted clinical categories are available from each assigned donor profile.</p></section>`;
  }
  isDirty() {
    const form = this.context.dialog.querySelector('.operation-form');
    return Boolean(form && this.formState && this.digest(form) !== this.formState.initial);
  }
  digest(form) { return JSON.stringify([...new FormData(form)]); }
  requestClose() {
    const { dialog } = this.context;
    if (dialog.querySelector('[aria-busy="true"]')) return;
    if (this.isDirty()) { dialog.querySelector('.ops-discard').hidden = false; dialog.querySelector('[data-op="keep"]').focus(); }
    else dialog.close();
  }
  open(title, fields, spec, trigger) {
    const { dialog } = this.context;
    this.context.remember(trigger);
    this.formState = { ...spec, key: crypto.randomUUID() };
    dialog.innerHTML = `<form class="operation-form" data-stage="entry"><div class="dialog-heading"><h2 id="record-title">${e(title)}</h2><button type="button" class="icon-button" data-op="close" aria-label="Close form">×</button></div><p class="workspace-muted">Enter the record, review it, then confirm the server save. Times in this form are Philippine time.</p><div class="ops-fields form-grid">${fields}</div><section class="ops-review" hidden aria-label="Review before saving"></section><p class="form-error" role="alert" tabindex="-1"></p><section class="ops-discard" hidden><h3>Discard unsaved entries?</h3><p>If a save was interrupted, check the saved records before starting a new entry.</p><div class="dialog-actions">${view.action('Keep editing', 'keep')}${view.action('Discard entries', 'discard')}</div></section><div class="dialog-actions">${view.action('Cancel', 'close')}${view.action('Edit entries', 'back')}<button class="button primary" type="submit">Review entries</button></div></form>`;
    const form = dialog.querySelector('form'); form.querySelector('[data-op="back"]').hidden = true;
    this.formState.initial = this.digest(form); dialog.showModal();
  }
  record(id) {
    const data = this.state?.data.data;
    return Array.isArray(data) ? data.find(item => String(item.id ?? item.user_id) === String(id)) : data?.id === id ? data : null;
  }
  async command(trigger) {
    const op = trigger.dataset.op;
    if (this.context.dialog.querySelector('[aria-busy="true"]')) return;
    if (op === 'close') { this.requestClose(); return; }
    if (op === 'discard') { this.context.dialog.close(); return; }
    if (op === 'keep') { this.context.dialog.querySelector('.ops-discard').hidden = true; this.context.dialog.querySelector('input,select,textarea')?.focus(); return; }
    if (op === 'back') { this.entryStep(this.context.dialog.querySelector('form')); return; }
    if (op === 'reconcile' || op === 'acknowledge') {
      trigger.disabled = true; trigger.setAttribute('aria-busy', 'true');
      try { await request('/api/v1' + (op === 'reconcile' ? '/stock-alerts/reconcile' : `/stock-alerts/${enc(trigger.dataset.id)}/acknowledge`), { method: 'POST', body: {} }); this.stopAlerts(); await this.context.refresh(); this.context.toast(op === 'reconcile' ? 'Stock conditions reconciled.' : 'Awareness recorded.'); }
      finally { trigger.disabled = false; trigger.removeAttribute('aria-busy'); }
      return;
    }
    if (!this.state) return;
    const state = this.state; const item = trigger.dataset.id ? this.record(trigger.dataset.id) : null;
    if (op === 'report-export') {
      trigger.disabled = true;
      try {
        const result = await request('/api/v1/reports/' + enc(state.kind) + '/export?' + state.params, { method: 'POST', body: {} });
        if (this.state !== state || !this.context.user()) return;
        const url = URL.createObjectURL(new Blob([result.csv], { type: 'text/csv;charset=utf-8' }));
        const link = document.createElement('a'); link.href = url; link.download = result.filename; document.body.append(link); link.click(); link.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000);
        this.context.toast(result.row_count + ' aggregate rows exported.');
      } catch (error) {
        if (this.state === state && this.context.user()) throw error;
      } finally { trigger.disabled = false; }
      return;
    }
    if (op === 'publication-new') {
      if (!state.data.locations.length) { this.context.toast('Register inventory before preparing a publication.'); return; }
      this.open('Publish availability', view.select('location', 'Exact storage location', state.data.locations.map(value => [value, value])) + view.input('public_label', 'Approved public location label', '', 'text', 'required minlength="2" maxlength="100"') + view.input('valid_minutes', 'Maximum validity (minutes)', '', 'number', 'required min="1" max="1440"') + view.checkbox('confirmed', 'I approve public disclosure of this location label, component labels and aggregate counts.', false, 'required') + view.reason(), { kind: 'publication', state }, trigger); return;
    }
    if (op === 'publication-withdraw') { this.open('Withdraw public snapshot', view.notice('The latest snapshot will become unavailable. Older snapshots are not republished.') + view.reason(), { kind: 'withdraw', item, state }, trigger); return; }
    if (op === 'staff-new' || op === 'staff-edit') {
      const fields = view.input('name', 'Staff display name', item?.name, 'text', 'required minlength="2" maxlength="120"') + (item ? view.notice('Email: ' + item.email) : view.input('email', 'Staff email', '', 'email', 'required maxlength="255"')) + view.select('role', 'Authorized role', state.data.roles.map(value => [value, value]), item?.role) + (item ? view.checkbox('active', 'Account active', item.active) : view.input('password', 'Initial password', '', 'password', 'required minlength="12" maxlength="128" autocomplete="new-password"') + view.input('password_confirmation', 'Confirm initial password', '', 'password', 'required minlength="12" maxlength="128" autocomplete="new-password"')) + view.input('current_password', 'Your current administrator password', '', 'password', 'required autocomplete="current-password"') + view.reason() + view.notice('A password needs 12+ characters, uppercase and lowercase letters, a number and a symbol. Changing access removes donor assignments; Red Cross Administrators must grant any new assignments explicitly.');
      this.open(item ? 'Update staff account' : 'Create staff account', fields, { kind: 'staff', item, state }, trigger); return;
    }
    if (op === 'donation-new' || op === 'donation-edit') {
      const fields = (item ? `<p class="notice full">Documentary reference: ${e(item.reference)}. A voided event remains in history and cannot be reactivated.</p>` : view.input('reference', 'Unique documentary reference', '', 'text', 'required maxlength="80" pattern="[A-Za-z0-9_-]+"')) + view.input('donated_on', 'Actual donation date', item?.donated_on, 'date', 'required') + view.input('location', 'Collection location', item?.location, 'text', 'required minlength="2" maxlength="100"') + (item ? view.select('status', 'Recorded state', item.status === 'voided' ? ['voided'] : ['completed', 'voided'], item.status) + view.reason() : view.notice('This records a completed event from its documentary source. Check the reference before saving.'));
      this.open(item ? 'Correct / void donation' : 'Record completed donation', fields, { kind: 'donation', item, state }, trigger); return;
    }
    if (op === 'unit-new') {
      const fields = view.input('reference', 'Unique bag reference', '', 'text', 'required maxlength="80" pattern="[A-Za-z0-9_-]+"') + view.select('blood_type', 'Recorded blood type', bloodTypes.map(value => [value, value])) + view.input('component', 'Approved component name', '', 'text', 'required minlength="2" maxlength="80"') + view.input('location', 'Storage location', '', 'text', 'required minlength="2" maxlength="100"') + view.input('collected_at', 'Collected (PHT)', '', 'datetime-local', 'required') + view.input('expires_at', 'Entered expiry (PHT)', '', 'datetime-local', 'required') + view.reason() + view.notice('One bag record represents one tracked unit. No component shelf life is calculated. New units remain pending verification.');
      this.open('Register inventory unit', fields, { kind: 'unit', state }, trigger); return;
    }
    if (op === 'unit-source') {
      this.open('Link source donation', view.input('donation_reference', 'Completed donation reference', '', 'text', 'required maxlength="80"') + view.reason() + view.notice('Only assigned donor records can be linked. Collection dates must match. The source link is permanent; inspect the documentary source before confirming.'), { kind: 'source', item: state.data.data, state }, trigger); return;
    }
    if (op === 'unit-action') {
      const unit = state.data.data;
      const actions = { pending: ['verify', 'quarantine', 'spoil', 'discard'], available: ['quarantine', 'spoil', 'release', 'discard'], quarantined: ['spoil', 'discard'], spoiled: ['discard'] }[unit.status] ?? [];
      this.open('Record stock action', view.notice(`${unit.reference} · ${view.titleCase(unit.status)} · Version ${unit.version}`) + view.select('action', 'Authorized action', actions) + view.checkbox('verification_confirmed', 'For verification: I confirm the approved verification has been recorded by authorized personnel.') + view.reason(), { kind: 'transition', item: unit, state }, trigger); return;
    }
    if (op === 'assignment-new' || op === 'assignment-edit') {
      const directory = state.directory;
      const choices = item ? [[item.user_id, directory.staff.find(person => person.id === item.user_id)?.name ?? `Staff ${item.user_id}`]] : directory.staff.map(person => [person.id, person.name]);
      const fields = view.select('user_id', 'Assigned staff', choices, item?.user_id, 'required') + view.checkbox('revoke', 'Revoke this staff assignment', item?.active === false) + '<fieldset class="full"><legend>Separate clinical permissions</legend><p>Review requires both View and Review. Only grant the actions this staff member is authorized to perform.</p>' + directory.clinical_permissions.map(permission => view.checkbox('grant_' + permission.replace('.', '_'), permission.split('.').map(view.titleCase).join(' · '), item?.clinical_permissions?.includes(permission))).join('') + '</fieldset>' + view.reason();
      this.open('Assign donor access', fields, { kind: 'assignment', item, state }, trigger); return;
    }
    if (op === 'rule-new' || op === 'rule-edit') {
      const fields = view.select('blood_type', 'Blood type', (item ? [item.blood_type] : bloodTypes).map(value => [value, value]), item?.blood_type) + view.input('component', 'Exact inventory component', item?.component, 'text', `required minlength="2" maxlength="80" ${item ? 'readonly' : ''}`) + view.input('location', 'Exact inventory location', item?.location, 'text', `required minlength="2" maxlength="100" ${item ? 'readonly' : ''}`) + view.input('threshold', 'Low-stock threshold (units)', item?.threshold ?? '', 'number', 'required min="0" max="999999" step="1"') + '<fieldset class="full"><legend>Responsible recipients</legend>' + state.data.staff.map(person => view.checkbox('recipient_' + person.id, person.name, item?.recipient_ids.includes(person.id))).join('') + '</fieldset>' + view.checkbox('active', 'Rule enabled', item?.active ?? true) + view.reason();
      this.open('Configure stock rule', fields, { kind: 'rule', item, state }, trigger); return;
    }
    if (op === 'catalog-new' || op === 'catalog-edit') {
      const fields = view.input('key', 'Stable field key', item?.key, 'text', `required maxlength="60" pattern="[a-z][a-z0-9_]*" ${item ? 'readonly' : ''}`) + view.input('label', 'Approved display name', item?.label, 'text', 'required maxlength="100"') + view.select('format', 'Recording format', item ? [item.format] : ['text', 'number', 'choice'], item?.format) + view.input('unit', 'Units (if applicable)', item?.unit, 'text', 'maxlength="40"') + view.area('choices', 'Allowed choices (one per line, for choice format)', item?.choices?.join('\n'), 'maxlength="3000"') + view.area('reference_text', 'Approved reference information (optional)', item?.reference_text, 'maxlength="500"') + view.checkbox('required', 'Result field is required', item?.required) + view.checkbox('active', 'Definition enabled', item?.active ?? true) + view.reason();
      this.open('Laboratory field definition', fields, { kind: 'catalog', item, state }, trigger); return;
    }
    if (op === 'clinical-new' || op === 'clinical-edit') {
      const payload = item?.payload ?? {};
      if (item && state.category === 'laboratory' && Object.keys(payload.results ?? {}).some(key => !state.data.catalog.some(field => field.key === key))) { this.context.toast('This entry contains retired result fields. An administrator must restore the approved definitions before a correction can preserve all results.'); return; }
      const fields = (item ? view.notice(item.donation_id ? `Linked donation: ${item.donation_id}` : 'Donor-level record, no donation association.') : view.input('donation_id', 'Donation UUID (optional, same donor only)', '', 'text', 'maxlength="36"')) + view.input('source_reference', 'Source document reference', item?.source_reference, 'text', 'required maxlength="120"') + view.input('observed_at', 'Observed / recorded source time (PHT)', view.phtInput(item?.observed_at), 'datetime-local', 'required') + (state.category === 'laboratory' ? state.data.catalog.map(field => field.format === 'choice' ? view.select('result_' + field.key, field.label, [['', 'Choose / not entered'], ...field.choices.map(choice => [choice, choice])], payload.results?.[field.key], field.required ? 'required' : '') : view.input('result_' + field.key, field.label + (field.unit ? ` (${field.unit})` : ''), payload.results?.[field.key], field.format === 'number' ? 'number' : 'text', `${field.required ? 'required' : ''} ${field.format === 'number' ? 'step="any"' : 'maxlength="1000"'}`)).join('') : view.select('decision', 'Staff-recorded status / decision', state.category === 'screening' ? ['pending', 'recorded', 'requires_review'] : ['not_assessed', 'eligible', 'deferred', 'not_eligible'], payload.decision) + (state.category === 'eligibility' ? view.input('deferred_until', 'Staff-entered deferral date (if deferred)', payload.deferred_until, 'date') : '')) + view.area('notes', 'Restricted notes', payload.notes, `${state.category === 'laboratory' ? '' : 'required'} maxlength="5000"`) + (item ? view.reason() : '') + view.notice('Record approved information only. Saving does not establish suitability, release approval or an automatic end to deferral.');
      if (state.category === 'laboratory' && !state.data.catalog.length) { this.context.toast('Configure the approved laboratory catalog first.'); return; }
      this.open(item ? 'Correct clinical entry' : 'Record clinical entry', fields, { kind: 'clinical', item, state }, trigger); return;
    }
    if (op === 'clinical-review') {
      this.open('Review recorded clinical entry', view.notice(`Source ${item.source_reference} · Version ${item.version}. Review the recorded values and documentary source before confirming.`) + view.clinicalValues(item.payload) + view.reason(), { kind: 'review', item, state }, trigger); return;
    }
    if (op === 'clinical-history') {
      trigger.disabled = true;
      try {
        const page = Math.max(1, Number(trigger.dataset.page) || 1);
        const response = await get(`/donors/${enc(state.id)}/clinical/${enc(state.category)}/${enc(item.id)}/history?page=${page}`);
        if (this.state !== state || !this.context.user()) return;
        const wasOpen = this.context.dialog.open;
        if (!wasOpen) this.context.remember(trigger);
        const pageButton = (label, number) => `<button class="button secondary" type="button" data-op="clinical-history" data-id="${e(item.id)}" data-page="${number}">${label}</button>`;
        this.context.dialog.innerHTML = `<div class="dialog-heading"><h2 id="record-title">Restricted revision history</h2>${view.action('Close', 'close')}</div><p>Showing ${response.data.length} of ${response.meta.total} revisions · Page ${response.meta.current_page}.</p>${response.data.map(row => `<section class="ops-history"><h3>Version ${row.version} · ${e(view.titleCase(row.snapshot.status))}</h3><p>${e(row.snapshot.source_reference)} · ${e(timestamp(row.snapshot.observed_at))}</p><p>${e(row.snapshot.reason)}</p>${view.clinicalValues(row.snapshot.payload)}</section>`).join('')}<nav class="ops-actions" aria-label="Revision pages">${response.meta.current_page > 1 ? pageButton('Newer revisions', response.meta.current_page - 1) : ''}${response.meta.current_page < response.meta.last_page ? pageButton('Older revisions', response.meta.current_page + 1) : ''}</nav>`;
        this.formState = null; if (!wasOpen) this.context.dialog.showModal();
      } catch (error) {
        if (this.state === state && this.context.user()) throw error;
      } finally { trigger.disabled = false; }
      return;
    }
    if (op === 'backup-new') { this.open('Create encrypted snapshot', view.reason() + view.notice('This snapshots the current SQLite database. The application key is stored separately and is required for recovery.'), { kind: 'backup', state }, trigger); return; }
    if (op === 'backup-restore') this.open('Restore a new isolated copy', view.notice(`Backup ${item.id}. The running database will not be replaced. The copy contains private data and stays in private server storage.`) + view.checkbox('isolated_copy_confirmed', 'I understand this creates a private isolated recovery copy for verification.', false, 'required'), { kind: 'restore', item, state }, trigger);
  }
  entryStep(form) {
    form.dataset.stage = 'entry'; form.querySelector('.ops-fields').hidden = false; form.querySelector('.ops-review').hidden = true;
    form.querySelector('[data-op="back"]').hidden = true; form.querySelector('[type="submit"]').textContent = 'Review entries';
    form.querySelector('input,select,textarea')?.focus();
  }
  body(form) {
    const values = Object.fromEntries(new FormData(form));
    const { kind, item, state } = this.formState;
    if (kind === 'publication') return { ...values, valid_minutes: Number(values.valid_minutes), confirmed: values.confirmed === 'on' };
    if (kind === 'staff') return { ...values, ...(item ? { active: values.active === 'on', version: item.version } : {}) };
    if (kind === 'donation') return { donated_on: values.donated_on, location: values.location, ...(item ? { status: values.status, version: item.version, reason: values.reason } : { reference: values.reference }) };
    if (kind === 'unit') return { ...values, collected_at: toInstant(values.collected_at), expires_at: toInstant(values.expires_at) };
    if (kind === 'source') return { ...values, version: item.version };
    if (kind === 'transition') return { action: values.action, version: item.version, verification_confirmed: values.verification_confirmed === 'on', reason: values.reason };
    if (kind === 'assignment') return { user_id: Number(values.user_id), version: item?.version ?? 0, revoke: values.revoke === 'on', clinical_permissions: state.directory.clinical_permissions.filter(permission => values['grant_' + permission.replace('.', '_')] === 'on'), reason: values.reason };
    if (kind === 'rule') return { ...(item ? { id: item.id } : {}), version: item?.version ?? 0, blood_type: values.blood_type, component: values.component, location: values.location, threshold: Number(values.threshold), recipient_ids: state.data.staff.filter(person => values['recipient_' + person.id] === 'on').map(person => person.id), active: values.active === 'on', reason: values.reason };
    if (kind === 'catalog') return { ...(item ? { id: item.id } : {}), version: item?.version ?? 0, key: values.key, label: values.label, format: values.format, unit: values.unit || null, reference_text: values.reference_text || null, choices: values.format === 'choice' ? values.choices.split('\n').map(value => value.trim()).filter(Boolean) : [], required: values.required === 'on', active: values.active === 'on', reason: values.reason };
    if (kind === 'clinical') {
      const payload = state.category === 'laboratory' ? { results: Object.fromEntries(state.data.catalog.map(field => [field.key, values['result_' + field.key] === '' ? null : field.format === 'number' ? Number(values['result_' + field.key]) : values['result_' + field.key]])), notes: values.notes } : { decision: values.decision, notes: values.notes, ...(values.deferred_until ? { deferred_until: values.deferred_until } : {}) };
      return { ...(!item && values.donation_id ? { donation_id: values.donation_id } : {}), source_reference: values.source_reference, observed_at: toInstant(values.observed_at), payload, ...(item ? { version: item.version, reason: values.reason } : {}) };
    }
    if (kind === 'review') return { version: item.version, reason: values.reason };
    if (kind === 'restore') return { isolated_copy_confirmed: values.isolated_copy_confirmed === 'on' };
    return values;
  }
  destination() {
    const { kind, item, state } = this.formState;
    if (kind === 'publication') return ['/publications', 'POST'];
    if (kind === 'withdraw') return [`/publications/${enc(item.id)}/withdraw`, 'POST'];
    if (kind === 'staff') return [item ? `/staff/${enc(item.id)}` : '/staff', item ? 'PATCH' : 'POST'];
    if (kind === 'donation') return [`/donors/${enc(state.id)}/donations${item ? '/' + enc(item.id) : ''}`, item ? 'PATCH' : 'POST'];
    if (kind === 'unit') return ['/inventory', 'POST'];
    if (kind === 'source') return [`/inventory/${enc(item.id)}/source`, 'POST'];
    if (kind === 'transition') return [`/inventory/${enc(item.id)}/transitions`, 'POST'];
    if (kind === 'assignment') return [`/assignments/${enc(state.id)}`, 'PUT'];
    if (kind === 'rule') return ['/stock-rules', 'POST'];
    if (kind === 'catalog') return ['/clinical-catalog', 'POST'];
    if (kind === 'clinical' || kind === 'review') return [`/donors/${enc(state.id)}/clinical/${enc(state.category)}${item ? '/' + enc(item.id) : ''}${kind === 'review' ? '/review' : ''}`, kind === 'clinical' && item ? 'PATCH' : 'POST'];
    if (kind === 'restore') return [`/backups/${enc(item.id)}/restore-copy`, 'POST'];
    return ['/backups', 'POST'];
  }
  async submit(form) {
    const button = form.querySelector('[type="submit"]'); if (button.disabled || !this.formState || !form.reportValidity()) return;
    const region = form.querySelector('.form-error'); region.textContent = '';
    const body = this.body(form);
    if (form.dataset.stage === 'entry') {
      const summary = [...form.querySelectorAll('[data-label]')].map(label => {
        const field = label.querySelector('input,select,textarea'); if (!field) return '';
        const value = field.type === 'password' ? 'Entered (hidden)' : field.type === 'checkbox' ? (field.checked ? 'Yes' : 'No') : field instanceof HTMLSelectElement ? field.selectedOptions[0]?.textContent : field.value;
        return `<div><dt>${e(label.dataset.label)}</dt><dd class="ops-preserve">${e(value || 'Not entered')}</dd></div>`;
      }).join('');
      form.querySelector('.ops-review').innerHTML = `<h3 tabindex="-1">Review before saving</h3><dl class="details-grid">${summary}${this.formState.item?.version ? `<div><dt>Saved version</dt><dd>${this.formState.item.version}</dd></div>` : ''}</dl>`;
      form.querySelector('.ops-review').hidden = false; form.querySelector('.ops-fields').hidden = true;
      form.dataset.stage = 'review'; form.querySelector('[data-op="back"]').hidden = false; button.textContent = 'Confirm & save'; form.querySelector('.ops-review h3').focus(); return;
    }
    const [path, method] = this.destination(); const current = this.formState;
    button.disabled = true; button.setAttribute('aria-busy', 'true'); button.textContent = 'Saving…';
    try {
      const result = await request('/api/v1' + path, { method, body, headers: { 'Idempotency-Key': current.key } });
      if (this.formState !== current || !this.context.user()) return;
      this.context.dialog.close();
      if (['unit', 'transition', 'rule'].includes(current.kind)) this.stopAlerts();
      if (current.kind === 'unit') this.context.navigate(`inventory/${result.data.id}`); else await this.context.refresh();
      this.context.toast(current.kind === 'restore' ? `Isolated copy verified: ${result.data.copy_id}` : 'Saved and confirmed by the server.');
    } catch (error) {
      if (this.formState !== current || !this.context.user()) return;
      if ([401, 419].includes(error.status)) { this.context.expired(); return; }
      if ([403, 404].includes(error.status)) { await this.failure(error); return; }
      region.textContent = (error instanceof ApiError ? error.message : 'The operation could not complete.') + ' ' + Object.values(error.errors ?? {}).flat().join(' ') + (error.requestId ? ` Reference: ${error.requestId}` : '') + (error.status === 409 ? ' Your entries are retained. Close this form and refresh the record before preparing a new correction.' : '') + (error.status === 0 ? ' The save was not confirmed. Check saved records or retry the unchanged entry.' : '');
      region.focus();
    } finally { button.disabled = false; button.removeAttribute('aria-busy'); button.textContent = 'Confirm & save'; }
  }
}
