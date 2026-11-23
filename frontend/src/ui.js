const paths = {
  chart: '<path d="M3 3v18h18M7 16v-4m5 4V8m5 8V5"/>',
  bell: '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-11 12a2 2 0 0 0 4 0"/>',
  storage: '<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6h.01M7 17h.01"/>',
  settings: '<path d="M4 7h16M4 17h16"/><circle cx="8" cy="7" r="3" fill="white"/><circle cx="16" cy="17" r="3" fill="white"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  grid: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75"/><circle cx="9" cy="7" r="4"/>',
  shield: '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
  book: '<path d="M12 5v16M3 3c4 0 7 0 9 2 2-2 5-2 9-2v16c-4 0-7 0-9 2-2-2-5-2-9-2V3Z"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v1"/>',
  drop: '<path d="M12 3S5 11 5 15a7 7 0 0 0 14 0c0-4-7-12-7-12Z"/><path d="M8 15a4 4 0 0 0 4 4"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  arrow: '<path d="M4 12h16m-6-6 6 6-6 6"/>',
  search: '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  pin: '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  check: '<path d="m5 12 4 4L19 6"/>',
};
export function icon(name) {
  return `<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] ?? paths.info}</svg>`;
}
export function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
}
export function initials(name) { return name.split(' ').map(part => part[0] ?? '').slice(0, 2).join(''); }
const dateFormatter = new Intl.DateTimeFormat('en-PH', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Manila' });
export function dateLabel(value) {
  if (!value) return 'No donation recorded';
  return dateFormatter.format(new Date(`${value}T12:00:00+08:00`));
}
export function badge(status) {
  const tone = { Eligible: 'success', Active: 'success', Available: 'success', 'Sample success': 'success', 'Simulated delivery': 'success', Deferred: 'muted', 'For review': 'warning', 'Pending verification': 'warning', Queued: 'warning', Failed: 'danger', Quarantined: 'danger', Discarded: 'muted' }[status] ?? 'muted';
  return `<span class="badge ${tone}"><span class="status-dot" aria-hidden="true"></span>${escapeHtml(status)}</span>`;
}
export function donorRow(donor) {
  return `<tr><td><a class="person" href="#/donors/${encodeURIComponent(donor.id)}"><span class="avatar">${escapeHtml(initials(donor.name))}</span><span><strong>${escapeHtml(donor.name)}</strong><small>${escapeHtml(donor.id)}</small></span></a></td><td><span class="blood-label">${escapeHtml(donor.blood)}</span></td><td>${escapeHtml(donor.area)}</td><td>${badge(donor.status)}</td><td>${dateLabel(donor.lastDonation)}</td><td><a class="row-link" href="#/donors/${encodeURIComponent(donor.id)}" aria-label="View ${escapeHtml(donor.name)}">View ${icon('arrow')}</a></td></tr>`;
}
export function donorTable(rows, caption = 'Donor records') {
  return `<div class="table-scroll" tabindex="0" role="region" aria-label="${escapeHtml(caption)}"><table><caption class="sr-only">${escapeHtml(caption)}</caption><thead><tr><th scope="col">Donor</th><th scope="col">Blood type</th><th scope="col">City / municipality</th><th scope="col">Recorded status</th><th scope="col">Last donation</th><th scope="col"><span class="sr-only">Open record</span></th></tr></thead><tbody>${rows.map(donorRow).join('')}</tbody></table></div>`;
}
