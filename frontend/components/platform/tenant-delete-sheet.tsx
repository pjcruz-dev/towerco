"use client";

import { FormInput } from "@/components/forms/form-input";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";

type ChildTenant = {
  id: string;
  environment: string | null;
  domain: string | null;
};

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tenantId: string;
  tenantLabel: string;
  domains: string[];
  childTenants: ChildTenant[];
  confirmation: string;
  cascadeDelete: boolean;
  onConfirmationChange: (value: string) => void;
  onCascadeDeleteChange: (value: boolean) => void;
  isPending: boolean;
  onConfirm: () => void;
};

export function TenantDeleteSheet({
  open,
  onOpenChange,
  tenantId,
  tenantLabel,
  domains,
  childTenants,
  confirmation,
  cascadeDelete,
  onConfirmationChange,
  onCascadeDeleteChange,
  isPending,
  onConfirm,
}: Props) {
  const canDelete = confirmation.trim() === tenantId;
  const hasChildren = childTenants.length > 0;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md">
        <SheetHeader>
          <SheetTitle>Delete tenant</SheetTitle>
          <SheetDescription>
            Permanently remove <span className="font-medium text-foreground">{tenantLabel}</span> and all tenant data.
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 px-4 py-2 text-sm text-muted-foreground">
          <div className="rounded-md border border-destructive/30 bg-destructive/5 p-3 text-destructive dark:text-red-300">
            <p className="font-medium text-destructive dark:text-red-200">This action cannot be undone.</p>
            <ul className="mt-2 list-inside list-disc space-y-1 text-xs leading-relaxed">
              <li>Tenant database and all rollout, user, and audit data</li>
              <li>Domains, SSO config, and playbook bindings</li>
              <li>Uploaded tenant files and storage artifacts</li>
            </ul>
          </div>

          {hasChildren ? (
            <div className="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-950 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-100">
              <p className="font-medium">Linked environment tenants</p>
              <p className="mt-1">
                This tenant is the org root for other environments. Delete them together, or remove each environment
                tenant individually first.
              </p>
              <ul className="mt-2 space-y-1 font-mono">
                {childTenants.map((child) => (
                  <li key={child.id}>
                    {(child.environment ?? "unknown").toUpperCase()}: {child.domain ?? child.id}
                  </li>
                ))}
              </ul>
              <label className="mt-3 flex items-start gap-2">
                <Checkbox
                  className="mt-0.5 size-4"
                  checked={cascadeDelete}
                  onCheckedChange={(v) => onCascadeDeleteChange(v === true)}
                />
                <span>
                  Also delete {childTenants.length} linked environment tenant
                  {childTenants.length === 1 ? "" : "s"}
                </span>
              </label>
            </div>
          ) : null}

          <div className="space-y-1 text-xs">
            <p>
              <span className="font-medium text-foreground">Tenant ID:</span>{" "}
              <span className="font-mono">{tenantId}</span>
            </p>
            {domains.length > 0 ? (
              <p>
                <span className="font-medium text-foreground">Domains:</span>{" "}
                <span className="font-mono">{domains.join(", ")}</span>
              </p>
            ) : null}
          </div>

          <FormInput
            label="Type tenant ID to confirm"
            value={confirmation}
            onChange={(event) => onConfirmationChange(event.target.value)}
            placeholder={tenantId}
            autoComplete="off"
          />
        </div>

        <SheetFooter className="gap-2 sm:gap-0">
          <Button
            type="button"
            variant="outline"
            disabled={isPending}
            onClick={() => onOpenChange(false)}
          >
            Cancel
          </Button>
          <Button
            type="button"
            variant="destructive"
            disabled={!canDelete || isPending || (hasChildren && !cascadeDelete)}
            onClick={onConfirm}
          >
            {isPending ? "Deleting…" : hasChildren ? "Delete tenant group" : "Delete tenant"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
