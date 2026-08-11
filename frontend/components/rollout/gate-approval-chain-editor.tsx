"use client";

import { ChevronDown, ChevronUp, Plus, X } from "lucide-react";
import { useEffect, useState } from "react";

import { Button } from "@/components/ui/button";
import {
  chainUsesAdvancedGateRoles,
  defaultRoleForNewChainStep,
  gateApprovalChainRoleLabel,
  GATE_APPROVAL_CHAIN_ADVANCED_ROLES,
  GATE_APPROVAL_CHAIN_CORE_ROLES,
  isKnownGateApprovalChainRole,
  roleOptionsForChainStep,
  type GateApprovalChainRole,
} from "@/lib/rollout/gate-approval-chain-roles";
import { cn } from "@/lib/utils";

type Props = {
  chain: string[];
  disabled?: boolean;
  onChange: (chain: string[]) => void;
};

function moveItem<T>(items: T[], from: number, to: number): T[] {
  if (to < 0 || to >= items.length) {
    return items;
  }

  const next = [...items];
  const [item] = next.splice(from, 1);
  next.splice(to, 0, item!);
  return next;
}

function RoleSelect({
  role,
  showAdvanced,
  index,
  onChange,
}: {
  role: string;
  showAdvanced: boolean;
  index: number;
  onChange: (role: GateApprovalChainRole) => void;
}) {
  const unknown = !isKnownGateApprovalChainRole(role);
  const options = roleOptionsForChainStep(role, showAdvanced);
  const showCoreGroup = options.length > GATE_APPROVAL_CHAIN_CORE_ROLES.length;
  const coreOptions = showCoreGroup ? GATE_APPROVAL_CHAIN_CORE_ROLES : options;
  const advancedOptions = showCoreGroup
    ? GATE_APPROVAL_CHAIN_ADVANCED_ROLES.filter((entry) => options.some((o) => o.value === entry.value))
    : [];

  return (
    <select
      className={cn(
        "h-9 min-w-0 flex-1 rounded-md border border-input bg-background px-2 text-xs",
        unknown && "border-amber-500/60",
      )}
      value={unknown ? "" : role}
      aria-label={`Approval step ${index + 1}`}
      onChange={(e) => onChange(e.target.value as GateApprovalChainRole)}
    >
      {unknown ? (
        <option value="" disabled>
          Unknown: {role} — pick a role
        </option>
      ) : null}
      {showCoreGroup ? (
        <optgroup label="Core (rollout owners)">
          {coreOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </optgroup>
      ) : (
        coreOptions.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))
      )}
      {advancedOptions.length > 0 ? (
        <optgroup label="Advanced">
          {advancedOptions.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </optgroup>
      ) : null}
    </select>
  );
}

export function GateApprovalChainEditor({ chain, disabled, onChange }: Props) {
  const [showAdvanced, setShowAdvanced] = useState(() => chainUsesAdvancedGateRoles(chain));

  useEffect(() => {
    if (chainUsesAdvancedGateRoles(chain)) {
      setShowAdvanced(true);
    }
  }, [chain]);

  if (disabled) {
    if (chain.length === 0) {
      return <span className="text-xs text-muted-foreground">No approvers configured</span>;
    }

    return (
      <div className="flex flex-wrap items-center gap-1">
        {chain.map((role, index) => (
          <span key={`${role}-${index}`} className="inline-flex items-center gap-1">
            {index > 0 ? <span className="text-muted-foreground">→</span> : null}
            <span
              className={cn(
                "rounded-md border px-2 py-0.5 text-xs font-medium",
                isKnownGateApprovalChainRole(role)
                  ? "border-border bg-muted/50 text-foreground"
                  : "border-amber-500/50 bg-amber-500/10 text-amber-900 dark:text-amber-100",
              )}
              title={role}
            >
              {gateApprovalChainRoleLabel(role)}
            </span>
          </span>
        ))}
      </div>
    );
  }

  const updateRole = (index: number, role: GateApprovalChainRole) => {
    const next = [...chain];
    next[index] = role;
    onChange(next);
  };

  const removeStep = (index: number) => {
    onChange(chain.filter((_, i) => i !== index));
  };

  const addStep = () => {
    onChange([...chain, defaultRoleForNewChainStep(chain)]);
  };

  return (
    <div className="space-y-2">
      {chain.length === 0 ? (
        <p className="text-xs text-muted-foreground">
          Add steps in order. Start with <strong>SAQ</strong> and <strong>PMO</strong> — they map to owners on each rollout.
        </p>
      ) : null}

      <ol className="space-y-1.5">
        {chain.map((role, index) => (
          <li key={`${index}-${role}`} className="flex items-center gap-1.5">
            <span className="w-5 shrink-0 text-center text-[10px] font-medium text-muted-foreground">{index + 1}</span>
            <RoleSelect
              role={role}
              showAdvanced={showAdvanced}
              index={index + 1}
              onChange={(nextRole) => updateRole(index, nextRole)}
            />
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className="shrink-0"
              disabled={index === 0}
              aria-label="Move step up"
              onClick={() => onChange(moveItem(chain, index, index - 1))}
            >
              <ChevronUp className="size-3.5" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className="shrink-0"
              disabled={index === chain.length - 1}
              aria-label="Move step down"
              onClick={() => onChange(moveItem(chain, index, index + 1))}
            >
              <ChevronDown className="size-3.5" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon-xs"
              className="shrink-0 text-muted-foreground hover:text-destructive"
              aria-label="Remove step"
              onClick={() => removeStep(index)}
            >
              <X className="size-3.5" />
            </Button>
          </li>
        ))}
      </ol>

      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" variant="outline" size="xs" className="h-7" onClick={addStep}>
          <Plus className="size-3.5" />
          Add step
        </Button>
        <button
          type="button"
          className="text-xs text-primary hover:underline"
          onClick={() => setShowAdvanced((value) => !value)}
        >
          {showAdvanced ? "Hide advanced step types" : "Show advanced step types (MNO, Engineering, …)"}
        </button>
      </div>

      <p className="text-[11px] leading-snug text-muted-foreground">
        <strong>Core:</strong> SAQ, PMO, and CME use the SAQ owner, PMO owner, and CME PM on each rollout.{" "}
        {showAdvanced ? (
          <>
            <strong>Advanced:</strong> extra disciplines for specific playbook phases (not separate metadata fields).
          </>
        ) : null}
      </p>
    </div>
  );
}
