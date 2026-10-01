// beforeReadFile: keep secrets out of the model context.
// (.cursorignore covers indexing. This covers explicit reads.)
import path from 'node:path';
import { readInput, allow, deny } from './lib.mjs';

const { file_path: filePath = '' } = await readInput();
const base = path.basename(filePath);

const isEnv = /^\.env(\..+)?$/.test(base) && !/\.(example|sample|dist)$/.test(base);
const isKey = /\.(pem|key|p12|pfx)$/i.test(base) || /^id_(rsa|ed25519|ecdsa)$/.test(base);

if (isEnv || isKey) {
  deny(
    `Blocked: ${base} may contain secrets.`,
    `Reading ${base} is blocked by .cursor/hooks/protect-reads.mjs. Ask the user for the specific non-secret value you need, or use .env.example.`,
  );
} else {
  allow();
}
