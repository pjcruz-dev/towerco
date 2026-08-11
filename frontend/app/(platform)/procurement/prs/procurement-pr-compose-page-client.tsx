"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Plus, Trash2 } from "lucide-react";

import { ProcurementOnePageHeader } from "@/components/procurement-one/procurement-one-page-header";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SectionCardSkeleton } from "@/components/ui/page-skeletons";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import {
  createProcurementPr,
  fetchProcurementPr,
  submitProcurementPr,
  updateProcurementPr,
} from "@/lib/api/modules/procurement-one-api";
import { getErrorMessage } from "@/lib/api/error";
import type { ProcurementPrLine } from "@/modules/procurement-one/types";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { prId?: string };

const emptyLine = (): ProcurementPrLine => ({ description: "", quantity: 1, unit_price: 0 });

export function ProcurementPrComposePageClient({ prId }: Props) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const pushNotification = useNotificationStore((s) => s.push);
  const isEdit = Boolean(prId);

  const [title, setTitle] = useState("");
  const [department, setDepartment] = useState("operations");
  const [urgency, setUrgency] = useState("normal");
  const [justification, setJustification] = useState("");
  const [projectId, setProjectId] = useState("");
  const [rolloutId, setRolloutId] = useState("");
  const [siteId, setSiteId] = useState("");
  const [boqLineId, setBoqLineId] = useState("");
  const [lines, setLines] = useState<ProcurementPrLine[]>([emptyLine()]);

  const prQuery = useQuery({
    queryKey: ["procurement-one", "pr", prId],
    queryFn: () => fetchProcurementPr(prId!),
    enabled: isEdit,
  });

  useEffect(() => {
    const pr = prQuery.data;
    if (!pr) return;
    setTitle(pr.title);
    setDepartment(pr.department ?? "operations");
    setUrgency(pr.urgency ?? "normal");
    setJustification(pr.justification ?? "");
    setProjectId(pr.project_id ?? "");
    setRolloutId(pr.rollout_id ?? "");
    setSiteId(pr.site_id ?? "");
    setBoqLineId(pr.boq_line_id ?? "");
    setLines(pr.lines.length > 0 ? pr.lines : [emptyLine()]);
  }, [prQuery.data]);

  const estimatedTotal = useMemo(
    () => lines.reduce((sum, line) => sum + line.quantity * line.unit_price, 0),
    [lines],
  );

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        title: title.trim(),
        department,
        urgency,
        justification: justification.trim(),
        project_id: projectId.trim() || null,
        rollout_id: rolloutId.trim() || null,
        site_id: siteId.trim() || null,
        boq_line_id: boqLineId.trim() || null,
        lines: lines.filter((line) => line.description.trim() !== ""),
      };
      return isEdit ? updateProcurementPr(prId!, payload) : createProcurementPr(payload);
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "prs"] });
      queryClient.setQueryData(["procurement-one", "pr", data.id], data);
      pushNotification({ title: "Purchase requisition saved", variant: "success" });
      if (!isEdit) router.replace(`/procurement/prs/${data.id}/edit`);
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const submitMutation = useMutation({
    mutationFn: async () => {
      const id = prId ?? (await saveMutation.mutateAsync()).id;
      return submitProcurementPr(id);
    },
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ["procurement-one", "prs"] });
      if (result.warning) {
        pushNotification({ title: result.warning, variant: "warning" });
      }
      pushNotification({ title: "Purchase requisition submitted for approval", variant: "success" });
      router.push(`/procurement/prs/${result.pr.id}`);
    },
    onError: (error) => pushNotification({ title: getErrorMessage(error), variant: "destructive" }),
  });

  const updateLine = (index: number, patch: Partial<ProcurementPrLine>) => {
    setLines((current) => current.map((line, i) => (i === index ? { ...line, ...patch } : line)));
  };

  if (isEdit && prQuery.isLoading) return <SectionCardSkeleton />;

  return (
    <PermissionGate requiredPermissions={[permissions.procurementOneDocumentsCreate]}>
      <div className="space-y-6">
        <ProcurementOnePageHeader
          eyebrow={
            <Link href="/procurement/prs" className="hover:text-primary">
              Purchase requisitions
            </Link>
          }
          title={isEdit ? "Edit purchase requisition" : "New purchase requisition"}
          description="Draft a PR with line items; submit when ready for E-Approval workflow."
        />

        <form
          className="space-y-6"
          onSubmit={(e) => {
            e.preventDefault();
            saveMutation.mutate();
          }}
        >
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm space-y-4">
            <div className="space-y-2">
              <Label htmlFor="title">Title *</Label>
              <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} required />
            </div>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="department">Department</Label>
                <Select
                  id="department"
                  className="h-9"
                  value={department}
                  onChange={(e) => setDepartment(e.target.value)}
                >
                  <option value="operations">Operations</option>
                  <option value="finance">Finance</option>
                  <option value="engineering">Engineering</option>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="urgency">Urgency</Label>
                <Select id="urgency" className="h-9" value={urgency} onChange={(e) => setUrgency(e.target.value)}>
                  <option value="normal">Normal</option>
                  <option value="urgent">Urgent</option>
                </Select>
              </div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="justification">Justification</Label>
              <Textarea
                id="justification"
                value={justification}
                onChange={(e) => setJustification(e.target.value)}
                rows={3}
              />
            </div>
          </section>

          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-base font-medium">Line items</h2>
              <Button type="button" size="sm" variant="outline" onClick={() => setLines((c) => [...c, emptyLine()])}>
                <Plus className="mr-1.5 h-4 w-4" aria-hidden />
                Add line
              </Button>
            </div>
            <div className="mt-4 space-y-3">
              {lines.map((line, index) => (
                <div key={index} className="grid gap-2 rounded-lg border border-border p-3 md:grid-cols-4">
                  <Input
                    placeholder="Description"
                    value={line.description}
                    onChange={(e) => updateLine(index, { description: e.target.value })}
                    className="md:col-span-2"
                    required
                  />
                  <Input
                    type="number"
                    min={0}
                    step="0.01"
                    placeholder="Qty"
                    value={line.quantity}
                    onChange={(e) => updateLine(index, { quantity: Number(e.target.value) })}
                  />
                  <div className="flex gap-2">
                    <Input
                      type="number"
                      min={0}
                      step="0.01"
                      placeholder="Unit price"
                      value={line.unit_price}
                      onChange={(e) => updateLine(index, { unit_price: Number(e.target.value) })}
                    />
                    <Button type="button" variant="ghost" size="icon" onClick={() => setLines((c) => c.filter((_, i) => i !== index))}>
                      <Trash2 className="h-4 w-4" aria-hidden />
                    </Button>
                  </div>
                </div>
              ))}
            </div>
            <p className="mt-3 text-sm text-muted-foreground">
              Estimated total: PHP {estimatedTotal.toLocaleString(undefined, { minimumFractionDigits: 2 })}
            </p>
          </section>

          <div className="flex flex-wrap justify-end gap-2">
            <Button type="button" variant="outline" render={<Link href={isEdit ? `/procurement/prs/${prId}` : "/procurement/prs"} />}>
              Cancel
            </Button>
            <Button type="submit" disabled={saveMutation.isPending || title.trim() === ""}>
              Save draft
            </Button>
            <Button
              type="button"
              disabled={submitMutation.isPending || saveMutation.isPending}
              onClick={() => submitMutation.mutate()}
            >
              Submit for approval
            </Button>
          </div>
        </form>
      </div>
    </PermissionGate>
  );
}
