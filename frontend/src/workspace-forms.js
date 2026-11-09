import { escapeHtml as e } from './ui.js';
import { bloodTypes } from './workspace-ui.js';

export const donorFields = [['name', 'Full name'], ['municipality', 'Municipality'], ['blood_type', 'Recorded blood type']];
function field(name, label, value, maximum, help = '') {
  return `<label class="field ${name === 'name' || name === 'reason' ? 'full' : ''}">${label}<input name="${name}" value="${e(value)}" required minlength="${name === 'reason' ? 3 : 2}" maxlength="${maximum}" aria-describedby="error-${name}${help ? ' help-' + name : ''}">${help ? `<small class="field-hint" id="help-${name}">${help}</small>` : ''}<span class="field-error" id="error-${name}" data-error="${name}"></span></label>`;
}
export function donorForm(donor) {
  const edit = Boolean(donor);
  return `<form id="server-donor" data-id="${e(donor?.id ?? '')}" data-stage="details"><div class="dialog-heading"><div><p class="eyebrow">${edit ? 'TRACEABLE CORRECTIONS' : 'GROW THE DONOR REGISTRY'}</p><h2 id="record-title">${edit ? 'Correct donor details' : 'Register donor'}</h2></div><button class="icon-button" type="button" data-command="close" aria-label="Close donor form">×</button></div><ol class="workspace-form-steps" aria-label="Save progress"><li aria-current="step" data-step="details"><span>1</span>Enter details</li><li data-step="review"><span>2</span>Review & save</li></ol><div id="donor-fields"><p class="workspace-muted">${edit ? 'Correct the approved record fields and explain the change. Your draft is kept in this dialog until saved or discarded.' : 'Enter the approved record fields. Search the registry first if you are unsure whether a record already exists.'}</p><div class="form-grid">${field('name', 'Full name', donor?.name ?? '', 120)}${field('municipality', 'City / municipality', donor?.municipality ?? '', 100)}<label class="field">Recorded blood type<select name="blood_type" aria-describedby="error-blood_type">${bloodTypes.map(type => `<option ${donor?.blood_type === type ? 'selected' : ''}>${type}</option>`).join('')}</select><span class="field-error" id="error-blood_type" data-error="blood_type"></span></label>${edit ? `<input type="hidden" name="version" value="${donor.version}">${field('reason', 'Correction reason', '', 500, 'Describe the record correction. Do not include private clinical details.')}` : ''}</div></div><section id="save-review" class="workspace-save-review" aria-label="Review before saving" hidden></section><p class="form-error" role="alert" tabindex="-1"></p><p class="workspace-form-status" role="status"></p><section id="discard-panel" class="workspace-discard" aria-labelledby="discard-title" hidden><h3 id="discard-title">Discard these unsaved entries?</h3><p>This removes the entries from the dialog. If a request failed after submission, check the registry before registering again.</p><div class="dialog-actions"><button class="button secondary" type="button" data-command="keep-editing">Keep editing</button><button class="button primary" type="button" data-command="discard">Discard entries</button></div></section><div class="dialog-actions" id="save-actions"><button class="button secondary" type="button" data-command="close">Cancel</button><button class="button secondary" type="button" data-command="back-to-details" hidden>Edit entries</button><button class="button primary" type="submit">Review ${edit ? 'correction' : 'registration'}</button></div></form>`;
}
export function formValues(form) {
  const fields = Object.fromEntries(new FormData(form));
  const input = { name: String(fields.name ?? '').trim(), blood_type: String(fields.blood_type ?? ''), municipality: String(fields.municipality ?? '').trim() };
  return form.dataset.id ? { ...input, version: Number(fields.version), reason: String(fields.reason ?? '').trim() } : input;
}
export function validateFields(form, values) {
  const errors = {};
  if (values.name.length < 2) errors.name = ['Enter a name containing at least two characters.'];
  if (values.municipality.length < 2) errors.municipality = ['Enter a municipality containing at least two characters.'];
  if (!bloodTypes.includes(values.blood_type)) errors.blood_type = ['Choose a recorded blood type.'];
  if (form.dataset.id && values.reason.length < 3) errors.reason = ['Provide a correction reason containing at least three characters.'];
  return errors;
}
export function showFieldErrors(form, errors) {
  let first;
  for (const [name, messages] of Object.entries(errors)) {
    const target = form.querySelector(`[data-error="${name.replace(/[^a-z_]/g, '')}"]`);
    const input = form.elements.namedItem(name);
    if (target && input instanceof HTMLElement) {
      target.textContent = Array.isArray(messages) ? messages.join(' ') : String(messages);
      input.setAttribute('aria-invalid', 'true'); first ??= input;
    }
  }
  first?.focus();
}
export function resetErrors(form) {
  form.querySelector('.form-error').textContent = '';
  form.querySelectorAll('[data-error]').forEach(field => { field.textContent = ''; });
  form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
}
export function showDetails(form) {
  form.dataset.stage = 'details'; form.querySelector('#donor-fields').hidden = false;
  form.querySelector('#save-review').hidden = true;
  form.querySelector('[data-command="back-to-details"]').hidden = true;
  const submit = form.querySelector('[type="submit"]'); submit.hidden = false; submit.textContent = form.dataset.id ? 'Review correction' : 'Review registration';
  setSteps(form, 'details'); form.elements.namedItem('name').focus();
}
function setSteps(form, step) { form.querySelectorAll('[data-step]').forEach(item => { if (item.dataset.step === step) item.setAttribute('aria-current', 'step'); else item.removeAttribute('aria-current'); }); }
export function showReview(form, values, original) {
  const differences = donorFields.map(([key, label]) => `<div><dt>${label}</dt><dd>${e(values[key])}${original && original[key] !== values[key] ? `<small>Previously: ${e(original[key])}</small>` : ''}</dd></div>`).join('');
  form.querySelector('#save-review').innerHTML = `<h3 tabindex="-1" id="review-heading">Check before saving.</h3><dl class="workspace-review-values">${differences}${original ? `<div class="full"><dt>Correction reason</dt><dd>${e(values.reason)}</dd></div>` : ''}</dl><p class="notice">${original ? `This correction will use record version ${values.version} and create an audit entry.` : 'The server will create a record for administrative review. This does not establish medical eligibility.'}</p>`;
  form.dataset.stage = 'review'; form.querySelector('#donor-fields').hidden = true; form.querySelector('#save-review').hidden = false;
  form.querySelector('[data-command="back-to-details"]').hidden = false;
  form.querySelector('[type="submit"]').textContent = original ? 'Save correction' : 'Register donor';
  setSteps(form, 'review'); form.querySelector('#review-heading').focus();
}
export function showConflict(form) {
  form.dataset.stage = 'conflict'; form.querySelector('#donor-fields').hidden = true;
  form.querySelector('#save-review').hidden = false;
  form.querySelector('#save-review').innerHTML = `<div class="workspace-conflict"><h3 tabindex="-1" id="conflict-heading">A newer version was saved.</h3><p>Your entries are still in this dialog. Load the latest record to compare its saved values with your draft.</p><button class="button secondary" type="button" data-command="load-conflict">Load latest record</button></div>`;
  form.querySelector('[type="submit"]').hidden = true;
  form.querySelector('[data-command="back-to-details"]').hidden = true;
  form.querySelector('#conflict-heading').focus();
}
export function showComparison(form, latest, draft) {
  form.querySelector('#save-review').innerHTML = `<div class="workspace-conflict"><h3 id="conflict-heading" tabindex="-1">Compare with saved version ${latest.version}.</h3><p>Choose the value to keep for each field. These choices do not save anything yet.</p>${donorFields.map(([key, label]) => `<div class="workspace-compare"><h4>${label}</h4><dl><div><dt>Latest saved value</dt><dd>${e(latest[key])}</dd></div><div><dt>Your entry</dt><dd>${e(draft[key])}</dd></div></dl><label class="field">Value to keep for ${label.toLowerCase()}<select name="resolve_${key}"><option value="saved">Use latest saved value</option><option value="draft">Use my entry</option></select></label></div>`).join('')}<button class="button primary" type="button" data-command="apply-conflict">Apply choices and review again</button></div>`;
  form.querySelector('#conflict-heading').focus();
}
