"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

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
import { Spinner } from "@/components/ui/spinner";
import { getErrorMessage } from "@/lib/api/error";
import { fetchAdminRoleCatalog } from "@/lib/api/modules/admin-roles-api";
import { updateAdminUser } from "@/lib/api/modules/admin-users-api";
import type { OrgChartNode } from "@/lib/admin/org-chart";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  person: OrgChartNode | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function AdminOrgPersonRolesSheet({ person, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();
  const notify = useNotificationStore((state) => state.push);
  const [selected, setSelected] = useState<string[]>([]);

  const catalogQuery = useQuery({
    queryKey: ["admin", "roles"],
    queryFn: fetchAdminRoleCatalog,
    enabled: open,
    staleTime: 60_000,
  });

  const roleNames = useMemo(
    () => (catalogQuery.data?.roles ?? []).map((role) => role.name).sort((a, b) => a.localeCompare(b)),
    [catalogQuery.data?.roles],
  );

  useEffect(() => {
    if (open && person) {
      setSelected([...(person.roles ?? [])]);
    }
  }, [open, person]);

  const saveMutation = useMutation({
    mutationFn: async () => {
      if (!person || person.external) {
        throw new Error("Cannot assign roles to an Entra-only manager.");
      }
      return updateAdminUser(person.id, { roles: selected });
    },
    onSuccess: () => {
      notify({
        level: "success",
        title: "Roles updated",
        message: `Saved roles for ${person?.name ?? "user"}.`,
      });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users"] });
      void queryClient.invalidateQueries({ queryKey: ["admin", "users", "org-chart"] });
      onOpenChange(false);
    },
    onError: (error) => {
      notify({
        level: "error",
        title: "Could not save roles",
        message: getErrorMessage(error),
      });
    },
  });

  const toggle = (role: string, checked: boolean) => {
    setSelected((current) => {
      if (checked) {
        return current.includes(role) ? current : [...current, role];
      }
      return current.filter((name) => name !== role);
    });
  };

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full sm:max-w-md" data-org-no-pan="">
        <SheetHeader>
          <SheetTitle>Assign roles</SheetTitle>
          <SheetDescription>
            {person
              ? `Choose tenant roles for ${person.name}. These are the same roles managed under Roles & permissions.`
              : "Select a person first."}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-4">
          {person?.external ? (
            <p className="text-sm text-muted-foreground">
              This person exists only in Microsoft Entra. Create a TowerOS user account before assigning roles.
            </p>
          ) : catalogQuery.isLoading ? (
            <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
              <Spinner className="size-4" /> Loading roles…
            </div>
          ) : roleNames.length === 0 ? (
            <p className="text-sm text-muted-foreground">No roles available.</p>
          ) : (
            <ul className="space-y-2 py-2">
              {roleNames.map((role) => {
                const checked = selected.includes(role);
                return (
                  <li key={role}>
                    <label className="flex cursor-pointer items-start gap-2.5 rounded-lg border border-border px-3 py-2.5 hover:bg-muted/40">
                      <Checkbox
                        checked={checked}
                        onCheckedChange={(value) => toggle(role, value === true)}
                        className="mt-0.5"
                      />
                      <span className="min-w-0">
                        <span className="block text-sm font-medium text-foreground">{role}</span>
                      </span>
                    </label>
                  </li>
                );
              })}
            </ul>
          )}
        </div>

        <SheetFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={!person || person.external || saveMutation.isPending}
            onClick={() => saveMutation.mutate()}
          >
            {saveMutation.isPending ? <Spinner className="size-3.5" /> : null}
            Save roles
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}
