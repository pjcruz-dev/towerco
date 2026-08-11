"use client";

import Link from "next/link";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button, buttonVariants } from "@/components/ui/button";
import { getErrorMessage } from "@/lib/api/error";
import { createRolloutBatch } from "@/lib/api/modules/rollout-api";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type SiteRow = { search_ring_name: string; region: string; territory: string };

export function RolloutBatchNewPageClient() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [mno, setMno] = useState<"globe" | "smart" | "dito">("globe");
  const [batchLabel, setBatchLabel] = useState("");
  const [endorsementRef, setEndorsementRef] = useState("");
  const [region, setRegion] = useState("ncr");
  const [sites, setSites] = useState<SiteRow[]>([
    { search_ring_name: "", region: "ncr", territory: "" },
    { search_ring_name: "", region: "ncr", territory: "" },
  ]);

  const mutation = useMutation({
    mutationFn: () =>
      createRolloutBatch({
        mno,
        project_type: "bts",
        batch_label: batchLabel.trim() || undefined,
        endorsement_ref: endorsementRef.trim() || undefined,
        region: region.trim() || undefined,
        sites: sites
          .filter((s) => s.search_ring_name.trim())
          .map((s) => ({
            search_ring_name: s.search_ring_name.trim(),
            region: s.region.trim() || undefined,
            territory: s.territory.trim() || undefined,
          })),
      }),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      push({ level: "success", title: "Batch created", message: `${data.children.length} child rollouts created.` });
      router.push(`/project-one/rollouts/${data.parent.id}`);
    },
    onError: (error) => push({ level: "error", title: "Batch failed", message: getErrorMessage(error) }),
  });

  return (
    <PermissionGate requiredPermissions={[permissions.rolloutManage]}>
      <div className="space-y-5">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">New batch rollout</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            <AcronymText text="One MNO batch endorsement spawns a parent batch record plus independent child rollouts (one site each)." />
          </p>
          <p className="mt-2 text-xs font-medium">
            <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollouts">
              Back to rollouts
            </Link>
          </p>
        </header>

        <form
          className="space-y-4 rounded-xl border border-border bg-card p-5 shadow-sm"
          onSubmit={(e) => {
            e.preventDefault();
            mutation.mutate();
          }}
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">
                <AcronymLabel term="MNO" />
              </span>
              <select className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm" value={mno} onChange={(e) => setMno(e.target.value as typeof mno)}>
                <option value="globe">Globe</option>
                <option value="smart">Smart</option>
                <option value="dito">DITO</option>
              </select>
            </label>
            <FormInput label="Region" value={region} onChange={(e) => setRegion(e.target.value)} />
          </div>
          <FormInput label="Batch label" value={batchLabel} onChange={(e) => setBatchLabel(e.target.value)} placeholder="e.g. NCR Q2 Batch" />
          <FormInput label="Endorsement reference" value={endorsementRef} onChange={(e) => setEndorsementRef(e.target.value)} />

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h2 className="text-base font-medium text-foreground">Sites in batch</h2>
              <Button
                type="button"
                size="sm"
                variant="outline"
                onClick={() => setSites((rows) => [...rows, { search_ring_name: "", region: region, territory: "" }])}
              >
                Add site
              </Button>
            </div>
            {sites.map((site, index) => (
              <div key={index} className="grid gap-3 rounded-lg border border-border p-3 sm:grid-cols-3">
                <FormInput
                  label={`Search ring ${index + 1}`}
                  value={site.search_ring_name}
                  onChange={(e) =>
                    setSites((rows) => rows.map((row, i) => (i === index ? { ...row, search_ring_name: e.target.value } : row)))
                  }
                />
                <FormInput
                  label="Region"
                  value={site.region}
                  onChange={(e) =>
                    setSites((rows) => rows.map((row, i) => (i === index ? { ...row, region: e.target.value } : row)))
                  }
                />
                <FormInput
                  label="Territory"
                  value={site.territory}
                  onChange={(e) =>
                    setSites((rows) => rows.map((row, i) => (i === index ? { ...row, territory: e.target.value } : row)))
                  }
                />
              </div>
            ))}
          </div>

          <div className="flex justify-end gap-2">
            <Link href="/project-one/rollouts" className={buttonVariants({ variant: "outline" })}>
              Cancel
            </Link>
            <Button type="submit" disabled={mutation.isPending}>
              {mutation.isPending ? "Creating…" : "Create batch"}
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
