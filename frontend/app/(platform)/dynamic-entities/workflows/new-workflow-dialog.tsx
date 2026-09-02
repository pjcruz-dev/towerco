"use client";

import { useEffect, useState } from "react";
import { Filter, MousePointerClick, RefreshCw, Zap } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { getErrorMessage } from "@/lib/api/error";
import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";
import {
  createDynWorkflow,
  fetchDynEntities,
  type DynEntitySummary,
  type DynWorkflowTriggerMode,
} from "@/lib/api/modules/dynamic-entities-api";
import { cn } from "@/lib/utils";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onCreated: (id: string) => void;
};

const TRIGGERS: Array<{
  id: DynWorkflowTriggerMode;
  label: string;
  icon: typeof Zap;
}> = [
  { id: "manual", label: "Manual Button (User-Triggered)", icon: MousePointerClick },
  { id: "on_create", label: "Auto on Record Create", icon: Zap },
  { id: "on_update", label: "Auto on Record Update", icon: RefreshCw },
];

export function NewWorkflowDialog({ open, onOpenChange, onCreated }: Props) {
  const [entities, setEntities] = useState<DynEntitySummary[]>([]);
  const [roles, setRoles] = useState<Array<{ id: string; name: string }>>([]);
  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [entitySlug, setEntitySlug] = useState("");
  const [triggerMode, setTriggerMode] = useState<DynWorkflowTriggerMode>("manual");
  const [statusField, setStatusField] = useState("status");
  const [statusMatches, setStatusMatches] = useState("");
  const [roleIds, setRoleIds] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    let cancelled = false;
    void (async () => {
      try {
        const [ents, catalog] = await Promise.all([
          fetchDynEntities({ active_only: true }),
          fetchAdminRoleCatalog().catch(() => null),
        ]);
        if (cancelled) return;
        setEntities(ents);
        setRoles((catalog?.roles ?? []).map((r) => ({ id: String(r.id), name: r.name })));
      } catch {
        /* ignore preload errors; form still usable */
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [open]);

  function reset() {
    setName("");
    setDescription("");
    setEntitySlug("");
    setTriggerMode("manual");
    setStatusField("status");
    setStatusMatches("");
    setRoleIds([]);
    setError(null);
  }

  function toggleRole(id: string) {
    setRoleIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  }

  async function onSave() {
    if (!name.trim()) {
      setError("Workflow name is required.");
      return;
    }
    if (!entitySlug) {
      setError("Choose a target entity.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const created = await createDynWorkflow({
        name: name.trim(),
        description: description.trim() || undefined,
        entity_slug: entitySlug,
        trigger_mode: triggerMode,
        status_field: statusField.trim() || "status",
        status_matches: statusMatches,
        role_ids: triggerMode === "manual" ? roleIds : [],
      });
      reset();
      onCreated(created.id);
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) reset();
        onOpenChange(next);
      }}
    >
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
        <DialogHeader className="border-b bg-slate-800 text-white sm:rounded-t-lg">
          <DialogTitle className="text-base font-medium text-white">+ New Workflow</DialogTitle>
        </DialogHeader>
        <DialogBody className="space-y-4 pt-4">
          {error ? (
            <div className="rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2 text-sm text-destructive">
              {error}
            </div>
          ) : null}

          <div className="space-y-1.5">
            <Label>Workflow Name</Label>
            <Input
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="e.g. Approve Sales Order"
            />
          </div>
          <div className="space-y-1.5">
            <Label>Description</Label>
            <Textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Explain what business action this workflow automates..."
              className="min-h-[72px]"
            />
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label>Target Entity</Label>
              <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={entitySlug}
                onChange={(e) => setEntitySlug(e.target.value)}
              >
                <option value="">Choose entity...</option>
                {entities.map((e) => (
                  <option key={e.id} value={e.slug}>
                    {e.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-1.5">
              <Label>Trigger Mode</Label>
              <select
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={triggerMode}
                onChange={(e) => setTriggerMode(e.target.value as DynWorkflowTriggerMode)}
              >
                {TRIGGERS.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.label}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="space-y-3 border-t pt-4">
            <div className="flex items-center gap-2 text-sm font-medium">
              <Filter className="size-4 text-muted-foreground" />
              Trigger Constraints
            </div>
            <div className="grid gap-3 sm:grid-cols-2">
              <div className="space-y-1.5">
                <Label>Status Field Slug</Label>
                <Input value={statusField} onChange={(e) => setStatusField(e.target.value)} />
                <p className="text-[11px] text-muted-foreground">Name of the select field to track.</p>
              </div>
              <div className="space-y-1.5">
                <Label>When Status matches...</Label>
                <Input
                  value={statusMatches}
                  onChange={(e) => setStatusMatches(e.target.value)}
                  placeholder="e.g. Draft, Pending"
                />
                <p className="text-[11px] text-muted-foreground">
                  Comma-separated status options that qualify.
                </p>
              </div>
            </div>

            {triggerMode === "manual" ? (
              <div className="space-y-2">
                <Label>Allowed Roles to click button</Label>
                {roles.length === 0 ? (
                  <p className="text-xs text-muted-foreground">
                    No roles loaded. Leave unchecked to allow everyone with record manage access.
                  </p>
                ) : (
                  <div className="grid max-h-40 grid-cols-1 gap-2 overflow-y-auto rounded-lg border p-3 sm:grid-cols-3">
                    {roles.map((role) => {
                      const checked = roleIds.includes(role.id);
                      return (
                        <label
                          key={role.id}
                          className={cn(
                            "flex cursor-pointer items-center gap-2 rounded-md px-1 py-0.5 text-sm",
                            checked && "bg-muted/50",
                          )}
                        >
                          <Checkbox
                            checked={checked}
                            onCheckedChange={() => toggleRole(role.id)}
                          />
                          <span className="truncate">{role.name}</span>
                        </label>
                      );
                    })}
                  </div>
                )}
                <p className="text-[11px] text-muted-foreground">
                  Select roles allowed to run this manually. Leave all unchecked to allow everyone.
                </p>
              </div>
            ) : null}
          </div>
        </DialogBody>
        <DialogFooter>
          <Button type="button" variant="ghost" disabled={busy} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={busy} onClick={() => void onSave()}>
            Save Workflow
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
