#!/usr/bin/env node
const { spawnSync } = require("child_process");
const { resolveComposeInvocation } = require("./resolve-compose-cli");

const args = process.argv.slice(2);
const [runtime, subcommand, ...rest] = resolveComposeInvocation();
const command = runtime;
const commandArgs = [subcommand, ...rest, ...args];

const result = spawnSync(command, commandArgs, {
  stdio: "inherit",
  shell: process.platform === "win32",
  cwd: require("path").join(__dirname, ".."),
});

process.exit(result.status ?? 1);
