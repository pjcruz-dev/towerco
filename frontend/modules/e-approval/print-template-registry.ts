import type { ComponentType } from "react";

import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";

type PrintViewProps = { data: EApprovalPrintPayload; showApprovalFooter?: boolean };

export type PrintTemplateRegistryEntry = {
  kind: string;
  label: string;
  buildDefaultTemplate: () => EApprovalPrintTemplate;
  isFormMetadata: (metadata: Record<string, unknown> | null | undefined) => boolean;
  isPrintTemplate: (template: Record<string, unknown> | null | undefined) => boolean;
  isPrintPayload: (data: EApprovalPrintPayload) => boolean;
  PrintView: ComponentType<PrintViewProps>;
};

export const PRINT_TEMPLATE_REGISTRY: PrintTemplateRegistryEntry[] = [];

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
