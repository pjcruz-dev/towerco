#!/usr/bin/env node
// UI drift ratchet (no dependencies, Node 18+).
//
// Counts hard-coded colours in frontend code: hex values, rgb()/hsl()/oklch() arbitrary
// values, and undocumented Tailwind palette classes (bg-blue-600, text-slate-600, ...).
// The status families documented in DESIGN_SYSTEM.md 17.1 (emerald, amber, red, sky) are NOT counted:
// they are the approved status palette, and belong in the shared StatusBadge / statusToneClasses.
// Existing drift is recorded in scripts/ui-drift-baseline.json. The check FAILS only when a
// file gets worse than its baseline (or a new file introduces any), so legacy screens do not
// block work, but nothing new slips in. Clean a file up, then re-lock with --update.
//
//   node scripts/ui-drift.mjs             check against the baseline (CI, before finishing work)
//   node scripts/ui-drift.mjs --report    print totals and the worst files, never fails
//   node scripts/ui-drift.mjs --update    rewrite the baseline (a human runs this, not the agent)

import { readdirSync, readFileSync, writeFileSync, existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const frontend = path.join(root, 'frontend');
const baselinePath = path.join(root, 'scripts', 'ui-drift-baseline.json');

const flags = new Set(process.argv.slice(2));
const UPDATE = flags.has('--update');
const REPORT = flags.has('--report');

const SKIP_DIRS = new Set(['node_modules', '.next', 'dist', 'coverage', 'public', '.turbo']);
const EXT = /\.(tsx|ts|jsx|js|css)$/;

// Deliberate exceptions. Changing this list is a design decision, so a human does it.
const ALLOW = [
  /(^|\/)app\/globals\.css$/, // token definitions
  /(^|\/)app\/tokens\.css$/, // token definitions
  /print/i, // print/PDF templates and the (print) route group need fixed colours
  /chart-utils/i, // chart series palettes
  /sidebar/i, // DESIGN_SYSTEM.md 2.4 documents slate classes for the sidebar
  /\.test\.(ts|tsx)$/,
];

// Files belonging to the active modules (Ticketing, E-Forms, DocExtract, Document Control).
const ACTIVE = /(ticketing|e-approval|doc-extract|documents|controlled-documents)/i;

const HEX = /(?<![\w&#])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])/g;
const FUNC = /\b(?:bg|text|border|ring|fill|stroke)-\[(?:rgb|hsl|oklch)a?\([^\]]*\]/g;
const PALETTE =
  /\b(?:bg|text|border|ring|outline|divide|from|via|to|fill|stroke|decoration|accent|caret|shadow)-(?:orange|yellow|lime|green|teal|cyan|blue|indigo|violet|purple|fuchsia|pink|rose|slate|gray|zinc|neutral|stone)-\d{2,3}\b/g;

function* walk(dir) {
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    if (entry.isDirectory()) {
      if (!SKIP_DIRS.has(entry.name)) yield* walk(path.join(dir, entry.name));
    } else if (EXT.test(entry.name)) {
      yield path.join(dir, entry.name);
    }
  }
}

function countFile(abs) {
  let n = 0;
  for (const line of readFileSync(abs, 'utf8').split(/\r?\n/)) {
    if (/href=|url\(#|&#/.test(line)) continue; // anchors and SVG refs, not colours
    n += (line.match(HEX) ?? []).length;
    n += (line.match(FUNC) ?? []).length;
    n += (line.match(PALETTE) ?? []).length;
  }
  return n;
}

if (!existsSync(frontend)) {
  console.error(`ui-drift: ${frontend} not found. Run from the TowerOS repo (script lives in scripts/).`);
  process.exit(2);
}

const current = {};
for (const abs of walk(frontend)) {
  const rel = path.relative(frontend, abs).split(path.sep).join('/');
  if (ALLOW.some((re) => re.test(rel))) continue;
  const n = countFile(abs);
  if (n > 0) current[rel] = n;
}

const sum = (o) => Object.values(o).reduce((a, b) => a + b, 0);
const active = Object.fromEntries(Object.entries(current).filter(([f]) => ACTIVE.test(f)));

if (UPDATE) {
  const sorted = Object.fromEntries(Object.entries(current).sort(([a], [b]) => a.localeCompare(b)));
  writeFileSync(baselinePath, JSON.stringify({ files: sorted }, null, 2) + '\n');
  console.log(`ui-drift: baseline written (${Object.keys(current).length} files, ${sum(current)} matches).`);
  process.exit(0);
}

if (REPORT) {
  console.log(`Total drift: ${sum(current)} matches in ${Object.keys(current).length} files`);
  console.log(`  active modules (ticketing, e-approval, doc-extract, documents): ${sum(active)} in ${Object.keys(active).length} files`);
  console.log(`  everything else: ${sum(current) - sum(active)}`);
  console.log('\nWorst files in the active modules:');
  Object.entries(active).sort((a, b) => b[1] - a[1]).slice(0, 15)
    .forEach(([f, n]) => console.log(String(n).padStart(5), f));
  console.log('\nWorst files overall:');
  Object.entries(current).sort((a, b) => b[1] - a[1]).slice(0, 15)
    .forEach(([f, n]) => console.log(String(n).padStart(5), f));
  process.exit(0);
}

if (!existsSync(baselinePath)) {
  console.error('ui-drift: no baseline yet. A human should run: node scripts/ui-drift.mjs --update');
  process.exit(1);
}

const base = JSON.parse(readFileSync(baselinePath, 'utf8')).files ?? {};
const worse = [];
const better = [];
for (const [f, n] of Object.entries(current)) {
  const b = base[f] ?? 0;
  if (n > b) worse.push([f, b, n]);
}
for (const [f, b] of Object.entries(base)) {
  const n = current[f] ?? 0;
  if (n < b) better.push([f, b, n]);
}

if (worse.length) {
  console.error('ui-drift: FAILED. These files gained hard-coded colours:\n');
  for (const [f, b, n] of worse) console.error(`  ${f}: ${b} -> ${n}`);
  console.error(
    '\nUse semantic tokens (bg-primary, text-muted-foreground, border-border, text-destructive),' +
      '\nthe shared StatusBadge (components/ui/status-badge.tsx) for statuses instead. If a file is a print template or a chart' +
      '\npalette, ask a human to add it to ALLOW in scripts/ui-drift.mjs. Do not edit the baseline.',
  );
  process.exit(1);
}

console.log(`ui-drift: OK (${sum(current)} matches, none added since the baseline).`);
if (better.length) {
  console.log(`\n${better.length} file(s) improved. A human can lock that in with: node scripts/ui-drift.mjs --update`);
}
