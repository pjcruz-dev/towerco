"use client";

import { useEffect, useState } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import {
  REGISTRY_TABLE_CLASS,
  REGISTRY_TABLE_HEAD_CELL_CLASS,
  REGISTRY_TABLE_HEADER_CLASS,
  RegistryTableScroll,
} from "@/components/registry/registry-data-table";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { fetchEApprovalPdfLayout, updateEApprovalPdfLayout } from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import { isPurchaseOrderPrintTemplate } from "@/modules/e-approval/purchase-order-template";
import type { EApprovalFormFieldInput, EApprovalPdfLayoutRow } from "@/modules/e-approval/types";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

type Props = {
  formId: string;
  fields: EApprovalFormFieldInput[];
};

export function EApprovalPrintLayoutEditor({ formId, fields }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const [rows, setRows] = useState<EApprovalPdfLayoutRow[]>([]);

  const layoutQuery = useQuery({
    queryKey: ["e-approval", "pdf-layout", formId],
    queryFn: () => fetchEApprovalPdfLayout(formId),
  });

  useEffect(() => {
    const serverLayout = layoutQuery.data?.layout ?? [];
    if (serverLayout.length > 0) {
      setRows(serverLayout);
      return;
    }
    setRows(
      fields.map((f) => ({
        key: f.name,
        label: f.label,
        visible: true,
        fieldType: f.type,
      })),
    );
  }, [layoutQuery.data, fields]);

  useEffect(() => {
    if (fields.length === 0) return;
    setRows((prev) => {
      const byKey = new Map(prev.map((r) => [r.key, r]));
      return fields.map((f) => {
        const existing = byKey.get(f.name);
        return {
          key: f.name,
          label: existing?.label ?? f.label,
          visible: existing?.visible ?? true,
          fieldType: f.type,
        };
      });
    });
  }, [fields]);

  const saveMutation = useMutation({
    mutationFn: () =>
      updateEApprovalPdfLayout(formId, {
        layout: rows,
        template: layoutQuery.data?.template,
        active_preset_id: layoutQuery.data?.active_preset_id ?? "default",
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", formId] });
      push({ level: "success", title: "Print layout saved" });
    },
    onError: (e) => push({ level: "error", title: "Save failed", message: getErrorMessage(e) }),
  });

  const visibleCount = rows.filter((r) => r.visible).length;
  const isPoTemplate =
    isPurchaseOrderPrintTemplate(layoutQuery.data?.template) ||
    fields.some((field) => field.name === "grand_total");

  return (
    <section className="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h2 className="text-base font-medium text-foreground">Print / PDF layout</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Choose which fields appear when users print a submission or save as PDF. Order follows the table below.
          </p>
          {isPoTemplate ? (
            <p className="mt-2 text-sm text-primary">
              Purchase order template active — submissions use the structured PO print layout (line items table, tax
              summary, signatures). Field visibility still applies to the fallback list view.
            </p>
          ) : null}
        </div>
        <Button
          type="button"
          size="sm"
          onClick={() => saveMutation.mutate()}
          disabled={saveMutation.isPending || visibleCount === 0}
        >
          Save print layout
        </Button>
      </div>

      {visibleCount === 0 ? (
        <p className="text-sm text-destructive">At least one field must be visible.</p>
      ) : null}

      <div className="overflow-hidden rounded-lg border border-border">
        <RegistryTableScroll className="max-h-[360px]">
          <Table className={REGISTRY_TABLE_CLASS}>
            <TableHeader className={REGISTRY_TABLE_HEADER_CLASS}>
              <TableRow className="hover:bg-transparent">
                <TableHead className={cn(REGISTRY_TABLE_HEAD_CELL_CLASS, "w-12")}>Print</TableHead>
                <TableHead className={REGISTRY_TABLE_HEAD_CELL_CLASS}>Field</TableHead>
                <TableHead className={REGISTRY_TABLE_HEAD_CELL_CLASS}>Label on printout</TableHead>
                <TableHead className={REGISTRY_TABLE_HEAD_CELL_CLASS}>Type</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((row, index) => (
                <TableRow key={row.key}>
                  <TableCell>
                    <Checkbox
                      checked={row.visible}
                      onCheckedChange={(v) => {
                        const next = [...rows];
                        next[index] = { ...row, visible: v === true };
                        setRows(next);
                      }}
                      aria-label={`Show ${row.key} on printout`}
                    />
                  </TableCell>
                  <TableCell className="font-mono text-xs">{row.key}</TableCell>
                  <TableCell>
                    <Input
                      value={row.label}
                      onChange={(e) => {
                        const next = [...rows];
                        next[index] = { ...row, label: e.target.value };
                        setRows(next);
                      }}
                    />
                  </TableCell>
                  <TableCell className="text-muted-foreground">{row.fieldType ?? "—"}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </RegistryTableScroll>
      </div>

      {layoutQuery.data?.updated_at ? (
        <p className="text-xs text-muted-foreground">
          Last saved {layoutQuery.data.updated_by_name ? `by ${layoutQuery.data.updated_by_name}` : ""}
          {layoutQuery.data.layout_persisted ? " · custom layout active" : " · using defaults"}
        </p>
      ) : null}
    </section>
  );
}
