"use client";

import { Plus, Trash2 } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import type {
  TicketingAssignmentRule,
  TicketingCategoryOption,
  TicketingUserRef,
} from "@/modules/ticketing/types";

type Props = {
  rules: TicketingAssignmentRule[];
  categories: TicketingCategoryOption[];
  users: TicketingUserRef[];
  onChange: (rules: TicketingAssignmentRule[]) => void;
};

export function TicketingAssignmentRulesEditor({ rules, categories, users, onChange }: Props) {
  const used = new Set(rules.map((rule) => rule.category));
  const available = categories.filter((category) => !used.has(category.id));

  function addRule() {
    const nextCategory = available[0]?.id;
    const nextUser = users[0]?.id;
    if (!nextCategory || !nextUser) return;
    onChange([...rules, { category: nextCategory, assignee_id: nextUser, enabled: true }]);
  }

  function updateRule(index: number, patch: Partial<TicketingAssignmentRule>) {
    onChange(rules.map((rule, i) => (i === index ? { ...rule, ...patch } : rule)));
  }

  function removeRule(index: number) {
    onChange(rules.filter((_, i) => i !== index));
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-sm font-medium text-foreground">Auto-assign rules</h2>
          <p className="mt-1 text-xs text-muted-foreground">
            When a ticket is created without an assignee, route by category. Explicit assignee on create
            still wins.
          </p>
        </div>
        <Button
          type="button"
          size="sm"
          variant="outline"
          className="shrink-0"
          disabled={available.length === 0 || users.length === 0}
          onClick={addRule}
        >
          <Plus className="mr-1.5 h-4 w-4" aria-hidden />
          Add rule
        </Button>
      </div>

      {users.length === 0 ? (
        <p className="text-xs text-muted-foreground">No assignable users available yet.</p>
      ) : null}

      <div className="space-y-2">
        {rules.length === 0 ? (
          <p className="rounded-lg border border-dashed border-border px-3 py-4 text-center text-xs text-muted-foreground">
            No auto-assign rules. New tickets stay unassigned until someone claims them.
          </p>
        ) : (
          rules.map((rule, index) => {
            const label =
              categories.find((category) => category.id === rule.category)?.label ?? rule.category;
            return (
              <div
                key={`${rule.category}-${index}`}
                className="grid gap-2 rounded-lg border border-border p-3 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end"
              >
                <div className="space-y-1.5">
                  <Label className="text-xs">Category</Label>
                  <Select
                    className="h-9"
                    value={rule.category}
                    onChange={(e) => updateRule(index, { category: e.target.value })}
                  >
                    <option value={rule.category}>{label}</option>
                    {available.map((category) => (
                      <option key={category.id} value={category.id}>
                        {category.label}
                      </option>
                    ))}
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label className="text-xs">Assignee</Label>
                  <Select
                    className="h-9"
                    value={rule.assignee_id}
                    onChange={(e) => updateRule(index, { assignee_id: e.target.value })}
                  >
                    {users.map((user) => (
                      <option key={user.id} value={user.id}>
                        {user.name}
                      </option>
                    ))}
                  </Select>
                </div>
                <label className="inline-flex h-9 items-center gap-2 text-xs text-foreground">
                  <Checkbox
                    checked={rule.enabled}
                    onCheckedChange={(v) => updateRule(index, { enabled: v === true })}
                  />
                  Enabled
                </label>
                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="h-9 w-9 px-0 text-destructive"
                  onClick={() => removeRule(index)}
                  aria-label={`Remove rule for ${label}`}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
}
