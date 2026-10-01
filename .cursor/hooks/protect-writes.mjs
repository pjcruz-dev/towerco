// preToolUse (Write|Delete): protect secrets, lockfiles, VCS internals, hooks, and committed migrations.
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { readInput, allow, deny, projectDir } from './lib.mjs';

const input = await readInput();
const ti = input.tool_input ?? {};
// Key name differs between Cursor tools/versions, so try the common ones.
const raw = ti.file_path ?? ti.path ?? ti.target_file ?? ti.filePath ?? ti.file ?? '';
if (!raw || typeof raw !== 'string') allow();

const root = projectDir();
const abs = path.isAbsolute(raw) ? raw : path.resolve(input.cwd || root, raw);
const rel = path.relative(root, abs).split(path.sep).join('/');
const base = path.basename(abs);

const blocked = (why) =>
  deny(`Blocked: ${rel} (${why}).`, `Editing ${rel} is blocked by .cursor/hooks/protect-writes.mjs (${why}). ${HINT[why] ?? 'Ask the user.'}`);

const HINT = {
  'secrets file': 'Never write secrets. Update .env.example and tell the user which values to set.',
  'lockfile': 'Use the package manager (composer / npm) instead of editing lockfiles.',
  'git internals': 'Use git commands instead.',
  'agent guardrails': 'Ask the user to change hooks and ignore files manually.',
  'committed migration': 'Create a NEW migration (expand/contract) instead of editing a committed one.',
};

if (/^\.env(\..+)?$/.test(base) && !/\.(example|sample|dist)$/.test(base)) blocked('secrets file');
if (/\.(pem|key|p12|pfx)$/i.test(base)) blocked('secrets file');
if (['composer.lock', 'pnpm-lock.yaml', 'package-lock.json', 'yarn.lock'].includes(base)) blocked('lockfile');
if (rel.startsWith('.git/')) blocked('git internals');
if (rel === '.cursor/hooks.json' || rel.startsWith('.cursor/hooks/') || rel === '.cursorignore' ||
    rel === 'scripts/ui-drift.mjs' || rel === 'scripts/ui-drift-baseline.json') blocked('agent guardrails');

if (/(^|\/)database\/migrations\//.test(rel)) {
  const tracked = spawnSync('git', ['ls-files', '--error-unmatch', rel], { cwd: root, stdio: 'ignore' });
  if (tracked.status === 0) blocked('committed migration');
}
allow();
