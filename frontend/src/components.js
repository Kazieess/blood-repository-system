import { escapeHtml as e, icon } from './ui.js';
export const action = (label, name, id = '', primary = false) => `<button class="button ${primary ? 'primary' : 'secondary'}" data-action="${name}" data-id="${e(id)}">${label}</button>`;
export function heading(eyebrow, title, description, controls = '') {
  return `<section class="page-heading"><div><p class="eyebrow">${e(eyebrow)}</p><h1>${e(title)}</h1><p>${e(description)}</p></div><div class="heading-actions">${controls}</div></section>`;
}
export function metrics(items) { return `<section class="metric-strip" aria-label="Sample summary">${items.map(([label, value, note]) => `<div><span>${e(label)}</span><strong>${e(value)}</strong><small>${e(note)}</small></div>`).join('')}</section>`; }
export function table(headers, rows, caption) {
  if (!rows.length) return `<div class="empty-state">${icon('search')}<h2>No matching records</h2><p>Try a different search or clear the filters.</p></div>`;
  return `<div class="table-scroll" tabindex="0" role="region" aria-label="${e(caption)}"><table><caption class="sr-only">${e(caption)}</caption><thead><tr>${headers.map(header => `<th scope="col">${e(header)}</th>`).join('')}</tr></thead><tbody>${rows.map(row => `<tr>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`;
}
export function pages(items, params, route, renderRows) {
  const totalPages = Math.max(1, Math.ceil(items.length / 8));
  const page = Math.min(Math.max(1, Math.floor(Number(params.get('page'))) || 1), totalPages);
  const start = (page - 1) * 8;
  const href = step => { const next = new URLSearchParams(params); next.set('page', String(step)); return `#/${route}?${e(next.toString())}`; };
  return `${renderRows(items.slice(start, start + 8))}<div class="pagination"><span>${items.length ? `Showing ${start + 1}–${Math.min(start + 8, items.length)} of ${items.length}` : 'No records'} · Page ${page} of ${totalPages}</span><div>${page > 1 ? `<a class="button secondary" href="${href(page - 1)}">Previous</a>` : '<button class="button secondary" disabled>Previous</button>'}${page < totalPages ? `<a class="button secondary" href="${href(page + 1)}">Next</a>` : '<button class="button secondary" disabled>Next</button>'}</div></div>`;
}
export function filterForm(route, params, extra = '') {
  return `<form class="filters" data-filter-form="${route}"><label class="search-field">${icon('search')}<span class="sr-only">Search records</span><input type="search" name="q" placeholder="Search records…" value="${e(params.get('q') ?? '')}"></label>${extra}<button class="button secondary" type="submit">Apply filters</button><a class="text-link" href="#/${route}">Reset</a></form>`;
}
export function selectFilter(name, label, options, value) { return `<label class="filter-field">${e(label)}<select name="${name}"><option value="">All</option>${options.map(option => `<option value="${e(option)}" ${option === value ? 'selected' : ''}>${e(option)}</option>`).join('')}</select></label>`; }
export function searchRows(rows, params) { const q = (params.get('q') ?? '').trim().toLowerCase(); return rows.filter(row => Object.values(row).join(' ').toLowerCase().includes(q)); }
export function denied() { return `<section class="panel empty-state">${icon('shield')}<h1>This workspace is restricted.</h1><p>The selected demo role cannot access this page. Switch roles to explore another proposed permission set.</p><a href="#/login" class="button primary">Choose a demo role</a><a href="#/home" class="button secondary">Public home</a></section>`; }
export function previewState(kind) {
  if (kind === 'loading') return `<section class="panel empty-state" role="status"><span class="loading-indicator" aria-hidden="true"></span><h1>Loading workspace</h1><p>Loading-state preview. Choose Ready to return to the screen.</p></section>`;
  if (kind === 'denied') return denied();
  return `<section class="panel empty-state" role="alert">${icon('info')}<h1>The workspace is unavailable.</h1><p>We couldn't load the records. Your current sample changes are still in this tab.</p>${action('Retry sample workspace', 'ready', '', true)}</section>`;
}
