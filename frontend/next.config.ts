import type { NextConfig } from "next";
import os from "node:os";
import path from "node:path";
import { fileURLToPath } from "node:url";
import bundleAnalyzer from "@next/bundle-analyzer";

const frontendRoot = path.dirname(fileURLToPath(import.meta.url));

const lanIp = process.env.TOWEROS_LAN_IP?.trim();
const lanWebPort = process.env.TOWEROS_LAN_WEB_PORT?.trim() || "3002";
const lanDevOrigins =
  lanIp && /^\d{1,3}(\.\d{1,3}){3}$/.test(lanIp)
    ? [lanIp, `${lanIp}:${lanWebPort}`]
    : [];

function getLocalIpv4Origins(): string[] {
  const nets = os.networkInterfaces();
  const ips = new Set<string>();

  for (const entries of Object.values(nets)) {
    for (const entry of entries ?? []) {
      if (entry.family !== "IPv4") continue;
      if (entry.internal) continue;
      if (!entry.address) continue;
      ips.add(entry.address);
    }
  }

  // Include both common dev ports so HMR doesn't get blocked when switching modes.
  const ports = new Set([lanWebPort, "80", "3002"]);
  const origins: string[] = [];
  for (const ip of ips) {
    origins.push(ip);
    for (const port of ports) origins.push(`${ip}:${port}`);
  }
  return origins;
}

const nextConfig: NextConfig = {
  // Local Docker prod builds (`TOWEROS_WEB_MODE=prod`) skip tsc until frontend TS debt is cleared.
  // CI / AWS production images must not set TOWEROS_DOCKER — `next build` enforces types there.
  typescript: {
    ignoreBuildErrors: process.env.TOWEROS_DOCKER === "1",
  },
  async redirects() {
    return [
      {
        source: "/e-approval/forms/new",
        destination: "/e-approval/forms/create",
        permanent: false,
      },
      // Legacy package links emailed as /api/v1/... on the UI host (Next 404). Forward to the public download page.
      {
        source: "/api/v1/public/e-approval/package-downloads/:token",
        destination: "/public/e-approval/package-downloads/:token",
        permanent: false,
      },
    ];
  },
  // Dev-only: Next.js blocks cross-origin /_next requests unless the page origin is allowlisted.
  // `*.localhost` matches only one label (atc.localhost). Tenant env hosts are two labels deep
  // (test.atc.localhost, app.acme.localhost) and need `*.*.localhost`.
  allowedDevOrigins: [
    "*.localhost",
    "*.*.localhost",
    "localhost",
    "127.0.0.1",
    // LAN colleagues may open IP:port or tenant hosts mapped in hosts.
    ...getLocalIpv4Origins(),
    ...lanDevOrigins,
  ],
  // Monorepo root has its own package-lock.json; keep Turbopack scoped to this app so
  // CSS @import paths and module resolution match `frontend/` (see Next.js turbopack.root).
  turbopack: {
    root: frontendRoot,
  },
  experimental: {
    // Tree-shake large barrel imports so pages only bundle the icons/charts they use.
    optimizePackageImports: ["lucide-react", "recharts"],
  },
};

// Enable with `ANALYZE=1 npm run build` to emit per-route bundle treemaps under .next/analyze.
const withBundleAnalyzer = bundleAnalyzer({
  enabled: process.env.ANALYZE === "1",
});

export default withBundleAnalyzer(nextConfig);
