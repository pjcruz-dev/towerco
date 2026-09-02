"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { Check, Copy, KeyRound, Plus, Search, Trash2 } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { getErrorMessage } from "@/lib/api/error";
import { resolveApiBaseUrl } from "@/lib/api/client";
import {
  createIntegrationApiKey,
  fetchIntegrationApiDocsMeta,
  fetchIntegrationApiKeys,
  revokeIntegrationApiKey,
  type IntegrationApiDocsMeta,
  type IntegrationApiKeyRow,
} from "@/lib/api/modules/integration-api-keys-api";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";

const QUICK_NAV: Array<{ id: string; label: string }> = [
  { id: "docs-endpoint", label: "Base URL & Auth" },
  { id: "docs-list", label: "List Records (Collection)" },
  { id: "docs-get", label: "Get Single Record" },
  { id: "docs-create", label: "Create Record" },
  { id: "docs-update", label: "Update / Delete" },
  { id: "docs-user-auth", label: "User Auth" },
  { id: "docs-schema", label: "Schema Discovery" },
  { id: "docs-field-types", label: "Field Types" },
  { id: "docs-workflow", label: "Workflow Execution" },
  { id: "docs-printables", label: "Printable Generation" },
  { id: "docs-location", label: "Location Field Type" },
];

function formatWhen(iso: string | null): string {
  if (!iso) return "—";
  try {
    return new Date(iso).toLocaleString();
  } catch {
    return iso;
  }
}

function statusLabel(status: string): { label: string; className: string } {
  if (status === "operational") {
    return {
      label: "Operational",
      className: "bg-emerald-500/15 text-emerald-700 dark:text-emerald-400",
    };
  }
  if (status === "expired") {
    return {
      label: "Expired",
      className: "bg-amber-500/15 text-amber-800 dark:text-amber-400",
    };
  }
  return {
    label: "Owner inactive",
    className: "bg-red-500/15 text-red-700 dark:text-red-400",
  };
}

function CopyBlock({ text, label }: { text: string; label?: string }) {
  const [copied, setCopied] = useState(false);

  return (
    <div className="group relative rounded-lg border border-border bg-slate-950 text-slate-100">
      {label ? (
        <div className="flex items-center justify-between border-b border-slate-800 px-3 py-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">
          <span>{label}</span>
          <button
            type="button"
            className="inline-flex items-center gap-1 text-slate-300 hover:text-white"
            onClick={async () => {
              await navigator.clipboard.writeText(text);
              setCopied(true);
              window.setTimeout(() => setCopied(false), 1500);
            }}
          >
            {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
            {copied ? "Copied" : "Copy"}
          </button>
        </div>
      ) : null}
      <pre className="overflow-x-auto p-3 text-[12px] leading-relaxed whitespace-pre-wrap break-all">
        {text}
      </pre>
      {!label ? (
        <button
          type="button"
          className="absolute top-2 right-2 inline-flex items-center gap-1 rounded-md bg-slate-800 px-2 py-1 text-[11px] text-slate-200 opacity-0 transition group-hover:opacity-100"
          onClick={async () => {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
          }}
        >
          {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
          {copied ? "Copied" : "Copy"}
        </button>
      ) : null}
    </div>
  );
}

function DocSection({
  id,
  title,
  children,
}: {
  id: string;
  title: string;
  children: React.ReactNode;
}) {
  return (
    <section id={id} className="scroll-mt-28 space-y-3 border-b border-border pb-8 last:border-0">
      <h3 className="text-base font-medium text-foreground">{title}</h3>
      {children}
    </section>
  );
}

export function ManageRestApiPageClient() {
  const [rows, setRows] = useState<IntegrationApiKeyRow[]>([]);
  const [total, setTotal] = useState(0);
  const [docsMeta, setDocsMeta] = useState<IntegrationApiDocsMeta | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [newName, setNewName] = useState("");
  const [revealedToken, setRevealedToken] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [schemaSearch, setSchemaSearch] = useState("");
  const [expandedSchema, setExpandedSchema] = useState<string | null>(null);

  const baseUrl = useMemo(() => resolveApiBaseUrl().replace(/\/$/, ""), []);
  const integrationBase = `${baseUrl}/integration`;
  const sampleEntity = docsMeta?.sample_entity_slug || "tower_sites";
  const sampleToken = "YOUR_API_TOKEN";
  const authHeader = `Authorization: Bearer ${sampleToken}`;

  const filteredSchemas = useMemo(() => {
    const schemas = docsMeta?.schemas ?? [];
    const q = schemaSearch.trim().toLowerCase();
    if (!q) return schemas;
    return schemas.filter(
      (s) =>
        s.slug.toLowerCase().includes(q) ||
        s.name.toLowerCase().includes(q) ||
        s.fields.some((f) => f.name.toLowerCase().includes(q) || f.label.toLowerCase().includes(q)),
    );
  }, [docsMeta?.schemas, schemaSearch]);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const [keys, meta] = await Promise.all([
        fetchIntegrationApiKeys(),
        fetchIntegrationApiDocsMeta().catch(() => null),
      ]);
      setRows(keys.rows);
      setTotal(keys.total);
      if (meta) {
        setDocsMeta(meta);
        if (!expandedSchema && meta.sample_entity_slug) {
          setExpandedSchema(meta.sample_entity_slug);
        }
      }
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoading(false);
    }
  }, [expandedSchema]);

  useEffect(() => {
    void load();
    // intentionally once on mount
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function onCreate() {
    const name = newName.trim();
    if (!name) return;
    setBusy(true);
    setError(null);
    try {
      const created = await createIntegrationApiKey(name);
      setRevealedToken(created.plain_text_token);
      setNewName("");
      setCreating(false);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function onRevoke(id: number, name: string) {
    if (!window.confirm(`Revoke API key “${name}”? Integrations using it will fail immediately.`)) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      await revokeIntegrationApiKey(id);
      await load();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <PermissionGate requiredPermissions={[permissions.apiKeysManage]}>
      <div className="mx-auto flex w-full max-w-[1600px] flex-col gap-6">
        <header className="space-y-1">
          <div className="flex items-center gap-2 text-muted-foreground">
            <KeyRound className="size-4" />
            <span className="text-xs font-medium">System Core</span>
          </div>
          <h1 className="text-2xl font-semibold text-foreground">API Control Center</h1>
          <p className="max-w-2xl text-sm text-muted-foreground">
            Manage security tokens and explore integration protocols.
          </p>
        </header>

        {error ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}

        {revealedToken ? (
          <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-4 dark:border-amber-900/40 dark:bg-amber-950/30">
            <p className="text-sm font-medium text-amber-950 dark:text-amber-100">
              Copy this token now — it will not be shown again.
            </p>
            <div className="mt-3">
              <CopyBlock text={revealedToken} label="Plain-text access key" />
            </div>
            <Button type="button" variant="outline" size="sm" className="mt-3" onClick={() => setRevealedToken(null)}>
              I saved the token
            </Button>
          </div>
        ) : null}

        <section className="rounded-xl border border-border bg-card shadow-sm">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3">
            <div>
              <h2 className="text-base font-medium text-foreground">Active Security Tokens</h2>
              <p className="text-xs text-muted-foreground">
                {total} {total === 1 ? "key" : "keys"} total
              </p>
            </div>
            <Button
              type="button"
              size="sm"
              disabled={busy}
              onClick={() => {
                setCreating(true);
                setNewName("");
              }}
            >
              <Plus className="size-4" />
              New key
            </Button>
          </div>

          {creating ? (
            <div className="flex flex-wrap items-end gap-3 border-b border-border bg-muted/30 px-4 py-3">
              <div className="min-w-[220px] flex-1 space-y-1.5">
                <Label htmlFor="api-key-name">Token identity</Label>
                <Input
                  id="api-key-name"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="e.g. Portal integration"
                  autoComplete="off"
                  maxLength={120}
                />
              </div>
              <Button type="button" size="sm" disabled={busy || !newName.trim()} onClick={() => void onCreate()}>
                Create
              </Button>
              <Button type="button" size="sm" variant="ghost" disabled={busy} onClick={() => setCreating(false)}>
                Cancel
              </Button>
            </div>
          ) : null}

          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-left text-[13px]">
              <thead className="sticky top-0 bg-muted/50 text-xs font-medium text-muted-foreground">
                <tr className="border-b border-border">
                  <th className="px-4 py-2.5">Token identity</th>
                  <th className="px-4 py-2.5">Access key</th>
                  <th className="px-4 py-2.5">Lifecycle</th>
                  <th className="px-4 py-2.5">Status</th>
                  <th className="px-4 py-2.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                      Loading keys…
                    </td>
                  </tr>
                ) : rows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-4 py-8 text-center text-muted-foreground">
                      No integration keys yet. Create one to call the integration endpoints.
                    </td>
                  </tr>
                ) : (
                  rows.map((row) => {
                    const tone = statusLabel(row.status);
                    return (
                      <tr key={row.id} className="border-b border-border last:border-0">
                        <td className="px-4 py-3 align-top">
                          <p className="font-medium text-foreground">{row.name}</p>
                          <p className="text-xs text-muted-foreground">ID: #{row.id}</p>
                          <p className="text-xs text-muted-foreground">
                            Owner: {row.created_by_name || row.created_by_email || "—"}
                          </p>
                        </td>
                        <td className="px-4 py-3 align-top font-mono text-xs text-muted-foreground">
                          {row.token_preview}
                        </td>
                        <td className="px-4 py-3 align-top text-muted-foreground">
                          <p>Issued: {formatWhen(row.created_at)}</p>
                          <p>Pulse: {row.last_used_at ? formatWhen(row.last_used_at) : "Never"}</p>
                        </td>
                        <td className="px-4 py-3 align-top">
                          <span
                            className={cn(
                              "inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium",
                              tone.className,
                            )}
                          >
                            {tone.label}
                          </span>
                        </td>
                        <td className="px-4 py-3 align-top text-right">
                          <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            className="text-red-600 hover:text-red-700"
                            disabled={busy}
                            onClick={() => void onRevoke(row.id, row.name)}
                          >
                            <Trash2 className="size-4" />
                            Revoke
                          </Button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </section>

        <section className="rounded-xl border border-border bg-card shadow-sm">
          <div className="border-b border-border px-4 py-3 sm:px-5">
            <h2 className="text-base font-medium text-foreground">Developer Integration Guide</h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Integrate seamlessly with our REST engine. Authenticate using your secure token.
              {docsMeta
                ? ` ${docsMeta.entity_count} entities · ${docsMeta.workflows.length} workflows.`
                : null}
            </p>
          </div>

          <div className="flex flex-col lg:flex-row">
            <aside className="shrink-0 border-b border-border lg:sticky lg:top-16 lg:h-[calc(100vh-5rem)] lg:w-56 lg:overflow-y-auto lg:border-r lg:border-b-0">
              <nav className="p-3">
                <p className="px-2 pb-2 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                  Quick navigation
                </p>
                <ul className="space-y-0.5">
                  {QUICK_NAV.map((item) => (
                    <li key={item.id}>
                      <a
                        href={`#${item.id}`}
                        className="block rounded-md px-2 py-1.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground"
                      >
                        {item.label}
                      </a>
                    </li>
                  ))}
                </ul>
              </nav>
            </aside>

            <div className="min-w-0 flex-1 space-y-8 p-4 sm:p-6">
              <DocSection id="docs-endpoint" title="Global Endpoint Router">
                <CopyBlock text={integrationBase} label="Base URL" />
                <p className="text-xs text-muted-foreground">
                  TowerOS uses versioned REST paths under <code className="rounded bg-muted px-1">/integration</code>,
                  not a single <code className="rounded bg-muted px-1">api.php?action=</code> router.
                </p>
                <div className="grid gap-3 pt-2 md:grid-cols-2">
                  <div className="space-y-2">
                    <p className="text-xs font-medium text-foreground">0. Header method (recommended)</p>
                    <CopyBlock text={authHeader} />
                    <CopyBlock text={`X-API-Key: ${sampleToken}`} />
                  </div>
                  <div className="space-y-2">
                    <p className="text-xs font-medium text-foreground">Query method</p>
                    <CopyBlock text={`?api_key=${sampleToken}`} />
                  </div>
                </div>
              </DocSection>

              <DocSection id="docs-list" title="1. Resource Collection (LIST)">
                <p className="text-sm text-muted-foreground">
                  Query multiple records for a Dynamic Entity with advanced filtering. Parameters are
                  whitelisted against the entity field schema.
                </p>
                <CopyBlock
                  text={`GET ${integrationBase}/dynamic-entities/entities/${sampleEntity}/records?per_page=10\n${authHeader}`}
                  label="GET endpoint"
                />
                <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                  <p className="font-medium">Advanced filtering &amp; query whitelist</p>
                  <ul className="mt-2 list-disc space-y-1 pl-5 text-xs">
                    <li>
                      <strong>GTE:</strong> <code>created_at[gte]=2026-01-01</code> or{" "}
                      <code>created_at_from=…</code>
                    </li>
                    <li>
                      <strong>LTE:</strong> <code>created_at[lte]=2026-12-31</code> or{" "}
                      <code>created_at_to=…</code>
                    </li>
                    <li>
                      <strong>IN:</strong> <code>status[in]=Operational,WIP</code> or{" "}
                      <code>status_in=…</code>
                    </li>
                    <li>
                      <strong>NOT:</strong> <code>status[not]=Draft</code> or <code>status_not=…</code>
                    </li>
                  </ul>
                </div>
                <CopyBlock
                  text={`GET ${integrationBase}/dynamic-entities/entities/${sampleEntity}/records?status[in]=Operational,WIP&created_at[gte]=2026-01-01\n${authHeader}`}
                  label="Filtered example"
                />
              </DocSection>

              <DocSection id="docs-get" title="2. Resource Retrieval (GET)">
                <CopyBlock
                  text={`GET ${integrationBase}/dynamic-entities/records/{record_id}\n${authHeader}`}
                  label="GET endpoint"
                />
              </DocSection>

              <DocSection id="docs-create" title="3. Resource Creation (CREATE)">
                <CopyBlock
                  text={`POST ${integrationBase}/dynamic-entities/entities/${sampleEntity}/records\n${authHeader}\nContent-Type: application/json\n\n{\n  "title": "ATC-001",\n  "values": {\n    "site_code": "ATC-001",\n    "status": "Draft"\n  }\n}`}
                  label="POST payload"
                />
              </DocSection>

              <DocSection id="docs-update" title="4. Resource Mutation (UPDATE / DELETE)">
                <CopyBlock
                  text={`PATCH ${integrationBase}/dynamic-entities/records/{record_id}\n${authHeader}\nContent-Type: application/json\n\n{\n  "values": {\n    "status": "Operational"\n  }\n}`}
                  label="PATCH"
                />
                <CopyBlock
                  text={`DELETE ${integrationBase}/dynamic-entities/records/{record_id}\n${authHeader}`}
                  label="DELETE"
                />
              </DocSection>

              <DocSection id="docs-user-auth" title="5. User Authentication">
                <p className="text-sm text-muted-foreground">
                  Human session auth is separate from integration keys (TowerOS does not put portal
                  login on the same machine gateway as Metacoresoft&apos;s <code className="rounded bg-muted px-1">action=login</code>).
                </p>
                <CopyBlock
                  text={`POST ${baseUrl}/auth/login\nContent-Type: application/json\n\n{\n  "email": "user@example.com",\n  "password": "yourPassword"\n}`}
                  label="Session login"
                />
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
                  <p className="font-medium">Attribution</p>
                  <p className="mt-1 text-xs leading-relaxed">
                    Writes via an integration key are attributed to the minting user. Prefer a
                    dedicated service account for multi-user portals. Logout does not revoke keys;
                    use Revoke or deactivate the owner.
                  </p>
                </div>
              </DocSection>

              <DocSection id="docs-schema" title="7. System Schema &amp; Data Map">
                <p className="text-sm text-muted-foreground">
                  Discover all available entities and field definitions dynamically — essential for
                  apps that adapt to your custom structure.
                </p>
                <CopyBlock
                  text={`GET ${integrationBase}/dynamic-entities/entities\n${authHeader}`}
                  label="LIST entities"
                />
                <CopyBlock
                  text={`GET ${integrationBase}/dynamic-entities/entities/${sampleEntity}\n${authHeader}`}
                  label="GET entity schema"
                />

                <div className="space-y-3 rounded-xl border border-border bg-muted/20 p-4">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-medium text-foreground">Interactive Schema Explorer</p>
                    <p className="text-xs text-muted-foreground">
                      {(docsMeta?.schemas.length ?? 0)} active entities
                    </p>
                  </div>
                  <div className="relative">
                    <Search className="pointer-events-none absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                      value={schemaSearch}
                      onChange={(e) => setSchemaSearch(e.target.value)}
                      placeholder="Search entities or fields…"
                      className="pl-9"
                      autoComplete="off"
                    />
                  </div>
                  <div className="grid max-h-[28rem] gap-2 overflow-y-auto sm:grid-cols-2 xl:grid-cols-3">
                    {filteredSchemas.length === 0 ? (
                      <p className="col-span-full py-6 text-center text-xs text-muted-foreground">
                        No schemas match your search.
                      </p>
                    ) : (
                      filteredSchemas.map((schema) => {
                        const open = expandedSchema === schema.slug;
                        return (
                          <button
                            key={schema.slug}
                            type="button"
                            onClick={() => setExpandedSchema(open ? null : schema.slug)}
                            className={cn(
                              "rounded-lg border px-3 py-2.5 text-left transition",
                              open
                                ? "border-sky-400 bg-sky-50 dark:border-sky-700 dark:bg-sky-950/40"
                                : "border-border bg-background hover:border-sky-300/60",
                            )}
                          >
                            <p className="text-sm font-medium text-foreground">{schema.name}</p>
                            <p className="font-mono text-[11px] text-muted-foreground">{schema.slug}</p>
                            <p className="mt-1 text-[11px] text-muted-foreground">
                              {schema.field_count} fields
                              {schema.module_pack ? ` · ${schema.module_pack}` : ""}
                            </p>
                            {open ? (
                              <ul className="mt-2 max-h-40 space-y-0.5 overflow-y-auto border-t border-border/60 pt-2">
                                {schema.fields.map((field) => (
                                  <li
                                    key={field.name}
                                    className="flex items-baseline justify-between gap-2 text-[11px]"
                                  >
                                    <span className="font-mono text-foreground">{field.name}</span>
                                    <span className="shrink-0 text-muted-foreground">{field.type}</span>
                                  </li>
                                ))}
                              </ul>
                            ) : null}
                          </button>
                        );
                      })
                    )}
                  </div>
                </div>
              </DocSection>

              <DocSection id="docs-field-types" title="8. Specialized Field Types">
                <div className="space-y-3 text-sm text-muted-foreground">
                  <div>
                    <p className="font-medium text-foreground">password</p>
                    <p className="text-xs">Hashed on write; masked in LIST/GET. Omit on update to keep existing.</p>
                  </div>
                  <div>
                    <p className="font-medium text-foreground">masked</p>
                    <p className="text-xs">Stored plaintext; returned masked. Reveal is UI-only.</p>
                  </div>
                  <div>
                    <p className="font-medium text-foreground">formula</p>
                    <p className="text-xs">Server-computed on read. Clients should not set these fields.</p>
                  </div>
                </div>
              </DocSection>

              <DocSection id="docs-workflow" title="9. Workflow &amp; Action Execution">
                <CopyBlock
                  text={`POST ${integrationBase}/dynamic-entities/records/{record_id}/workflow-actions/{action_slug}\n${authHeader}`}
                  label="POST execute"
                />
                <p className="text-sm font-medium text-foreground">Active workflows in your database</p>
                {!docsMeta || docsMeta.workflows.length === 0 ? (
                  <p className="text-xs text-muted-foreground">No workflow buttons on active entities yet.</p>
                ) : (
                  <div className="grid gap-3 sm:grid-cols-2">
                    {docsMeta.workflows.map((wf) => (
                      <div
                        key={`${wf.entity_slug}:${wf.action_id}`}
                        className="rounded-xl border border-border bg-background p-3 shadow-sm"
                      >
                        <p className="text-[11px] font-medium text-sky-700 dark:text-sky-400">
                          {wf.entity_name}
                        </p>
                        <p className="mt-0.5 text-sm font-medium text-foreground">{wf.label}</p>
                        <p className="mt-1 text-[11px] text-muted-foreground">
                          Manual / Status Update
                          {wf.from_status || wf.to_status
                            ? ` · ${wf.from_status || "…"} → ${wf.to_status || "…"}`
                            : null}
                        </p>
                        <p className="mt-1 text-[11px]">
                          Action slug:{" "}
                          <code className="rounded bg-red-500/10 px-1 text-red-700 dark:text-red-400">
                            {wf.action_id}
                          </code>
                        </p>
                        <div className="mt-2">
                          <CopyBlock
                            text={`curl -X POST "${integrationBase}/dynamic-entities/records/RECORD_ID/workflow-actions/${wf.action_id}" \\\n  -H "Authorization: Bearer ${sampleToken}"`}
                            label="CURL snippet"
                          />
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </DocSection>

              <DocSection id="docs-printables" title="10. Printable Document Generation">
                <p className="text-sm text-muted-foreground">
                  Printables are configured in Manage Printables and rendered in-product today. A
                  machine <code className="rounded bg-muted px-1">get_printable</code> gateway
                  (PDF/HTML/JSON) is the next parity step.
                </p>
              </DocSection>

              <DocSection id="docs-location" title="12. Location Field Type">
                <p className="text-sm text-muted-foreground">
                  Location fields store coordinates and an optional address. Prefer structured JSON in{" "}
                  <code className="rounded bg-muted px-1">values</code>.
                </p>
                <CopyBlock
                  text={`{\n  "home_location": {\n    "address": "684 Panero St, Quiapo, Manila",\n    "lat": 14.5995,\n    "lng": 120.9842\n  }\n}`}
                  label="Accepted JSON object"
                />
                <div className="rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-950 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-100">
                  Also accepts <code>&quot;14.9134, 120.9542&quot;</code> or a plain address string. Canonical
                  storage is a JSON array of <code>{`{address, lat, lng}`}</code> objects.
                </div>
              </DocSection>
            </div>
          </div>
        </section>
      </div>
    </PermissionGate>
  );
}
