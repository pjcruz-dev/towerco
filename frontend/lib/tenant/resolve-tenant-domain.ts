const STORAGE_KEY = "toweros_dev_tenant_domain";

export function parseCentralHostnames(): string[] {
  const raw = process.env.NEXT_PUBLIC_CENTRAL_DOMAINS?.trim();
  if (raw) {
    return raw
      .split(",")
      .map((host) => host.trim().toLowerCase())
      .filter(Boolean);
  }
  return ["localhost", "127.0.0.1"];
}

/**
 * Explicit App Menu launcher hosts (comma-separated), in addition to `appmenu.*`.
 * Example: NEXT_PUBLIC_APP_MENU_HOSTS=apps.alliancetowers.com,launcher.example.com
 */
export function parseAppMenuLauncherHostnames(): string[] {
  const raw = process.env.NEXT_PUBLIC_APP_MENU_HOSTS?.trim();
  if (!raw) {
    return [];
  }
  return raw
    .split(",")
    .map((host) => host.trim().toLowerCase())
    .filter(Boolean);
}

/**
 * Public App Menu landing hosts (e.g. appmenu.alliancetowers.com).
 * Not a tenant workspace and not the platform console — root redirects to /appmenu.
 */
export function isAppMenuLauncherHostname(hostname: string): boolean {
  const host = hostname.trim().toLowerCase();
  if (host === "") {
    return false;
  }
  if (host === "appmenu" || host.startsWith("appmenu.")) {
    return true;
  }
  return parseAppMenuLauncherHostnames().includes(host);
}

export function isCentralHostname(hostname: string): boolean {
  const host = hostname.trim().toLowerCase();
  if (host === "") {
    return true;
  }
  // Public App Menu launcher is not the platform console.
  if (isAppMenuLauncherHostname(host)) {
    return false;
  }
  // Tenant dev hosts: atc.localhost, staging.quantum.localhost, etc. (never the platform host).
  if (host.endsWith(".localhost")) {
    return false;
  }
  return parseCentralHostnames().includes(host);
}

export function tenantDomainFromBrowserHostname(hostname: string): string | null {
  const host = hostname.trim().toLowerCase();
  if (!host || isCentralHostname(host) || isAppMenuLauncherHostname(host)) {
    return null;
  }
  return host;
}

export function rememberDevTenantDomain(domain: string): void {
  if (typeof window === "undefined") {
    return;
  }
  const normalized = domain.trim().toLowerCase();
  if (!normalized) {
    return;
  }
  window.sessionStorage.setItem(STORAGE_KEY, normalized);
}

export function readDevTenantDomain(): string | null {
  if (typeof window === "undefined") {
    return null;
  }
  const value = window.sessionStorage.getItem(STORAGE_KEY)?.trim().toLowerCase();
  return value || null;
}

export function clearDevTenantDomain(): void {
  if (typeof window === "undefined") {
    return;
  }
  window.sessionStorage.removeItem(STORAGE_KEY);
}

/**
 * Hostname used to resolve tenant context for API calls.
 * Prefer the browser host; on central dev hosts fall back to sessionStorage / query param.
 */
export function resolveTenantDomainForApi(): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  const fromBrowser = tenantDomainFromBrowserHostname(window.location.hostname);
  if (fromBrowser !== null) {
    return fromBrowser;
  }

  return readDevTenantDomain();
}

export function resolveDevAppPort(fallback = ""): string {
  const fromEnv = process.env.NEXT_PUBLIC_DEV_APP_PORT?.trim();
  if (fromEnv) {
    return fromEnv;
  }

  if (typeof window !== "undefined" && window.location.port) {
    return window.location.port;
  }

  return fallback;
}

function portSuffix(port: string): string {
  if (!port || port === "80" || port === "443") {
    return "";
  }

  return `:${port}`;
}

/** Login URL for hostname preview (platform create-tenant sidebar). */
export function previewTenantLoginUrl(hostname: string, port?: string): string {
  const host = hostname.trim().toLowerCase();
  if (!host) {
    return "/login";
  }

  const portPart = portSuffix(port ?? resolveDevAppPort());

  if (host.endsWith(".localhost") || host === "localhost") {
    return `http://${host}${portPart}/login`;
  }

  return `https://${host}/login`;
}

export function tenantLoginUrl(domain: string, port?: string): string {
  const host = domain.trim().toLowerCase();
  if (!host) {
    return "/login";
  }

  const resolvedPort = port ?? resolveDevAppPort();

  const tpl = process.env.NEXT_PUBLIC_TENANT_LOGIN_URL_TEMPLATE?.trim();
  if (tpl) {
    return tpl
      .replaceAll("{host}", host)
      .replaceAll("{domain}", host)
      .replaceAll("{port}", resolvedPort);
  }

  const portPart = portSuffix(resolvedPort);

  if (typeof window !== "undefined") {
    const { protocol } = window.location;

    return `${protocol}//${host}${portPart}/login`;
  }

  return `http://${host}${portPart}/login`;
}
