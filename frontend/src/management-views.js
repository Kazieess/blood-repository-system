import { escapeHtml as e } from './ui.js';
import { heading } from './components.js';
import { timestamp, pager } from './workspace-ui.js';
import { input, select, records, action, notice } from './operations-views.js';
const wrap = html => '<section class="panel padded">' + html + '</section>';
export function reports({ data, params, section }) {
  const donations = section === 'donation-reports';
  const kind = donations ? 'donations' : params.get('kind') || 'inventory';
  const columns = donations ? ['donated_on', 'status', 'units'] : ['blood_type', 'component', 'location', 'stock_state', 'units'];
  const titles = donations ? ['Donation date', 'State', 'Events'] : ['Blood type', 'Component', 'Location', 'State', 'Units'];
  return heading('REPORTS', donations ? 'Assigned donation totals' : 'Inventory & expiry reports', data.scope, action('Download filtered CSV', 'report-export')) + notice('Generated ' + timestamp(data.generated_at) + '. Filters apply to collection dates for inventory, expiry dates for expiry reports, and donation dates for donation reports (Philippine time).') + wrap('<form class="filters" data-filter-form="' + section + '">' + (!donations ? select('kind', 'Report', ['inventory', 'expiry'], kind) : '') + input('from', 'From date', params.get('from'), 'date') + input('to', 'To date', params.get('to'), 'date') + input('location', 'Exact location', params.get('location'), 'text', 'maxlength="100"') + '<button class="button secondary">Apply filters</button></form>' + records(titles, data.data.map(row => columns.map(key => e(row[key]))), 'Filtered report aggregates') + pager({ ...data.meta, from: (data.meta.current_page - 1) * 25 + 1 }, params, section, data.data.length));
}
export function publications({ data }) {
  return heading('PUBLIC AVAILABILITY', 'Publish approved stock counts', 'Publish a selected storage location under an approved public label. No donor records or bag references are published.', action('Prepare publication', 'publication-new', '', true)) + notice('Publishing replaces the current public snapshot. Stock changes or the first included expiry mark it stale. Visitors see no stale counts.') + '<p><a class="text-link" href="/availability" target="_blank" rel="noopener">Open connected public availability</a></p>' + wrap(records(['Published', 'Valid until', 'State', 'Action'], data.data.map(row => [e(timestamp(row.published_at)), e(timestamp(row.valid_until)), row.withdrawn_at ? 'Withdrawn' : new Date(row.valid_until) <= new Date() ? 'Stale' : 'Within validity period', row.withdrawn_at ? '' : action('Withdraw publication', 'publication-withdraw', row.id)]), 'Recent publications'));
}
export function staff({ data, params }) {
  return heading('SYSTEM ADMINISTRATION', 'Staff accounts', 'Changing role or active status invalidates prior sessions and revokes donor assignments and clinical grants.', action('Create staff account', 'staff-new', '', true)) + wrap('<form class="filters" data-filter-form="staff">' + input('q', 'Name or email', params.get('q'), 'search', 'maxlength="120"') + '<button class="button secondary">Search</button></form>' + records(['Name', 'Email', 'Role', 'State', 'Manage'], data.data.map(row => [e(row.name), e(row.email), e(row.role), row.active ? 'Active' : 'Inactive', action('Edit account', 'staff-edit', row.id)]), 'Staff directory') + pager({ ...data.meta, from: (data.meta.current_page - 1) * 20 + 1 }, params, 'staff', data.data.length));
}
