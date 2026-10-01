// Shared helpers for Cursor hook scripts (Node 18+, no dependencies).
import { writeSync } from 'node:fs';

export async function readInput() {
  const chunks = [];
  for await (const c of process.stdin) chunks.push(c);
  const raw = Buffer.concat(chunks).toString('utf8').trim();
  try { return raw ? JSON.parse(raw) : {}; } catch { return {}; }
}

// Writes synchronously and exits, so a hook can never emit two responses.
function respond(obj) {
  writeSync(1, JSON.stringify(obj) + '\n');
  process.exit(0);
}

// Permission hooks MUST print valid JSON: invalid output blocks the action.
export const allow = () => respond({ permission: 'allow' });

export const deny = (userMessage, agentMessage = userMessage) =>
  respond({ permission: 'deny', user_message: userMessage, agent_message: agentMessage });

export const projectDir = () => process.env.CURSOR_PROJECT_DIR || process.cwd();
