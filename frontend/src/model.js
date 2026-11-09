import { donors, bloodTypes, stock } from './data.js';

export const demoDate = '2026-11-06';
export const roles = ['Red Cross Administrator', 'Red Cross Personnel', 'Barangay Health Coordinator', 'Report Viewer', 'System Administrator'];
export const permissions = {
  'Red Cross Administrator': ['overview', 'donors', 'inventory', 'reports', 'notifications', 'audit', 'settings'],
  'Red Cross Personnel': ['overview', 'donors', 'inventory', 'reports', 'notifications'],
  'Barangay Health Coordinator': ['overview', 'donors'],
  'Report Viewer': ['reports'],
  'System Administrator': ['users', 'backups', 'audit', 'settings'],
};
export const state = {
  session: null, donors,
  units: stock.flatMap((item, group) => Array.from({ length: item.units }, (_, index) => ({
    id: `BAG-${String(group * 30 + index + 1).padStart(4, '0')}`, blood: item.type,
    component: 'Whole blood', collected: '2026-10-25', expiry: index === 0 ? '2026-11-09' : '2026-11-28',
    location: 'Digos City repository', status: 'Available', verified: true,
  }))),
  donations: donors.flatMap(donor => Array.from({ length: donor.donations }, (_, index) => {
    const date = new Date(`${donor.lastDonation}T12:00:00Z`);
    date.setUTCMonth(date.getUTCMonth() - index * 4);
    return { id: `${donor.id}-D${index + 1}`, donorId: donor.id, date: date.toISOString().slice(0, 10), location: donor.area, type: 'Whole blood' };
  })),
  notifications: [
    { id: 'SMS-0001', recipient: 'Chapter personnel (sample)', event: 'Expiry review', message: 'Please review the sample units listed in the expiry report.', status: 'Queued', attempts: 0 },
    { id: 'SMS-0002', recipient: 'Demo donor group', event: 'Donor reminder', message: 'Please contact chapter personnel for an eligibility assessment.', status: 'Failed', attempts: 1 },
  ],
  users: [
    { id: 'USR-001', name: 'Demo Operations Lead', role: roles[0], active: true },
    { id: 'USR-002', name: 'Demo Chapter Staff', role: roles[1], active: true },
    { id: 'USR-003', name: 'Demo Field Coordinator', role: roles[2], active: true },
    { id: 'USR-004', name: 'Demo Report Reviewer', role: roles[3], active: false },
    { id: 'USR-005', name: 'Demo Technical Owner', role: roles[4], active: true },
  ],
  backups: [{ id: 'BKP-001', timestamp: `${demoDate}T02:00:00+08:00`, status: 'Sample success', records: donors.length, note: 'Illustrative backup metadata only; no backup file exists.' }],
  audit: [],
  settings: { chapter: 'Davao del Sur Chapter', refreshMinutes: 15, alertDays: 7, backupTime: '02:00', retentionDays: 30 },
  publicSnapshot: [], publicTimestamp: '', publicMode: 'normal', preview: 'ready',
};
let sequence = 100;
export function nextId(prefix) { sequence += 1; return `${prefix}-${String(sequence).padStart(4, '0')}`; }
export function can(section) { return Boolean(state.session && permissions[state.session.role]?.includes(section)); }
export function log(action, reference, reason = '') {
  state.audit.unshift({ id: nextId('EVT'), timestamp: new Date().toISOString(), operator: state.session?.name ?? 'Demo visitor', action, reference, reason });
}
export function requirePermission(section) { if (!can(section)) throw new Error('This demo role does not have permission for that action.'); }
export function signIn(role) {
  if (!roles.includes(role)) throw new Error('Select a valid demo role.');
  state.session = { name: 'Demo user', role };
  state.preview = 'ready';
  return permissions[role][0];
}
export function availableUnits() { return state.units.filter(unit => unit.status === 'Available' && unit.verified && unit.expiry >= demoDate); }
export function stockSummary() { return bloodTypes.map(type => ({ type, units: availableUnits().filter(unit => unit.blood === type).length })); }
export function publishSnapshot(recordAudit = true) {
  if (recordAudit) requirePermission('inventory');
  state.publicSnapshot = stockSummary().map(item => ({ ...item }));
  state.publicTimestamp = new Date().toISOString();
  state.publicMode = 'normal';
  if (recordAudit) log('Publish sample availability', 'Public snapshot', 'Explicit demo publication');
}
publishSnapshot(false);
export function addDonor(values) {
  requirePermission('donors');
  const donor = { id: nextId('DDS'), name: required(values.name, 'Full name'), area: required(values.area, 'Municipality'), blood: values.blood, status: 'For review', lastDonation: null, donations: 0, registered: demoDate };
  if (!bloodTypes.includes(donor.blood)) throw new Error('Select a blood type.');
  state.donors.unshift(donor); log('Create sample donor', donor.id); return donor;
}
export function updateDonor(id, values) {
  requirePermission('donors');
  const donor = state.donors.find(item => item.id === id);
  if (!donor) throw new Error('Donor record not found.');
  const name = required(values.name, 'Full name'); const area = required(values.area, 'Municipality');
  if (!bloodTypes.includes(values.blood)) throw new Error('Select a blood type.');
  const reason = required(values.reason, 'Correction reason');
  Object.assign(donor, { name, area, blood: values.blood }); log('Correct sample donor', id, reason);
}
export function recordEligibility(id, values) {
  requirePermission('donors');
  const donor = state.donors.find(item => item.id === id);
  if (!donor) throw new Error('Donor record not found.');
  if (!['Eligible', 'Deferred', 'For review', 'Inactive'].includes(values.status)) throw new Error('Select a recorded status.');
  const reason = required(values.reason, 'Assessment reference');
  if (values.until && !validDate(values.until)) throw new Error('Enter a valid review date.');
  donor.status = values.status; donor.reviewDate = values.until || null;
  log('Record sample staff assessment', id, reason);
}
export function addDonation(id, values) {
  requirePermission('donors');
  const donor = state.donors.find(item => item.id === id);
  if (!donor) throw new Error('Donor record not found.');
  if (!validDate(values.date) || values.date > demoDate) throw new Error('Use a valid donation date on or before the sample date.');
  const location = required(values.location, 'Location');
  if (state.donations.some(item => item.donorId === id && item.date === values.date)) throw new Error('A sample donation already exists for this donor on that date.');
  state.donations.unshift({ id: nextId('DON'), donorId: id, date: values.date, location, type: 'Whole blood' });
  donor.donations += 1; donor.lastDonation = [donor.lastDonation, values.date].filter(Boolean).sort().at(-1);
  log('Record sample donation', id, values.date);
}
export function addUnit(values) {
  requirePermission('inventory');
  if (!bloodTypes.includes(values.blood)) throw new Error('Select a blood type.');
  if (!validDate(values.collected) || !validDate(values.expiry) || values.expiry < values.collected) throw new Error('Expiry must be a valid date on or after collection.');
  if (values.collected > demoDate) throw new Error('Collection cannot be after the sample date.');
  const reference = required(values.reference, 'Bag reference');
  if (!/^[A-Za-z0-9_-]+$/.test(reference)) throw new Error('Use letters, numbers, underscores and hyphens in the bag reference.');
  if (state.units.some(unit => unit.id.toLowerCase() === reference.toLowerCase())) throw new Error('That bag reference is already in use.');
  state.units.unshift({ id: reference, blood: values.blood, component: 'Whole blood', collected: values.collected, expiry: values.expiry, location: required(values.location, 'Location'), status: 'Pending verification', verified: false });
  log('Add sample unit', reference); return reference;
}
export function changeUnit(id, values) {
  requirePermission('inventory');
  const unit = state.units.find(item => item.id === id);
  if (!unit) throw new Error('Inventory unit not found.');
  const reason = required(values.reason, 'Reason');
  if (values.operation === 'verify') {
    if (unit.status !== 'Pending verification' || unit.expiry < demoDate) throw new Error('Only unexpired pending units may be verified in this demo.');
    unit.verified = true; unit.status = 'Available';
  } else {
    if (!['Released', 'Discarded', 'Quarantined'].includes(values.status)) throw new Error('Choose a valid stock action.');
    if (['Released', 'Discarded'].includes(unit.status)) throw new Error('This unit has already left active stock.');
    if (values.status === 'Released' && (!unit.verified || unit.status !== 'Available' || unit.expiry < demoDate)) throw new Error('Only verified, available, unexpired sample units can be released.');
    unit.status = values.status;
  }
  log(values.operation === 'verify' ? 'Verify sample unit' : `Sample stock: ${unit.status}`, id, `Quantity: 1. ${reason}`);
}
export function required(value, label) {
  const result = String(value ?? '').trim();
  if (!result) throw new Error(`${label} is required.`);
  if (result.length > 200) throw new Error(`${label} must be 200 characters or fewer.`);
  return result;
}
export function validDate(value) { return /^\d{4}-\d{2}-\d{2}$/.test(value ?? '') && !Number.isNaN(Date.parse(value)) && new Date(`${value}T12:00:00Z`).toISOString().slice(0, 10) === value; }
export function within(date, from = '', to = '') { return (!from || date >= from) && (!to || date <= to); }
export function reportRows(type, from, to) {
  if (type === 'donors') return state.donors.filter(item => within(item.registered, from, to)).map(({ id, name, blood, area, status }) => ({ id, name, blood, area, status }));
  if (type === 'donations') return state.donations.filter(item => within(item.date, from, to)).map(({ id, donorId, date, location, type: donationType }) => ({ id, donorId, date, location, type: donationType }));
  return state.units.filter(item => within(type === 'expiry' ? item.expiry : item.collected, from, to)).map(({ id, blood, collected, expiry, status }) => ({ id, blood, collected, expiry, status }));
}
export function csv(rows) {
  if (!rows.length) return '';
  const keys = Object.keys(rows[0]);
  const quote = value => {
    const text = String(value ?? '');
    const safe = /^\s*[=+@\-]|^[\t\r\n]/.test(text) ? "'" + text : text;
    return `"${safe.replaceAll('"', '""')}"`;
  };
  return [keys.map(quote).join(','), ...rows.map(row => keys.map(key => quote(row[key])).join(','))].join('\r\n');
}
