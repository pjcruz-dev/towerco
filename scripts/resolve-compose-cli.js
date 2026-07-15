const { execSync, spawnSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");

function readEnvDockerPreference() {
  const envPath = path.join(root, ".env.docker");
  if (!fs.existsSync(envPath)) {
    return "auto";
  }

  const match = fs.readFileSync(envPath, "utf8").match(/^TOWEROS_CONTAINER_CLI=(.+)$/m);
  if (!match) {
    return "auto";
  }

  return match[1].trim().toLowerCase();
}

function commandExists(command) {
  try {
    if (process.platform === "win32") {
      execSync(`where ${command}`, { stdio: "ignore" });
    } else {
      execSync(`command -v ${command}`, { stdio: "ignore" });
    }
    return true;
  } catch {
    return false;
  }
}

function podmanEngineReady() {
  if (!commandExists("podman")) {
    return false;
  }

  const info = spawnSync("podman", ["info"], { stdio: "ignore" });
  return info.status === 0;
}

function dockerEngineReady() {
  if (!commandExists("docker")) {
    return false;
  }

  const info = spawnSync("docker", ["info"], { stdio: "ignore" });
  return info.status === 0;
}

/**
 * @returns {string[]} e.g. ["podman", "compose"] or ["docker", "compose"]
 */
function resolveComposeInvocation() {
  const preference = process.env.TOWEROS_CONTAINER_CLI?.trim().toLowerCase() || readEnvDockerPreference();

  if (preference === "podman") {
    if (!podmanEngineReady()) {
      console.error(
        "[toweros] Podman is not running. Open Podman Desktop → Resources → start your machine.",
      );
      process.exit(1);
    }
    return ["podman", "compose"];
  }

  if (preference === "docker") {
    if (!dockerEngineReady()) {
      console.error("[toweros] Docker is not running. Start Docker Desktop.");
      process.exit(1);
    }
    return ["docker", "compose"];
  }

  if (podmanEngineReady()) {
    return ["podman", "compose"];
  }

  if (dockerEngineReady()) {
    return ["docker", "compose"];
  }

  console.error(
    "[toweros] No container engine found. Install Podman Desktop or Docker Desktop and start the VM/machine.",
  );
  process.exit(1);
}

module.exports = { resolveComposeInvocation };
