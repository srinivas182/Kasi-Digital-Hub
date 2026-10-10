#!/usr/bin/env node
/**
 * Fails CI when JavaScript downloaded by phones grows past the budgets
 * (docs/design-system.md#performance-budgets). Sizes are gzip, as downloaded.
 *
 * - Entry: the app bootstrap (React, Inertia) loaded on every first visit.
 * - Page: each page plus the shared chunks it pulls in (layouts, components),
 *   excluding what the entry already loaded.
 * - Public first load: entry + public home page - what a new visitor downloads.
 */
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const ENTRY = 'resources/js/app.tsx';
const PUBLIC_HOME = 'modules/Site/resources/js/Pages/Home.tsx';
const ENTRY_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_ENTRY_KB ?? 150);
const PAGE_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_PAGE_KB ?? 60);
// Author-only tools (course editor) run on desktops, are never loaded by learners and carry a rich-text
// editor; they get their own budget (docs/design-system.md#performance-budgets).
const AUTHOR_PAGES = ['modules/Learn/resources/js/Pages/Author/'];
const AUTHOR_PAGE_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_AUTHOR_PAGE_KB ?? 200);
const FIRST_LOAD_BUDGET_KB = Number(process.env.BUNDLE_BUDGET_FIRST_LOAD_KB ?? 130);
// Internal pages that are never served in production (they deliberately load every component).
const EXEMPT = ['modules/Core/resources/js/Pages/UiKit/'];

const manifest = JSON.parse(readFileSync('public/build/manifest.json', 'utf8'));
const gz = (file) => gzipSync(readFileSync(`public/build/${file}`)).length / 1024;

function closure(key, seen = new Set()) {
    if (seen.has(key) || !manifest[key]) return seen;
    seen.add(key);
    for (const dep of manifest[key].imports ?? []) closure(dep, seen);
    return seen;
}

const size = (keys) => [...keys].reduce((total, key) => total + gz(manifest[key].file), 0);

const entryKeys = closure(ENTRY);
const entry = size(entryKeys);
let failed = entry > ENTRY_BUDGET_KB;
console.log(`Entry JS: ${entry.toFixed(1)} KB gzip (budget ${ENTRY_BUDGET_KB} KB)`);

for (const key of Object.keys(manifest)) {
    if (!key.includes('/resources/js/Pages/') || !manifest[key].file.endsWith('.js')) continue;
    const own = [...closure(key)].filter((k) => !entryKeys.has(k));
    const pageSize = size(own);
    const exempt = EXEMPT.some((prefix) => key.startsWith(prefix));
    const budget = AUTHOR_PAGES.some((prefix) => key.startsWith(prefix)) ? AUTHOR_PAGE_BUDGET_KB : PAGE_BUDGET_KB;
    const over = !exempt && pageSize > budget;
    failed ||= over;
    console.log(`Page ${key.replace(/^modules\//, '').replace('/resources/js/Pages/', '/')}: ${pageSize.toFixed(1)} KB gzip${exempt ? ' (internal, exempt)' : over ? '  <-- over budget' : ''}`);
}

if (manifest[PUBLIC_HOME]) {
    const firstLoad = size(new Set([...entryKeys, ...closure(PUBLIC_HOME)]));
    const over = firstLoad > FIRST_LOAD_BUDGET_KB;
    failed ||= over;
    console.log(`Public first load (entry + home): ${firstLoad.toFixed(1)} KB gzip (budget ${FIRST_LOAD_BUDGET_KB} KB)`);
}

if (failed) {
    console.error('Bundle budget exceeded. Lazy-load heavy code or raise the budget with a documented reason.');
    process.exit(1);
}
