// afterFileEdit: run Pint on PHP files the agent just wrote. Never blocks.
// (No Prettier is installed in frontend/, so frontend files are left to ESLint/`npm run lint`.)
import path from 'node:path';
import { spawnSync } from 'node:child_process';
import { readInput, projectDir } from './lib.mjs';

const { file_path: filePath = '' } = await readInput();
const root = projectDir();
const backend = path.join(root, 'backend');
const norm = (p) => (process.platform === 'win32' ? path.resolve(p).toLowerCase() : path.resolve(p));
const insideBackend = norm(filePath).startsWith(norm(backend) + path.sep);
const skip = /[\\/](vendor|storage|bootstrap[\\/]cache)[\\/]/.test(filePath);

try {
  if (filePath.endsWith('.php') && insideBackend && !skip) {
    // `php vendor/bin/pint` works the same on Windows and Linux (no .bat lookup).
    spawnSync('php', ['vendor/bin/pint', filePath], { cwd: backend, stdio: 'ignore', timeout: 45000 });
  }
} catch { /* formatting must never break the agent */ }
process.exit(0);
