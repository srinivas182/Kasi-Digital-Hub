#!/usr/bin/env node
/**
 * Fails CI when the JavaScript downloaded on first load grows past the budget.
 *
 * First load = the app entry chunk plus its static imports (shared vendor code).
 * Portal pages are lazy-loaded and budgeted separately per page.
 * Budgets are gzip sizes because that is what phones actually download.
 */
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const ENTRY = 'resources/js/app.tsx';
const ENTRY_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_ENTRY_KB ?? 150);
const PAGE_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_PAGE_KB ?? 60);

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const gz = (file) => gzipSync(readFileSync(`public/build/${file}`)).length / 1024;

const seen = new Set();
function staticSize(key) {
    if (seen.has(key)) return 0;
    seen.add(key);
    const chunk = manifest[key];
    let size = gz(chunk.file);
    for (const dep of chunk.imports ?? []) size += staticSize(dep);
    return size;
}

let failed = false;
const entry = staticSize(ENTRY);
console.log(`First-load JS: ${entry.toFixed(1)} KB gzip (budget ${ENTRY_BUDGET_KB} KB)`);
if (entry > ENTRY_BUDGET_KB) failed = true;

for (const [key, chunk] of Object.entries(manifest)) {
    if (!key.includes('/resources/js/Pages/') || !chunk.file.endsWith('.js')) continue;
    const size = gz(chunk.file);
    const flag = size > PAGE_BUDGET_KB ? '  <-- over budget' : '';
    console.log(`Page ${key.replace(/^modules\//, '')}: ${size.toFixed(1)} KB gzip${flag}`);
    if (size > PAGE_BUDGET_KB) failed = true;
}

if (failed) {
    console.error('Bundle budget exceeded. Lazy-load heavy code or raise the budget with a documented reason.');
    process.exit(1);
}
