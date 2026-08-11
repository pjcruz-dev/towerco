const raw = process.env.NEXT_PUBLIC_APP_ENV?.trim().toLowerCase();

const LABELS: Record<string, string> = {
  local: "local",
  development: "local",
  dev: "local",
  staging: "staging",
  production: "production",
  prod: "production",
  test: "test",
};

function environmentFromHostname(host: string): string | null {
  const hostname = host.toLowerCase();

  if (hostname === "localhost" || hostname.endsWith(".localhost") || hostname === "127.0.0.1") {
    if (hostname.startsWith("staging.") || hostname.includes(".staging.")) {
      return "staging";
    }
    if (hostname.startsWith("test.") || hostname.includes(".test.")) {
      return "test";
    }
    if (hostname.startsWith("app.") || hostname.includes(".app.")) {
      return "production";
    }
    return "local";
  }

  // LAN / DNS hostnames: staging.atc.toweros.lan, app.atc.toweros.lan
  if (hostname.startsWith("staging.") || hostname.includes(".staging.")) {
    return "staging";
  }
  if (hostname.startsWith("test.") || hostname.includes(".test.")) {
    return "test";
  }
  if (hostname.startsWith("app.") || hostname.includes(".app.")) {
    return "production";
  }

  return null;
}

export function resolveAppEnvironmentLabel(): string {
  if (typeof window !== "undefined") {
    const fromHost = environmentFromHostname(window.location.hostname);
    if (fromHost) {
      return fromHost;
    }
  }

  if (raw && LABELS[raw]) {
    return LABELS[raw];
  }

  return raw || "local";
}

export function resolveAppVersionLabel(): string {
  return process.env.NEXT_PUBLIC_APP_VERSION?.trim() || "v1.0.4";
}

export function isProductionEnvironment(): boolean {
  return resolveAppEnvironmentLabel() === "production";
}

/** Uppercase badge text for sidebar (hidden in production). */
export function resolveEnvironmentBadgeLabel(): string | null {
  const label = resolveAppEnvironmentLabel();
  if (label === "production") {
    return null;
  }

  return label.toUpperCase();
}
