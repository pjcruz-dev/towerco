#!/usr/bin/env node
/**
 * Local-only: scan frontend page clients for React Query / API usage.
 * Run: node scripts/profile-local-page-apis.mjs
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const appDir = path.join(root, "frontend", "app");
const hooksDir = path.join(root, "frontend", "hooks");
const apiDir = path.join(root, "frontend", "lib", "api", "modules");

function walk(dir, acc = []) {
  if (!fs.existsSync(dir)) return acc;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full, acc);
    else if (/page-client\.tsx$|page\.tsx$/.test(entry.name)) acc.push(full);
  }
  return acc;
}

function moduleFromPath(filePath) {
  const rel = path.relative(appDir, filePath).replace(/\\/g, "/");
  const seg = rel.split("/").filter(Boolean);
  if (seg[0] === "(platform)") return seg[1] ?? "platform";
  if (seg[0] === "(public)") return `public/${seg[1] ?? "public"}`;
  if (seg[0] === "(print)") return `print/${seg[1] ?? "print"}`;
  if (seg[0] === "(request-focus)") return "request-focus";
  return seg[0] ?? "root";
}

function routeFromPath(filePath) {
  const rel = path.relative(appDir, filePath).replace(/\\/g, "/");
  let route = rel
    .replace(/\/page-client\.tsx$/, "")
    .replace(/\/page\.tsx$/, "")
    .replace(/-page-client\.tsx$/, "")
    .replace(/^\([^)]+\)\//, "");
  if (!route.endsWith("/page") && route.includes("/") === false) {
    route = route.replace(/-page-client$/, "");
  }
  return "/" + route.replace(/\[([^\]]+)\]/g, ":$1");
}

function extractApis(content) {
  const apis = new Set();

  const importMap = new Map();
  for (const m of content.matchAll(/import\s*\{([^}]+)\}\s*from\s*["']@\/lib\/api\/modules\/([^"']+)["']/g)) {
    const names = m[1].split(",").map((s) => s.trim().split(/\s+as\s+/)[0].trim());
    for (const name of names) {
      if (name) importMap.set(name, m[2]);
    }
  }

  for (const m of content.matchAll(/useQuery\s*\(\s*\{[\s\S]*?queryFn:\s*([^,}\n]+)/g)) {
    const fn = m[1].trim();
    const direct = fn.match(/^([a-zA-Z0-9_]+)/);
    if (direct) apis.add(direct[1]);
  }

  for (const m of content.matchAll(/queryFn:\s*\(\)\s*=>\s*([a-zA-Z0-9_]+)\s*\(/g)) {
    apis.add(m[1]);
  }

  for (const m of content.matchAll(/queryFn:\s*([a-zA-Z0-9_]+)\b/g)) {
    apis.add(m[1]);
  }

  for (const m of content.matchAll(/\b(fetch[A-Z][a-zA-Z0-9_]*|platform[A-Z][a-zA-Z0-9_]*|me)\s*\(/g)) {
    apis.add(m[1]);
  }

  return [...apis].sort().map((name) => ({
    fn: name,
    module: importMap.get(name) ?? guessModule(name),
  }));
}

function guessModule(name) {
  if (name.startsWith("fetchEApproval") || name.startsWith("createEApproval")) return "e-approval-api";
  if (name.startsWith("fetchProcurement") || name.startsWith("createProcurement")) return "procurement-one-api";
  if (name.startsWith("fetchProjectOne") || name.startsWith("createProjectOne")) return "project-one-api";
  if (name.startsWith("fetchControlled") || name.startsWith("lookupControlled")) return "controlled-documents-api";
  if (name.startsWith("fetchTicketing")) return "ticketing-api";
  if (name.startsWith("platform")) return "platform-api";
  if (name === "me") return "auth-api";
  if (name.startsWith("fetchTenant")) return "admin-api";
  if (name.startsWith("fetchDocuments")) return "documents-api";
  if (name.startsWith("fetchWorkspace")) return "workspace-dashboard-api";
  return "?";
}

function readHookApis(hookName) {
  const hookPath = path.join(hooksDir, `${hookName}.ts`);
  if (!fs.existsSync(hookPath)) return [];
  return extractApis(fs.readFileSync(hookPath, "utf8"));
}

const GLOBAL_EVERY_TENANT_PAGE = [
  { fn: "me", module: "auth-api", note: "AppProviders + PlatformGuard if user missing" },
  { fn: "me", module: "auth-api", note: "useAuthProfileSync on every platform page" },
];

const files = walk(appDir);
const byModule = new Map();

for (const file of files) {
  const content = fs.readFileSync(file, "utf8");
  if (!content.includes("useQuery") && !content.includes("useMutation") && !content.includes("fetch")) {
    continue;
  }

  const route = routeFromPath(file);
  const mod = moduleFromPath(file);
  let apis = extractApis(content);

  for (const m of content.matchAll(/use([A-Z][a-zA-Z0-9]+)\s*\(/g)) {
    const hook = m[1];
    if (hook.startsWith("Query") || hook === "Mutation") continue;
    const hookFile = `use-${hook.replace(/([A-Z])/g, (x, c, i) => (i ? "-" : "") + c.toLowerCase())}`;
    const fromHook = readHookApis(hookFile);
    apis = [...apis, ...fromHook];
  }

  const key = `${mod}`;
  if (!byModule.has(key)) byModule.set(key, []);
  byModule.get(key).push({ route, file: path.relative(root, file), apis: dedupeApis(apis) });
}

function dedupeApis(apis) {
  const seen = new Set();
  return apis.filter((a) => {
    const k = `${a.fn}@${a.module}`;
    if (seen.has(k)) return false;
    seen.add(k);
    return true;
  });
}

const outPath = path.join(root, "scripts", "output", "local-page-api-inventory.json");
fs.mkdirSync(path.dirname(outPath), { recursive: true });

const summary = {
  generated_at: new Date().toISOString(),
  note: "Local dev inventory — API calls discovered in page-client files. Global calls apply to most tenant pages.",
  global_tenant_shell: GLOBAL_EVERY_TENANT_PAGE,
  modules: Object.fromEntries(
    [...byModule.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([mod, pages]) => [
        mod,
        {
          page_count: pages.length,
          unique_api_functions: [...new Set(pages.flatMap((p) => p.apis.map((a) => a.fn)))].sort(),
          pages: pages.sort((a, b) => a.route.localeCompare(b.route)),
        },
      ]),
  ),
};

fs.writeFileSync(outPath, JSON.stringify(summary, null, 2));

console.log(`Wrote ${path.relative(root, outPath)}`);
console.log(`Modules: ${Object.keys(summary.modules).length}`);
for (const [mod, data] of Object.entries(summary.modules)) {
  console.log(`  ${mod}: ${data.page_count} pages, ${data.unique_api_functions.length} API fns`);
}
