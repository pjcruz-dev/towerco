const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");
const target = path.join(root, ".env.docker");
const example = path.join(root, "env.docker.example");

if (!fs.existsSync(target) && fs.existsSync(example)) {
  fs.copyFileSync(example, target);
  console.log("[setup] Created .env.docker from env.docker.example");
}
