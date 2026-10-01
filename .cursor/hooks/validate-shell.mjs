// beforeShellExecution: block destructive or risky shell commands (bash and PowerShell).
import { readInput, allow, deny } from './lib.mjs';

const RULES = [
  [/\brm\s+(-\w+\s+)*(\/|~|\$HOME)(\s|$)/, 'recursive delete of root/home'],
  [/\b(rmdir|rd)\s+\/s\s+\/q\s+[a-z]:\\?(\s|$)/i, 'recursive delete of a drive root'],
  [/migrate:fresh|migrate:reset|db:wipe|tenants:migrate-fresh/, 'destructive Laravel database command'],
  [/npm\s+run\s+dev:fresh\b|docker-dev-fresh/, 'dev:fresh wipes the local database'],
  [/\b(drop|truncate)\s+(database|schema|table)\b/i, 'destructive SQL'],
  [/\bdocker(\s+compose)?\b.*\bdown\b.*(\s-v\b|--volumes)|compose-run\.js.*\bdown\b.*(\s-v\b|--volumes)/, 'removes Docker volumes (MySQL data)'],
  [/docker\s+(volume\s+(rm|prune)|system\s+prune)/, 'removes Docker volumes/images'],
  [/git\s+push\b.*(--force\b|--force-with-lease|\s-f(\s|$))/, 'force push'],
  [/git\s+reset\s+--hard/, 'git reset --hard'],
  [/git\s+clean\s+-\w*f/, 'git clean -f (deletes untracked files)'],
  [/chmod\s+-R\s+777/, 'chmod -R 777'],
  [/\b(curl|wget)\b[^|]*\|\s*(ba|z)?sh\b/, 'piping a download into a shell'],
  [/\b(iwr|irm|Invoke-WebRequest|Invoke-RestMethod)\b[^|]*\|\s*(iex|Invoke-Expression)\b/i, 'piping a download into Invoke-Expression'],
  [/\baws\s+(rds|ec2|s3|s3api)\b.*\b(delete|terminate|rb|rm)\b/, 'destructive AWS command'],
  [/artisan\b.*--env[= ]prod(uction)?\b/, 'artisan against production'],
  [/ui[-:]drift\b.*--update/, 'the UI drift baseline may only be updated by a human'],
];

const { command = '' } = await readInput();
const hit = RULES.find(([re]) => re.test(command));
if (hit) {
  const why = hit[1];
  deny(
    `Blocked: ${why}. Run it manually if you really intend to.`,
    `This command was blocked by .cursor/hooks/validate-shell.mjs (${why}). Explain why it is needed and ask the user to run it manually.`,
  );
}
allow();
