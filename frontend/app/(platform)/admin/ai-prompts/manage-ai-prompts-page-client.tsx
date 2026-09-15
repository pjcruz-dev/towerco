"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import { RotateCcw, Save } from "lucide-react";

import { PermissionGate } from "@/components/layout/permission-gate";
import { WorkspacePageHeader } from "@/components/layout/workspace-page-header";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import {
  getAiPromptModule,
  listAiPromptModules,
  resetAiPromptModule,
  updateAiPromptModule,
  type AiPromptModuleDetail,
  type AiPromptModuleListRow,
} from "@/lib/api/modules/assistant-api";
import { permissions } from "@/lib/rbac/permissions";
import { adminPageShellClass } from "@/lib/ui/page-shell";
import { cn } from "@/lib/utils";

export function ManageAiPromptsPageClient() {
  const [rows, setRows] = useState<AiPromptModuleListRow[]>([]);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [detail, setDetail] = useState<AiPromptModuleDetail | null>(null);
  const [body, setBody] = useState("");
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [enabled, setEnabled] = useState(true);
  const [search, setSearch] = useState("");
  const [loadingList, setLoadingList] = useState(true);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const loadList = useCallback(async () => {
    setLoadingList(true);
    setError(null);
    try {
      const data = await listAiPromptModules();
      setRows(data);
      setSelectedId((prev) => prev ?? (data[0]?.id ?? null));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setLoadingList(false);
    }
  }, []);

  useEffect(() => {
    void loadList();
  }, [loadList]);

  useEffect(() => {
    if (!selectedId) return;
    let cancelled = false;
    async function run() {
      setLoadingDetail(true);
      setError(null);
      setNotice(null);
      try {
        const mod = await getAiPromptModule(selectedId!);
        if (cancelled) return;
        setDetail(mod);
        setBody(mod.body);
        setName(mod.name);
        setDescription(mod.description ?? "");
        setEnabled(mod.is_enabled);
      } catch (err) {
        if (!cancelled) setError(getErrorMessage(err));
      } finally {
        if (!cancelled) setLoadingDetail(false);
      }
    }
    void run();
    return () => {
      cancelled = true;
    };
  }, [selectedId]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return rows;
    return rows.filter(
      (r) =>
        r.name.toLowerCase().includes(q) ||
        r.key.toLowerCase().includes(q) ||
        (r.filename ?? "").toLowerCase().includes(q) ||
        (r.intent_key ?? "").toLowerCase().includes(q) ||
        (r.description ?? "").toLowerCase().includes(q),
    );
  }, [rows, search]);

  async function handleSave() {
    if (!selectedId) return;
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await updateAiPromptModule(selectedId, {
        name,
        description: description.trim() || null,
        body,
        is_enabled: enabled,
      });
      setDetail(updated);
      setBody(updated.body);
      setNotice("Module saved.");
      await loadList();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  async function handleReset() {
    if (!selectedId || !detail) return;
    if (!window.confirm(`Reset “${detail.name}” to the TowerOS catalog default? Unsaved edits will be lost.`)) {
      return;
    }
    setSaving(true);
    setError(null);
    setNotice(null);
    try {
      const updated = await resetAiPromptModule(selectedId);
      setDetail(updated);
      setBody(updated.body);
      setName(updated.name);
      setDescription(updated.description ?? "");
      setEnabled(updated.is_enabled);
      setNotice("Reset to catalog default.");
      await loadList();
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setSaving(false);
    }
  }

  const dirty =
    detail !== null &&
    (body !== detail.body ||
      name !== detail.name ||
      (description || "") !== (detail.description ?? "") ||
      enabled !== detail.is_enabled);

  return (
    <PermissionGate requiredPermissions={[permissions.aiAssistantPromptsManage]}>
      <div className={cn(adminPageShellClass, "h-[calc(100vh-4rem)] gap-4")}>
        <WorkspacePageHeader
          eyebrow="System Core"
          title="AI System Prompts"
          description="Edit modular Ask TowerOS system instructions assembled by intent. Markdown/text only — never executed as code. Uses your configured LLM provider (`AI_ASSISTANT_LLM_PROVIDER`), not Active Security Tokens."
        />

        {error ? (
          <div className="mb-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-300">
            {error}
          </div>
        ) : null}
        {notice ? (
          <div className="mb-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-100">
            {notice}
          </div>
        ) : null}

        <div className="grid min-h-0 flex-1 overflow-hidden rounded-xl border border-border bg-card shadow-sm lg:grid-cols-[300px_1fr]">
          <aside className="flex min-h-0 flex-col border-b border-border lg:border-b-0 lg:border-r">
            <div className="space-y-2 border-b border-border p-3">
              <p className="text-xs font-medium text-muted-foreground">Prompt modules</p>
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search modules…"
                className="h-9"
                autoComplete="off"
              />
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto p-1">
              {loadingList ? (
                <p className="px-3 py-6 text-sm text-muted-foreground">Loading…</p>
              ) : filtered.length === 0 ? (
                <p className="px-3 py-6 text-sm text-muted-foreground">No modules match.</p>
              ) : (
                <ul>
                  {filtered.map((row) => {
                    const active = row.id === selectedId;
                    return (
                      <li key={row.id}>
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={() => {
                            if (dirty && !window.confirm("Discard unsaved changes?")) return;
                            setSelectedId(row.id);
                          }}
                          className={cn(
                            "h-auto w-full flex-col items-start gap-0.5 whitespace-normal rounded-lg px-3 py-2 text-left",
                            active ? "bg-muted" : "",
                            !row.is_enabled && !active ? "opacity-55" : "",
                          )}
                        >
                          <span className="font-medium leading-snug">{row.name}</span>
                          <span className="text-[11px] font-normal text-muted-foreground">
                            {row.key}
                            {row.kind === "router" ? " · reference" : ` · ${row.kind}`}
                            {row.intent_key ? ` · ${row.intent_key}` : ""}
                            {!row.is_enabled ? " · off" : ""}
                          </span>
                        </Button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </aside>

          <section className="flex min-h-0 flex-col">
            {loadingDetail || !detail ? (
              <p className="px-4 py-16 text-center text-sm text-muted-foreground">
                {loadingDetail ? "Loading module…" : "Select a prompt module"}
              </p>
            ) : (
              <>
                <div className="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
                  <div className="min-w-[200px] flex-1 space-y-1.5">
                    <Label>Display name</Label>
                    <Input value={name} onChange={(e) => setName(e.target.value)} />
                  </div>
                  <label className="flex items-center gap-2 pb-2 text-sm">
                    <Checkbox checked={enabled} onCheckedChange={(v) => setEnabled(v === true)} />
                    Enabled
                  </label>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      disabled={saving || !detail.is_system}
                      onClick={() => void handleReset()}
                    >
                      <RotateCcw className="size-4" />
                      Reset default
                    </Button>
                    <Button type="button" size="sm" disabled={saving || !dirty} onClick={() => void handleSave()}>
                      <Save className="size-4" />
                      {saving ? "Saving…" : "Save module"}
                    </Button>
                  </div>
                </div>

                <div className="grid gap-2 border-b border-border px-4 py-2 text-[11px] text-muted-foreground sm:grid-cols-4">
                  <div>
                    Key: <code className="text-foreground">{detail.key}</code>
                  </div>
                  <div>
                    Path: <code className="text-foreground">{detail.filename ?? "—"}</code>
                  </div>
                  <div>
                    Intent: <span className="text-foreground">{detail.intent_key ?? "—"}</span>
                  </div>
                  <div>
                    Size: <span className="text-foreground">{detail.body_chars.toLocaleString()} chars</span>
                  </div>
                </div>

                <div className="space-y-1.5 border-b border-border px-4 py-2">
                  <Label>Description</Label>
                  <Textarea
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    rows={2}
                    className="resize-none"
                  />
                </div>

                <div className="min-h-0 flex-1 bg-slate-950 p-0">
                  <Textarea
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                    spellCheck={false}
                    className="h-full min-h-[320px] resize-none rounded-none border-0 bg-transparent px-4 py-3 font-mono text-[13px] leading-relaxed text-slate-100 shadow-none focus-visible:ring-0"
                  />
                </div>

                <p className="border-t border-amber-500/30 bg-amber-50 px-4 py-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-100">
                  These modules shape Ask TowerOS and Report Builder AI instructions. Keep them concise. Bodies are
                  markdown/text guidance — not executable code.
                </p>
              </>
            )}
          </section>
        </div>
      </div>
    </PermissionGate>
  );
}
