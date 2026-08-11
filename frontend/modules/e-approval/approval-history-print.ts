import type { EApprovalPrintPayload } from "@/modules/e-approval/types";
import type { ApprovalHistorySlot, EApprovalPrintTemplate } from "@/modules/e-approval/print-template-types";
import { printFieldValueMap } from "@/modules/e-approval/print-utils";

export function resolvePrintTemplate(data: EApprovalPrintPayload): EApprovalPrintTemplate {
  return (data.template ?? {}) as EApprovalPrintTemplate;
}

export function shouldShowRequestorSignature(template: EApprovalPrintTemplate | null | undefined): boolean {
  if (template?.footer?.showRequestorSignature === false) {
    return false;
  }
  return template?.footer?.showRequestorSignature === true;
}

export function shouldShowApprovalHistory(template: EApprovalPrintTemplate | null | undefined): boolean {
  return template?.footer?.showApprovalHistory !== false;
}

/** Shared approval + requestor signature slots for browser print and pdf-lib footer. */
export function buildApprovalHistorySlots(
  data: EApprovalPrintPayload,
  template?: EApprovalPrintTemplate | null,
): ApprovalHistorySlot[] {
  const resolved = template ?? resolvePrintTemplate(data);
  const values = printFieldValueMap(data);
  const slots: ApprovalHistorySlot[] = [];
  const signatureFields = resolved.blocks?.signatures ?? [];

  if (shouldShowRequestorSignature(resolved)) {
    const requestorSig = data.requestor_signature?.trim() ?? "";
    if (requestorSig) {
      slots.push({
        key: "requestor",
        label: data.requestor?.trim() || "Requestor",
        subtitle: "Requestor",
        signature: requestorSig,
        kind: "requestor",
      });
    }
  }

  for (const fieldKey of signatureFields) {
    const labelField = values[fieldKey]?.trim();
    if (!labelField && fieldKey !== "prepared_by" && fieldKey !== "requested_by") {
      continue;
    }

    const label =
      fieldKey === "prepared_by"
        ? labelField || data.requestor?.trim() || "Prepared by"
        : fieldKey === "requested_by"
          ? labelField || data.requestor?.trim() || "Requested by"
          : labelField || fieldKey;

    if (fieldKey === "requested_by" && shouldShowRequestorSignature(resolved) && data.requestor_signature?.trim()) {
      continue;
    }

    slots.push({
      key: `field-${fieldKey}`,
      label,
      subtitle: fieldKey === "prepared_by" ? "Prepared by" : fieldKey === "requested_by" ? "Requested by" : fieldKey,
      signature:
        fieldKey === "prepared_by" || fieldKey === "requested_by"
          ? data.requestor_signature?.trim() || null
          : null,
      kind: fieldKey === "prepared_by" || fieldKey === "requested_by" ? "prepared_by" : "approver",
    });
  }

  data.approvals
    .filter((row) => row.status.toLowerCase() === "approved")
    .forEach((row, index) => {
      const signature = row.signature?.trim() ?? "";
      if (!signature) {
        return;
      }

      slots.push({
        key: `approver-${row.step ?? index}`,
        label: row.approver ?? `Approver (Step ${row.step ?? "—"})`,
        subtitle: `Approved · ${row.acted_at ?? "—"}`,
        signature,
        kind: "approver",
      });
    });

  return slots;
}

/** Rows consumed by pdf-lib footer (signature required). */
export function buildPdfFooterRows(data: EApprovalPrintPayload): {
  label: string;
  signature: string;
  status: string;
  actedAt: string;
}[] {
  const template = resolvePrintTemplate(data);

  return buildApprovalHistorySlots(data, template)
    .filter((slot) => Boolean(slot.signature?.trim()))
    .map((slot) => ({
      label:
        slot.kind === "requestor"
          ? `Requestor · ${slot.label}`
          : slot.subtitle?.startsWith("Approved")
            ? `${slot.label}`
            : `${slot.subtitle ?? slot.label} · ${slot.label}`,
      signature: slot.signature!.trim(),
      status: slot.kind === "approver" ? "APPROVED" : slot.subtitle?.toUpperCase() ?? "SIGNED",
      actedAt: slot.subtitle?.startsWith("Approved") ? slot.subtitle.replace(/^Approved ·\s*/, "") : "",
    }));
}
