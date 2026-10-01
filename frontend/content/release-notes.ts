export type ProductReleaseSection = {
  heading: string;
  body: string;
};

export type ProductRelease = {
  version: string;
  title: string;
  summary: string;
  sections: ProductReleaseSection[];
};

export const PRODUCT_RELEASE_NOTES_KEY = "product-release.current";
export const PRODUCT_RELEASE_SEEN_KEY = "product-release.seen";

/** Shipped with the app. A published tenant note replaces this until the version changes. */
export const bundledRelease: ProductRelease = {
  version: "v1.0.5",
  title: "Profile, team actions, and release notes",
  summary:
    "Account, security, and notifications sit on one profile. The bell pulses when something is unread. This note shows once for each version.",
  sections: [
    {
      heading: "My profile",
      body: "Name, email, and your records stay on Account. Security opens sessions, authenticator, and passkeys. Notifications is the in-app inbox.",
    },
    {
      heading: "People",
      body: "Each person on Team & Access has an actions menu: view details, impersonate, edit, deactivate, or delete.",
    },
    {
      heading: "Notifications",
      body: "A pulsing dot on the bell means you have unread items. Open the bell to read them.",
    },
    {
      heading: "What's new",
      body: "Tenant admins write the full note on My profile → Notifications. Everyone else sees it once, the first time they open the app after that version.",
    },
  ],
};

export function parseProductRelease(value: unknown): ProductRelease | null {
  if (!value || typeof value !== "object") {
    return null;
  }

  const row = value as Record<string, unknown>;
  const version = typeof row.version === "string" ? row.version.trim() : "";
  const title = typeof row.title === "string" ? row.title.trim() : "";
  if (!version || !title) {
    return null;
  }

  const summary = typeof row.summary === "string" ? row.summary.trim() : "";
  const sectionsRaw = Array.isArray(row.sections) ? row.sections : [];
  const sections = sectionsRaw.flatMap((item) => {
    if (!item || typeof item !== "object") {
      return [];
    }
    const section = item as Record<string, unknown>;
    const heading = typeof section.heading === "string" ? section.heading.trim() : "";
    const body = typeof section.body === "string" ? section.body.trim() : "";
    if (!heading && !body) {
      return [];
    }
    return [{ heading, body }];
  });

  return { version, title, summary, sections };
}

/** Accepts the note itself, or `{ "release": { ... } }` from a shared preference payload. */
export function parseProductReleaseImport(raw: string): ProductRelease | null {
  const text = raw.trim();
  if (!text) {
    return null;
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(text) as unknown;
  } catch {
    return null;
  }

  if (parsed && typeof parsed === "object" && "release" in parsed) {
    parsed = (parsed as { release: unknown }).release;
  }

  return parseProductRelease(parsed);
}
