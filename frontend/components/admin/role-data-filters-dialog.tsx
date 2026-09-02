"use client";

import { useEffect, useMemo, useState } from "react";
import { Filter, Lock, Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import type {
  RoleDataFilterGroup,
  RoleDataFilterOperator,
  RoleDataFilterRule,
} from "@/lib/api/modules/admin-roles-api";
import type { DynField } from "@/lib/api/modules/dynamic-entities-api";

const OPERATORS: Array<{ value: RoleDataFilterOperator; label: string; needsValue: boolean }> = [
  { value: "equals", label: "Equals", needsValue: true },
  { value: "not_equals", label: "Not equals", needsValue: true },
  { value: "contains", label: "Contains", needsValue: true },
  { value: "not_contains", label: "Does not contain", needsValue: true },
  { value: "is_empty", label: "Is empty", needsValue: false },
  { value: "is_not_empty", label: "Is not empty", needsValue: false },
  { value: "gt", label: "Greater than", needsValue: true },
  { value: "gte", label: "Greater or equal", needsValue: true },
  { value: "lt", label: "Less than", needsValue: true },
  { value: "lte", label: "Less or equal", needsValue: true },
];

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  roleName: string;
  entityName: string;
  fields: DynField[];
  initial: RoleDataFilterGroup | null;
  onSave: (next: RoleDataFilterGroup | null) => void;
};

function emptyRule(): RoleDataFilterRule {
  return { field: "", operator: "equals", value: "" };
}

export function RoleDataFiltersDialog({
  open,
  onOpenChange,
  roleName,
  entityName,
  fields,
  initial,
  onSave,
}: Props) {
  const [logic, setLogic] = useState<"and" | "or">("and");
  const [rules, setRules] = useState<RoleDataFilterRule[]>([emptyRule()]);

  useEffect(() => {
    if (!open) return;
    if (initial && initial.rules.length > 0) {
      setLogic(initial.logic === "or" ? "or" : "and");
      setRules(initial.rules.map((r) => ({ ...r, value: r.value ?? "" })));
    } else {
      setLogic("and");
      setRules([emptyRule()]);
    }
  }, [open, initial]);

  const fieldOptions = useMemo(() => {
    const builtins: Array<{ name: string; label: string }> = [
      { name: "status", label: "Status" },
      { name: "title", label: "Title" },
      { name: "created_by", label: "Created by (user id)" },
      { name: "assigned_user_id", label: "Assigned user id" },
    ];
    const fromEntity = fields
      .filter((f) => !f.is_system_field || f.name === "status")
      .filter((f) => !["actions", "workflows", "print", "id"].includes(f.name))
      .map((f) => ({ name: f.name, label: f.label }));
    const seen = new Set<string>();
    const out: Array<{ name: string; label: string }> = [];
    for (const row of [...builtins, ...fromEntity]) {
      if (seen.has(row.name)) continue;
      seen.add(row.name);
      out.push(row);
    }
    return out;
  }, [fields]);

  function save() {
    const cleaned = rules
      .map((r) => ({
        field: r.field.trim(),
        operator: r.operator,
        value: (r.value ?? "").trim(),
      }))
      .filter((r) => {
        if (!r.field || !r.operator) return false;
        const meta = OPERATORS.find((o) => o.value === r.operator);
        if (meta && !meta.needsValue) return true;
        return r.value !== "";
      });
    onSave(cleaned.length > 0 ? { logic, rules: cleaned } : null);
    onOpenChange(false);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent
        showCloseButton
        className="flex max-h-[min(90vh,720px)] w-[min(calc(100vw-2rem),720px)] flex-col gap-0 p-0 sm:max-w-none"
      >
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Filter className="size-4 text-muted-foreground" />
            Configure Role Data Filters
          </DialogTitle>
          <DialogDescription>
            Configure dynamic, cross-field data filters for role <strong>{roleName}</strong> on entity{" "}
            <strong>{entityName}</strong>. Records will be automatically filtered when queried by users
            with this role.
          </DialogDescription>
        </DialogHeader>

        <DialogBody className="space-y-5">
          <div className="space-y-2">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
              Rule combination logic
            </p>
            <div className="flex flex-wrap gap-4">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="radio"
                  name="filter-logic"
                  checked={logic === "and"}
                  onChange={() => setLogic("and")}
                />
                Match ALL rules (AND)
              </label>
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="radio"
                  name="filter-logic"
                  checked={logic === "or"}
                  onChange={() => setLogic("or")}
                />
                Match ANY rule (OR)
              </label>
            </div>
          </div>

          <div className="space-y-2">
            {rules.map((rule, index) => {
              const needsValue = OPERATORS.find((o) => o.value === rule.operator)?.needsValue ?? true;
              return (
                <div key={index} className="flex flex-wrap items-center gap-2 rounded-lg border border-border p-2">
                  <select
                    className="h-9 min-w-[10rem] flex-1 rounded-md border border-input bg-background px-2 text-sm"
                    value={rule.field}
                    onChange={(e) =>
                      setRules((prev) =>
                        prev.map((r, i) => (i === index ? { ...r, field: e.target.value } : r)),
                      )
                    }
                  >
                    <option value="">— Select Field —</option>
                    {fieldOptions.map((f) => (
                      <option key={f.name} value={f.name}>
                        {f.label} ({f.name})
                      </option>
                    ))}
                  </select>
                  <select
                    className="h-9 min-w-[9rem] rounded-md border border-input bg-background px-2 text-sm"
                    value={rule.operator}
                    disabled={!rule.field}
                    onChange={(e) =>
                      setRules((prev) =>
                        prev.map((r, i) =>
                          i === index
                            ? { ...r, operator: e.target.value as RoleDataFilterOperator }
                            : r,
                        ),
                      )
                    }
                  >
                    {!rule.field ? <option value="">— Select Field First —</option> : null}
                    {OPERATORS.map((op) => (
                      <option key={op.value} value={op.value}>
                        {op.label}
                      </option>
                    ))}
                  </select>
                  <input
                    className="h-9 min-w-[8rem] flex-1 rounded-md border border-input bg-background px-2 text-sm disabled:opacity-50"
                    placeholder={needsValue ? "Value…" : "N/A"}
                    value={rule.value ?? ""}
                    disabled={!rule.field || !needsValue}
                    onChange={(e) =>
                      setRules((prev) =>
                        prev.map((r, i) => (i === index ? { ...r, value: e.target.value } : r)),
                      )
                    }
                  />
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    className="text-destructive"
                    aria-label="Remove rule"
                    onClick={() =>
                      setRules((prev) => (prev.length <= 1 ? [emptyRule()] : prev.filter((_, i) => i !== index)))
                    }
                  >
                    <Trash2 className="size-3.5" />
                  </Button>
                </div>
              );
            })}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setRules((prev) => [...prev, emptyRule()])}
            >
              <Plus className="size-3.5" />
              Add Filter Rule
            </Button>
          </div>
        </DialogBody>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" onClick={save}>
            <Lock className="size-3.5" />
            Save Filters
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
