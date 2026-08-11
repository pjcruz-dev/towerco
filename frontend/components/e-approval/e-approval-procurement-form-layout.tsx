"use client";

import { EApprovalFieldRenderer } from "@/components/e-approval/e-approval-field-renderer";
import {
  PO_TAX_SUMMARY_LEFT_FIELDS,
  PO_TAX_SUMMARY_RIGHT_FIELDS,
  procurementSectionKind,
} from "@/modules/e-approval/procurement-document-layout";
import type { EApprovalFieldListEntry } from "@/modules/e-approval/form-field-groups";
import { fieldInstanceKey } from "@/modules/e-approval/form-field-groups";
import {
  assignEntriesToRowSlots,
  clusterEntriesByLayoutRow,
  inferLayoutRowColumnCount,
  layoutRowComposeGridClass,
  layoutWidthTailwindClass,
  parseFieldLayout,
} from "@/modules/e-approval/field-layout";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import type { EApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type FieldRenderProps = {
  fields: EApprovalFormFieldInput[];
  values: Record<string, string>;
  onChange: (fieldName: string, value: string) => void;
  onFileChange?: (fieldName: string, files: File[]) => void;
  onCameraChange?: (
    fieldName: string,
    files: File[],
    metadataByName: Record<string, import("@/modules/e-approval/field-camera-options").EApprovalCameraPhotoMetadata>,
  ) => void;
  fileSelections?: Record<string, File[]>;
  cameraMetadataByField?: Record<
    string,
    Record<string, import("@/modules/e-approval/field-camera-options").EApprovalCameraPhotoMetadata>
  >;
  existingAttachmentsByField?: Record<string, EApprovalSavedAttachmentRef[]>;
  onRemoveSavedAttachment?: (attachmentId: string) => void | Promise<void>;
  removingSavedAttachmentId?: string | null;
  approverOptions: { id: string; label: string }[];
  approverOptionsLoading?: boolean;
  density?: "compact" | "comfortable";
  disabled?: boolean;
  fieldErrors?: Record<string, string>;
  fieldHelpOverrides?: Record<string, string>;
  planFeaturesOverride?: EApprovalPlanFeatures;
  allowRemoteLookups?: boolean;
};

type Props = FieldRenderProps & {
  groups: {
    header: EApprovalFieldListEntry | null;
    items: EApprovalFieldListEntry[];
  }[];
  formMetadata?: Record<string, unknown> | null;
};

function formatMoneyPreview(value: string): string {
  const numeric = Number(value.replace(/,/g, ""));
  if (!Number.isFinite(numeric)) {
    return value || "—";
  }
  return numeric.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function isReadOnlyField(field: EApprovalFormFieldInput): boolean {
  return Boolean(field.options?.read_only);
}

export function EApprovalProcurementFormLayout({
  groups,
  fields,
  values,
  onChange,
  onFileChange,
  onCameraChange,
  fileSelections = {},
  cameraMetadataByField = {},
  existingAttachmentsByField = {},
  onRemoveSavedAttachment,
  removingSavedAttachmentId = null,
  approverOptions,
  approverOptionsLoading,
  density = "comfortable",
  disabled,
  fieldErrors,
  fieldHelpOverrides,
  planFeaturesOverride,
  allowRemoteLookups = true,
  formMetadata = null,
}: Props) {
  const renderField = (field: EApprovalFormFieldInput, index: number, compact = false) => {
    const message = fieldErrors?.[field.name];
    return (
      <div
        id={`ea-field-${field.name}`}
        className={cn(
          "scroll-mt-24",
          message && "rounded-lg border border-destructive/25 bg-destructive/[0.03] px-3 py-2 -mx-1",
        )}
      >
        {message ? (
          <p className="mb-2 text-xs font-medium text-destructive" role="alert">
            {message}
          </p>
        ) : null}
        <EApprovalFieldRenderer
          key={fieldInstanceKey(field, index)}
          field={field}
          allFields={fields}
          value={values[field.name] ?? ""}
          onChange={(next) => onChange(field.name, next)}
          onFileChange={onFileChange ? (files) => onFileChange(field.name, files) : undefined}
          onCameraChange={
            onCameraChange
              ? (files, metadataByName) => onCameraChange(field.name, files, metadataByName)
              : undefined
          }
          fileSelection={fileSelections[field.name] ?? []}
          cameraMetadataByName={cameraMetadataByField[field.name] ?? {}}
          existingFileAttachments={existingAttachmentsByField[field.name] ?? []}
          onRemoveSavedAttachment={onRemoveSavedAttachment}
          removingSavedAttachmentId={removingSavedAttachmentId}
          approverOptions={approverOptions}
          approverOptionsLoading={approverOptionsLoading}
          disabled={disabled}
          density={compact ? "compact" : density}
          planFeaturesOverride={planFeaturesOverride}
          allowRemoteLookups={allowRemoteLookups}
          helpTextOverride={fieldHelpOverrides?.[field.name]}
          allValues={values}
        />
      </div>
    );
  };

  const renderRowCluster = (nodes: ReturnType<typeof clusterEntriesByLayoutRow>, keyPrefix: string) =>
    nodes.map((node, ni) => {
      if (node.kind === "field") {
        const field = node.entry.field;
        if (field.type === "section" || field.type === "divider") {
          return <div key={`${keyPrefix}-f-${ni}`}>{renderField(field, node.entry.index)}</div>;
        }

        const width = field.type === "grid" ? "full" : parseFieldLayout(field).width;
        return (
          <div key={`${keyPrefix}-f-${ni}`} className="grid grid-cols-12 gap-4">
            <div className={layoutWidthTailwindClass(width)}>{renderField(field, node.entry.index)}</div>
          </div>
        );
      }

      const columnCount = inferLayoutRowColumnCount(node.entries);
      const slots = assignEntriesToRowSlots(node.entries, columnCount);

      return (
        <div key={`${keyPrefix}-r-${node.rowId}`} className={layoutRowComposeGridClass(columnCount)}>
          {slots.map((slotEntries, slot) => (
            <div key={`${keyPrefix}-r-${node.rowId}-s${slot}`} className="min-w-0 space-y-5">
              {slotEntries.map((entry) => (
                <div key={`${keyPrefix}-col-${entry.index}`}>{renderField(entry.field, entry.index)}</div>
              ))}
            </div>
          ))}
        </div>
      );
    });

  const renderTaxSummary = (items: EApprovalFieldListEntry[]) => {
    const byName = new Map(items.map((entry) => [entry.field.name, entry]));
    const left = PO_TAX_SUMMARY_LEFT_FIELDS.map((name) => byName.get(name)).filter(Boolean) as EApprovalFieldListEntry[];
    const right = PO_TAX_SUMMARY_RIGHT_FIELDS.map((name) => byName.get(name)).filter(
      Boolean,
    ) as EApprovalFieldListEntry[];

    return (
      <div className="grid gap-4 lg:grid-cols-2">
        <div className="overflow-hidden rounded-lg border border-border">
          <div className="border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Tax summary
          </div>
          <div className="divide-y divide-border">
            {left.map((entry) => {
              const readOnly = isReadOnlyField(entry.field);
              return (
                <div key={entry.field.name} className="px-3 py-2">
                  {readOnly ? (
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className="text-muted-foreground">{entry.field.label}</span>
                      <span className="font-medium tabular-nums text-foreground">
                        {formatMoneyPreview(values[entry.field.name] ?? "")}
                      </span>
                    </div>
                  ) : (
                    renderField(entry.field, entry.index, true)
                  )}
                </div>
              );
            })}
          </div>
        </div>

        <div className="flex flex-col justify-end">
          <div className="ml-auto w-full max-w-md overflow-hidden rounded-lg border border-border bg-muted/20">
            {right.map((entry) => {
              const isTotal = entry.field.name === "grand_total";
              const readOnly = isReadOnlyField(entry.field);
              return (
                <div
                  key={entry.field.name}
                  className={cn("border-b border-border px-3 py-2 last:border-b-0", isTotal && "bg-muted/60")}
                >
                  {readOnly ? (
                    <div className="flex items-center justify-between gap-3 text-sm">
                      <span className={cn("text-muted-foreground", isTotal && "font-semibold text-foreground")}>
                        {entry.field.label}
                      </span>
                      <span className={cn("font-medium tabular-nums text-foreground", isTotal && "text-base font-semibold")}>
                        {formatMoneyPreview(values[entry.field.name] ?? "")}
                      </span>
                    </div>
                  ) : (
                    renderField(entry.field, entry.index, true)
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </div>
    );
  };

  const renderTotalsHighlight = (items: EApprovalFieldListEntry[]) => {
    const totalField = items.find((entry) => entry.field.name === "estimated_total");
    if (!totalField) {
      return renderRowCluster(clusterEntriesByLayoutRow(items), "totals");
    }

    return (
      <div className="flex justify-end">
        <div className="w-full max-w-md overflow-hidden rounded-lg border border-border bg-muted/20">
          <div className="border-b border-border bg-muted/50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            Totals
          </div>
          <div className="px-3 py-3">
            {renderField(totalField.field, totalField.index, true)}
          </div>
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-8">
      {groups.map((group, gi) => {
        const sectionKind = procurementSectionKind(group.header?.field.name, formMetadata);

        return (
          <div
            key={gi}
            id={`ea-section-${gi}`}
            className={cn("scroll-mt-28", group.header && "rounded-xl border border-border/80 bg-card shadow-sm")}
          >
            {group.header ? (
              <div className="border-b border-border/60 px-4 py-3">
                <p className="text-sm font-medium text-foreground">{group.header.field.label}</p>
              </div>
            ) : null}
            <div className={cn("space-y-5", group.header ? "px-4 py-4" : "")}>
              {sectionKind === "tax_summary" ? (
                renderTaxSummary(group.items)
              ) : sectionKind === "totals" ? (
                renderTotalsHighlight(group.items)
              ) : sectionKind === "justification" ? (
                renderRowCluster(clusterEntriesByLayoutRow(group.items), `g${gi}`)
              ) : (
                renderRowCluster(clusterEntriesByLayoutRow(group.items), `g${gi}`)
              )}
            </div>
          </div>
        );
      })}
    </div>
  );
}
