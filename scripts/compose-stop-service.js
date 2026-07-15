#!/usr/bin/env node
/**
 * Stop and remove a compose service if it exists (ignore errors — e.g. not running).
 */
const path = require("path");
const { spawnSync } = require("child_process");
const { resolveComposeInvocation } = require("./resolve-compose-cli");

const service = process.argv[2];
if (!service) {
  process.exit(0);
}

const root = path.join(__dirname, "..");
const [runtime, subcommand] = resolveComposeInvocation();
const base = [subcommand, "--env-file", ".env.docker"];

for (const tail of [["stop", service], ["rm", "-f", service]]) {
  spawnSync(runtime, [...base, ...tail], {
    stdio: "ignore",
    cwd: root,
    shell: process.platform === "win32",
  });
}
