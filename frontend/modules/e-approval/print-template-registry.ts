import type { ComponentType } from "react";

import { EApprovalPurchaseOrderPrintView } from "@/components/e-approval/e-approval-purchase-order-print-view";
import { EApprovalPurchaseRequisitionPrintView } from "@/components/e-approval/e-approval-purchase-requisition-print-view";
import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";
import {
  PO_PRINT_TEMPLATE_KIND,
  buildPurchaseOrderPrintTemplate,
  isPurchaseOrderFormMetadata,
  isPurchaseOrderPrintTemplate,
} from "@/modules/e-approval/purchase-order-template";
import {
  PR_PRINT_TEMPLATE_KIND,
  buildPurchaseRequisitionPrintTemplate,
  isPurchaseRequisitionFormMetadata,
  isPurchaseRequisitionPrintTemplate,
} from "@/modules/e-approval/purchase-requisition-template";

export type ProcurementPrintTemplateKind = typeof PO_PRINT_TEMPLATE_KIND | typeof PR_PRINT_TEMPLATE_KIND;

type PrintViewProps = { data: EApprovalPrintPayload; showApprovalFooter?: boolean };

export type PrintTemplateRegistryEntry = {
  kind: ProcurementPrintTemplateKind;
  label: string;
  buildDefaultTemplate: () => EApprovalPrintTemplate;
  isFormMetadata: (metadata: Record<string, unknown> | null | undefined) => boolean;
  isPrintTemplate: (template: Record<string, unknown> | null | undefined) => boolean;
  isPrintPayload: (data: EApprovalPrintPayload) => boolean;
  PrintView: ComponentType<PrintViewProps>;
};

export const PRINT_TEMPLATE_REGISTRY: PrintTemplateRegistryEntry[] = [
  {
    kind: PR_PRINT_TEMPLATE_KIND,
    label: "Purchase Requisition",
    buildDefaultTemplate: () => buildPurchaseRequisitionPrintTemplate() as EApprovalPrintTemplate,
    isFormMetadata: isPurchaseRequisitionFormMetadata,
    isPrintTemplate: isPurchaseRequisitionPrintTemplate,
    isPrintPayload: (data) =>
      data.print_template_kind === PR_PRINT_TEMPLATE_KIND ||
      isPurchaseRequisitionPrintTemplate(data.template) ||
      data.fields.some((field) => field.key === "estimated_total" && !data.fields.some((f) => f.key === "grand_total")),
    PrintView: EApprovalPurchaseRequisitionPrintView,
  },
  {
    kind: PO_PRINT_TEMPLATE_KIND,
    label: "Purchase Order",
    buildDefaultTemplate: () => buildPurchaseOrderPrintTemplate() as EApprovalPrintTemplate,
    isFormMetadata: isPurchaseOrderFormMetadata,
    isPrintTemplate: isPurchaseOrderPrintTemplate,
    isPrintPayload: (data) =>
      data.print_template_kind === PO_PRINT_TEMPLATE_KIND ||
      isPurchaseOrderPrintTemplate(data.template) ||
      data.fields.some((field) => field.key === "grand_total"),
    PrintView: EApprovalPurchaseOrderPrintView,
  },
];

export function resolvePrintTemplateEntry(
  data: EApprovalPrintPayload,
): PrintTemplateRegistryEntry | null {
  const kind = data.print_template_kind;
  if (kind) {
    const byKind = PRINT_TEMPLATE_REGISTRY.find((entry) => entry.kind === kind);
    if (byKind) {
      return byKind;
    }
  }

  return PRINT_TEMPLATE_REGISTRY.find((entry) => entry.isPrintPayload(data)) ?? null;
}

export function resolvePrintTemplateEntryForForm(
  metadata: Record<string, unknown> | null | undefined,
  fields: { name: string; type?: string }[],
): PrintTemplateRegistryEntry | null {
  return (
    PRINT_TEMPLATE_REGISTRY.find((entry) => entry.isFormMetadata(metadata)) ??
    PRINT_TEMPLATE_REGISTRY.find((entry) =>
      entry.isPrintPayload({
        print_template_kind: null,
        template: {},
        fields: fields.map((field) => ({ key: field.name, label: field.name, value: null })),
      } as EApprovalPrintPayload),
    ) ??
    null
  );
}

export function buildDefaultPrintTemplateForForm(
  metadata: Record<string, unknown> | null | undefined,
  fields: { name: string; type?: string }[],
): EApprovalPrintTemplate | null {
  return resolvePrintTemplateEntryForForm(metadata, fields)?.buildDefaultTemplate() ?? null;
}
