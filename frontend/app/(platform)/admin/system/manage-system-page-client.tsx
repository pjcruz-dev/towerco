"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Tabs, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import { resolveBrandingAssetUrl } from "@/lib/api/modules/branding-api";
import {
  fetchSystemConfig,
  updateSystemConfig,
  uploadSystemBrandingAsset,
  type SystemConfigPayload,
  type SystemConfigResponse,
} from "@/lib/api/modules/system-config-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";

type TabId =
  | "brand"
  | "theme"
  | "localization"
  | "support"
  | "security"
  | "integrations";

const TABS: Array<{ id: TabId; label: string }> = [
  { id: "brand", label: "Brand & Logo" },
  { id: "theme", label: "Visual Theme" },
  { id: "localization", label: "Localization & AI" },
  { id: "support", label: "Support & Contact" },
  { id: "security", label: "Security & Login" },
  { id: "integrations", label: "API & Integrations" },
];

const THEME_PRESETS: Array<{
  id: string;
  label: string;
  sidebar_dark: string;
  accent: string;
  base_light: string;
}> = [
  { id: "classic", label: "Classic Dark & Blue", sidebar_dark: "#161e2e", accent: "#2563EB", base_light: "#F8FAFC" },
  { id: "slate", label: "Slate & Royal Blue", sidebar_dark: "#0F172A", accent: "#3B82F6", base_light: "#F1F5F9" },
  { id: "emerald", label: "Emerald Modern", sidebar_dark: "#064E3B", accent: "#059669", base_light: "#ECFDF5" },
  { id: "amber", label: "Dark & Amber", sidebar_dark: "#161e2e", accent: "#F87709", base_light: "#FCFCFC" },
];

export function ManageSystemPageClient() {
  const [tab, setTab] = useState<TabId>("brand");
  const [data, setData] = useState<SystemConfigResponse | null>(null);
  const [draft, setDraft] = useState<SystemConfigPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [settingsSearch, setSettingsSearch] = useState("");

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const payload = await fetchSystemConfig();
      setData(payload);
      setDraft(structuredClone(payload.config));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const logoUrl = useMemo(
    () => resolveBrandingAssetUrl(data?.branding.logo_url) ?? null,
    [data?.branding.logo_url],
  );
  const faviconUrl = useMemo(
    () => resolveBrandingAssetUrl(data?.branding.favicon_url) ?? null,
    [data?.branding.favicon_url],
  );

  async function onSave() {
    if (!draft) return;
    setSaving(true);
    setError(null);
    try {
      const saved = await updateSystemConfig(draft);
      setData(saved);
      setDraft(structuredClone(saved.config));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function onUpload(asset: "logo" | "favicon", file: File | null) {
    if (!file) return;
    setSaving(true);
    setError(null);
    try {
      const saved = await uploadSystemBrandingAsset(asset, file);
      setData(saved);
      setDraft(structuredClone(saved.config));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  function patchBrand<K extends keyof SystemConfigPayload["brand"]>(
    key: K,
    value: SystemConfigPayload["brand"][K],
  ) {
    setDraft((prev) => (prev ? { ...prev, brand: { ...prev.brand, [key]: value } } : prev));
  }

  function patchTheme<K extends keyof SystemConfigPayload["theme"]>(
    key: K,
    value: SystemConfigPayload["theme"][K],
  ) {
    setDraft((prev) => (prev ? { ...prev, theme: { ...prev.theme, [key]: value } } : prev));
  }

  function patchLocalization<K extends keyof SystemConfigPayload["localization"]>(
    key: K,
    value: SystemConfigPayload["localization"][K],
  ) {
    setDraft((prev) =>
      prev ? { ...prev, localization: { ...prev.localization, [key]: value } } : prev,
    );
  }

  function patchSupport<K extends keyof SystemConfigPayload["support"]>(
    key: K,
    value: SystemConfigPayload["support"][K],
  ) {
    setDraft((prev) => (prev ? { ...prev, support: { ...prev.support, [key]: value } } : prev));
  }

  function patchSecurity<K extends keyof SystemConfigPayload["security"]>(
    key: K,
    value: SystemConfigPayload["security"][K],
  ) {
    setDraft((prev) => (prev ? { ...prev, security: { ...prev.security, [key]: value } } : prev));
  }

  const filteredTabs = useMemo(() => {
    const q = settingsSearch.trim().toLowerCase();
    if (!q) return TABS;
    return TABS.filter((t) => t.label.toLowerCase().includes(q));
  }, [settingsSearch]);

  return (
    <PermissionGate requiredPermissions={[permissions.systemManage]}>
      <div className={adminPageShellClass}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="System Configuration"
          description="Manage branding, logo styling, visual themes, localization, security, and system integrations."
          actions={
            <>
              <Input
                value={settingsSearch}
                onChange={(e) => setSettingsSearch(e.target.value)}
                placeholder="Search settings…"
                className="h-9 w-44"
                autoComplete="off"
              />
              <Button type="button" size="sm" disabled={saving || !draft} onClick={() => void onSave()}>
                Save Changes
              </Button>
            </>
          }
        />

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        <Tabs value={tab} onValueChange={(value) => setTab(value as TabId)}>
          <TabsList variant="line" className="w-full justify-start overflow-x-auto">
            {filteredTabs.map((item) => (
              <TabsTrigger key={item.id} value={item.id} className="px-3">
                {item.label}
              </TabsTrigger>
            ))}
          </TabsList>
        </Tabs>

        {loading || !draft ? (
          <p className="py-12 text-center text-sm text-muted-foreground">Loading system settings…</p>
        ) : (
          <div className="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6">
            {tab === "brand" ? (
              <div className="grid gap-8 lg:grid-cols-2">
                <div className="space-y-4">
                  <h2 className="text-base font-medium text-foreground">Brand Identity</h2>
                  <Field label="System / Application Name" hint="Appears in browser titles.">
                    <Input
                      value={draft.brand.application_name}
                      onChange={(e) => patchBrand("application_name", e.target.value)}
                      placeholder="e.g. Alliance Towers ERP"
                    />
                  </Field>
                  <Field
                    label="Company Name"
                    hint="Used for printables via {{system.company_name}}."
                  >
                    <Input
                      value={draft.brand.company_name}
                      onChange={(e) => patchBrand("company_name", e.target.value)}
                      placeholder="Alliance Towers Corporation"
                    />
                  </Field>
                  <div className="rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                    System Name and Company Name are separate — keep Company Name for letterheads and
                    reports.
                  </div>
                  <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={draft.brand.show_company_beside_logo}
                      onCheckedChange={(v) => patchBrand("show_company_beside_logo", Boolean(v))}
                    />
                    Show Company Name Beside Logo?
                  </label>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <UploadCard
                      title="Company Logo Image"
                      previewUrl={logoUrl}
                      disabled={saving}
                      onFile={(f) => void onUpload("logo", f)}
                    />
                    <UploadCard
                      title="Favicon Icon"
                      previewUrl={faviconUrl}
                      disabled={saving}
                      onFile={(f) => void onUpload("favicon", f)}
                    />
                  </div>
                </div>
                <div className="space-y-4">
                  <h2 className="text-base font-medium text-foreground">Logo & Brand Text</h2>
                  <Field label="Logo Background">
                    <div className="flex items-center gap-2">
                      <input
                        type="color"
                        value={draft.brand.logo_background}
                        onChange={(e) => patchBrand("logo_background", e.target.value)}
                        className="size-9 cursor-pointer rounded border border-border"
                      />
                      <Input
                        value={draft.brand.logo_background}
                        onChange={(e) => patchBrand("logo_background", e.target.value)}
                      />
                    </div>
                  </Field>
                  <Field label="Application Name Color">
                    <select
                      className="flex h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                      value={draft.brand.application_name_color}
                      onChange={(e) => patchBrand("application_name_color", e.target.value)}
                    >
                      <option value="auto">AUTO (THEME)</option>
                      <option value="white">White</option>
                      <option value="near_black">Near Black</option>
                      <option value="theme_accent">Theme Accent</option>
                      <option value="theme_dark">Theme Dark</option>
                    </select>
                  </Field>
                  <div
                    className="rounded-lg border border-border p-3"
                    style={{ background: draft.brand.logo_background }}
                  >
                    <p className="mb-2 text-[11px] font-medium text-muted-foreground">
                      Top Bar Simulation
                    </p>
                    <div className="flex items-center gap-2">
                      {logoUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={logoUrl} alt="" className="h-8 w-auto object-contain" />
                      ) : (
                        <div className="flex size-8 items-center justify-center rounded bg-sky-600 text-xs font-medium text-white">
                          AT
                        </div>
                      )}
                      {(draft.brand.show_company_beside_logo || draft.brand.application_name) && (
                        <span className="text-sm font-medium text-slate-900">
                          {draft.brand.application_name || draft.brand.company_name || "Application"}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ) : null}

            {tab === "theme" ? (
              <div className="space-y-6">
                <h2 className="text-base font-medium text-foreground">Live Visual Theme Customizer</h2>
                <div className="grid gap-4 sm:grid-cols-3">
                  <ColorField
                    label="Dark / Sidebar Color"
                    value={draft.theme.sidebar_dark}
                    onChange={(v) => patchTheme("sidebar_dark", v)}
                  />
                  <ColorField
                    label="Accent / Brand Color"
                    value={draft.theme.accent}
                    onChange={(v) => patchTheme("accent", v)}
                  />
                  <ColorField
                    label="Background / Light Base"
                    value={draft.theme.base_light}
                    onChange={(v) => patchTheme("base_light", v)}
                  />
                </div>
                <div>
                  <p className="mb-2 text-xs font-medium text-muted-foreground">Theme palettes</p>
                  <div className="flex flex-wrap gap-2">
                    {THEME_PRESETS.map((preset) => (
                      <Button
                        key={preset.id}
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() =>
                          setDraft((prev) =>
                            prev
                              ? {
                                  ...prev,
                                  theme: {
                                    ...prev.theme,
                                    sidebar_dark: preset.sidebar_dark,
                                    accent: preset.accent,
                                    base_light: preset.base_light,
                                    preset: preset.id,
                                  },
                                }
                              : prev,
                          )
                        }
                      >
                        {preset.label}
                      </Button>
                    ))}
                  </div>
                </div>
                <div className="space-y-3">
                  <h3 className="text-sm font-medium text-foreground">Navigation Layout</h3>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <LayoutCard
                      active={draft.theme.shell_layout === "sidebar"}
                      title="Sidebar (Left Rail)"
                      description="Classic vertical menu with collapsible sections."
                      onClick={() => patchTheme("shell_layout", "sidebar")}
                    />
                    <LayoutCard
                      active={draft.theme.shell_layout === "navbar"}
                      title="Navbar (Top Bar)"
                      description="Horizontal menu bar with flyout submenus."
                      onClick={() => patchTheme("shell_layout", "navbar")}
                    />
                  </div>
                  <label className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={draft.theme.lock_layout}
                      onCheckedChange={(v) => patchTheme("lock_layout", Boolean(v))}
                    />
                    Lock this layout for everyone
                  </label>
                </div>
                <p className="text-xs text-muted-foreground">
                  Theme colors sync into tenant branding tokens on save. Refresh the workspace to see
                  chrome updates.
                </p>
              </div>
            ) : null}

            {tab === "localization" ? (
              <div className="space-y-4">
                <h2 className="text-base font-medium text-foreground">
                  Localization & AI Business Context
                </h2>
                <Field label="AI Business Context & Description">
                  <Textarea
                    rows={4}
                    value={draft.localization.ai_business_context}
                    onChange={(e) => patchLocalization("ai_business_context", e.target.value)}
                    placeholder="Describe the business model and industry for AI assistants…"
                  />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Country">
                    <Input
                      value={draft.localization.country}
                      onChange={(e) => patchLocalization("country", e.target.value)}
                    />
                  </Field>
                  <Field label="Office Location / City">
                    <Input
                      value={draft.localization.office_city}
                      onChange={(e) => patchLocalization("office_city", e.target.value)}
                    />
                  </Field>
                  <Field label="Currency Symbol">
                    <Input
                      value={draft.localization.currency_symbol}
                      onChange={(e) => patchLocalization("currency_symbol", e.target.value)}
                    />
                  </Field>
                  <Field label="Currency Code">
                    <Input
                      value={draft.localization.currency_code}
                      onChange={(e) => patchLocalization("currency_code", e.target.value)}
                    />
                  </Field>
                  <Field label="Timezone">
                    <Input
                      value={draft.localization.timezone}
                      onChange={(e) => patchLocalization("timezone", e.target.value)}
                      placeholder="Asia/Manila"
                    />
                  </Field>
                  <Field label="Language / Locale">
                    <Input
                      value={draft.localization.locale}
                      onChange={(e) => patchLocalization("locale", e.target.value)}
                      placeholder="en-PH"
                    />
                  </Field>
                  <Field label="Date Format">
                    <Input
                      value={draft.localization.date_format}
                      onChange={(e) => patchLocalization("date_format", e.target.value)}
                    />
                  </Field>
                  <Field label="Locations Entity Table">
                    <Input
                      value={draft.localization.locations_entity}
                      onChange={(e) => patchLocalization("locations_entity", e.target.value)}
                    />
                  </Field>
                </div>
              </div>
            ) : null}

            {tab === "support" ? (
              <div className="space-y-4">
                <h2 className="text-base font-medium text-foreground">
                  Support & Contact: Public contact channels & footer copyright
                </h2>
                <div className="grid gap-4 sm:grid-cols-2">
                  <Field label="Support Email">
                    <Input
                      value={draft.support.support_email}
                      onChange={(e) => patchSupport("support_email", e.target.value)}
                    />
                  </Field>
                  <Field label="Contact Phone">
                    <Input
                      value={draft.support.contact_phone}
                      onChange={(e) => patchSupport("contact_phone", e.target.value)}
                    />
                  </Field>
                </div>
                <Field label="Office Address">
                  <Textarea
                    rows={3}
                    value={draft.support.office_address}
                    onChange={(e) => patchSupport("office_address", e.target.value)}
                  />
                </Field>
                <Field label="Website URL">
                  <Input
                    value={draft.support.website_url}
                    onChange={(e) => patchSupport("website_url", e.target.value)}
                    placeholder="https://www.example.com"
                  />
                </Field>
                <Field label="Copyright Footer Text">
                  <Input
                    value={draft.support.copyright_footer}
                    onChange={(e) => patchSupport("copyright_footer", e.target.value)}
                  />
                </Field>
              </div>
            ) : null}

            {tab === "security" ? (
              <div className="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
                <div className="space-y-4">
                  <h2 className="text-base font-medium text-foreground">Login Screen & Security</h2>
                  <Field label="Login Screen Background Image URL">
                    <Input
                      value={draft.security.login_background_url ?? ""}
                      onChange={(e) =>
                        patchSecurity("login_background_url", e.target.value.trim() || null)
                      }
                      placeholder="https://…"
                    />
                  </Field>
                  <label className="flex items-start gap-2 text-sm">
                    <Checkbox
                      checked={draft.security.gps_tracking_enabled}
                      onCheckedChange={(v) => patchSecurity("gps_tracking_enabled", Boolean(v))}
                      className="mt-0.5"
                    />
                    <span>
                      <span className="font-medium text-foreground">
                        Enable Continuous Real-Time Location Tracking
                      </span>
                      <span className="mt-0.5 block text-xs text-muted-foreground">
                        Records GPS coordinates for active field personnel (policy flag — enforce in
                        mobile clients).
                      </span>
                    </span>
                  </label>
                  <div className="rounded-lg border border-border bg-muted/30 px-4 py-3 text-sm">
                    <p className="font-medium text-foreground">Sign-in & MFA</p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Enforce 2FA / passkeys / SSO from the dedicated security settings page.
                    </p>
                    <Link
                      href="/admin/settings"
                      className="mt-3 inline-flex h-8 items-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-muted"
                    >
                      Open Sign-in &amp; security
                    </Link>
                  </div>
                </div>
                <div className="rounded-xl border border-border bg-muted/20 p-4">
                  <p className="text-sm font-medium text-foreground">Security audit summary</p>
                  <ul className="mt-3 space-y-2 text-xs text-muted-foreground">
                    <li>
                      GPS tracking:{" "}
                      <span className="font-medium text-foreground">
                        {draft.security.gps_tracking_enabled ? "Active" : "Inactive"}
                      </span>
                    </li>
                    <li>
                      Login background:{" "}
                      <span className="font-medium text-foreground">
                        {draft.security.login_background_url ? "Configured" : "Default"}
                      </span>
                    </li>
                    <li>MFA / SSO: managed under Sign-in &amp; security</li>
                  </ul>
                </div>
              </div>
            ) : null}

            {tab === "integrations" ? (
              <div className="grid gap-4 lg:grid-cols-2">
                <div className="rounded-xl border border-border p-4">
                  <h2 className="text-base font-medium text-foreground">Maps API</h2>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Provider: <span className="font-medium text-foreground">Mapbox</span> (platform
                    configured). Usage costing / keys are not editable per tenant in this release.
                  </p>
                  <ul className="mt-3 space-y-1 text-xs text-muted-foreground">
                    <li>Geocoding — platform env</li>
                    <li>Places / Directions — platform env</li>
                  </ul>
                </div>
                <div className="rounded-xl border border-border p-4">
                  <div className="flex items-center justify-between gap-2">
                    <h2 className="text-base font-medium text-foreground">REST &amp; MCP</h2>
                    <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-800 dark:text-amber-400">
                      See Manage REST API
                    </span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Integration API keys and developer docs live in Manage REST API. MCP remote
                    server install is not wired in TowerOS yet.
                  </p>
                  <Link
                    href="/admin/api-keys"
                    className="mt-3 inline-flex h-8 items-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-muted"
                  >
                    Open API Control Center
                  </Link>
                </div>
              </div>
            ) : null}
          </div>
        )}
      </div>
    </PermissionGate>
  );
}

function Field({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label className="text-xs font-medium">{label}</Label>
      {children}
      {hint ? <p className="text-[11px] text-muted-foreground">{hint}</p> : null}
    </div>
  );
}

function ColorField({
  label,
  value,
  onChange,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
}) {
  return (
    <Field label={label}>
      <div className="flex items-center gap-2">
        <input
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="size-9 cursor-pointer rounded border border-border"
        />
        <Input value={value} onChange={(e) => onChange(e.target.value)} />
      </div>
    </Field>
  );
}

function UploadCard({
  title,
  previewUrl,
  disabled,
  onFile,
}: {
  title: string;
  previewUrl: string | null;
  disabled?: boolean;
  onFile: (file: File | null) => void;
}) {
  return (
    <div className="rounded-xl border border-dashed border-border p-3">
      <p className="text-xs font-medium text-foreground">{title}</p>
      <div className="mt-2 flex h-20 items-center justify-center rounded-lg bg-muted/40">
        {previewUrl ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img src={previewUrl} alt="" className="max-h-16 max-w-full object-contain" />
        ) : (
          <span className="text-xs text-muted-foreground">No image</span>
        )}
      </div>
      <Input
        type="file"
        accept="image/png,image/jpeg,image/gif,image/webp,image/x-icon,.ico"
        className="mt-2 text-xs"
        disabled={disabled}
        onChange={(e) => onFile(e.target.files?.[0] ?? null)}
      />
    </div>
  );
}

function LayoutCard({
  active,
  title,
  description,
  onClick,
}: {
  active: boolean;
  title: string;
  description: string;
  onClick: () => void;
}) {
  return (
    <Button
      type="button"
      variant="outline"
      onClick={onClick}
      className={cn(
        "h-auto w-full flex-col items-start gap-1 whitespace-normal rounded-xl px-4 py-3 text-left shadow-none",
        active
          ? "border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/40"
          : "hover:border-sky-300/60",
      )}
    >
      <span className="text-sm font-medium text-foreground">{title}</span>
      <span className="text-xs font-normal text-muted-foreground">{description}</span>
    </Button>
  );
}
