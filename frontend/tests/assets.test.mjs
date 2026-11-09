import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile, access } from 'node:fs/promises';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
test('both frontend entries reference existing relocated stylesheets and scripts', async () => {
  for (const entry of ['index.html', 'connected.html']) {
    const html = await readFile(resolve(root, entry), 'utf8');
    const links = [...html.matchAll(/(?:href|src)="([^"#]+\.(?:css|js))"/g)].map(match => match[1]);
    assert.ok(links.length >= 3);
    for (const path of links) { assert.ok(!path.startsWith('http')); await access(resolve(root, path)); }
    assert.ok(html.includes('css/styles.css')); assert.ok(html.includes('css/experience.css'));
  }
});
test('the connected entry dependency graph never imports the fictional donor store', async () => {
  const visited = new Set();
  async function inspect(path) {
    if (visited.has(path)) return; visited.add(path);
    assert.ok(!['data.js', 'model.js', 'views.js', 'operations.js'].some(name => path.endsWith('/' + name) || path.endsWith('\\' + name)), path);
    const source = await readFile(path, 'utf8');
    for (const match of source.matchAll(/(?:from\s+|import\s*)['"](\.\.?\/[^'"]+\.js)['"]/g)) await inspect(resolve(dirname(path), match[1]));
  }
  await inspect(resolve(root, 'src/connected.js'));
  assert.ok([...visited].some(path => path.endsWith('workspace.js')));
  assert.ok([...visited].some(path => path.endsWith('api.js')));
});
