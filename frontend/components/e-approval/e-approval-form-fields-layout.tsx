"use client";

import { useState } from "react";
import { Lock, Pencil } from "lucide-react";

import { EApprovalProcurementFormLayout } from "@/components/e-approval/e-approval-procurement-form-layout";
import { EApprovalFieldRenderer } from "@/components/e-approval/e-approval-field-renderer";
import { parseBuilderLayoutRows } from "@/modules/e-approval/builder-layout-rows";
import { visibleFormFields } from "@/modules/e-approval/field-visibility";
import type { EApprovalCameraPhotoMetadata } from "@/modules/e-approval/field-camera-options";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import { buildFieldDisplayGroups, fieldInstanceKey } from "@/modules/e-approval/form-field-groups";
import {
  assignEntriesToRowSlots,
  clusterEntriesByLayoutRow,
  inferLayoutRowColumnCount,
  layoutRowComposeGridClass,
  layoutWidthTailwindClass,
  normalizeFormFieldLayouts,
  parseFieldLayout,
  type EApprovalLayoutRowColumns,
} from "@/modules/e-approval/field-layout";
import {
  ensureProcurementFieldLayouts,
  isProcurementDocumentForm,
} from "@/modules/e-approval/procurement-document-layout";
import type { EApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  fields: EApprovalFormFieldInput[];
  values: Record<string, string>;
  onChange: (fieldName: string, value: string) => void;
  onFileChange?: (fieldName: string, files: File[]) => void;
  onCameraChange?: (
    fieldName: string,
    files: File[],
    metadataByName: Record<string, EApprovalCameraPhotoMetadata>,
  ) => void;
  fileSelections?: Record<string, File[]>;
  cameraMetadataByField?: Record<string, Record<string, EApprovalCameraPhotoMetadata>>;
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
  formMetadata?: Record<string, unknown> | null;
  /** Fields pre-filled from registry — shown as locked with an unlock toggle. */
  prefillReadOnlyFields?: Set<string>;
  /** When true, locked fields stay read-only but omit per-field registry badges. */
  hidePrefillFieldBadges?: boolean;
};

export function EApprovalFormFieldsLayout({
  fields,
  values,
  onChange,
  approverOptions,
  approverOptionsLoading,
  onFileChange,
  onCameraChange,
  fileSelections = {},
  cameraMetadataByField = {},
  existingAttachmentsByField = {},
  onRemoveSavedAttachment,
  removingSavedAttachmentId = null,
  density = "comfortable",
  disabled,
  fieldErrors,
  fieldHelpOverrides,
  planFeaturesOverride,
  allowRemoteLookups = true,
  formMetadata = null,
  prefillReadOnlyFields,
  hidePrefillFieldBadges = false,
}: Props) {
  // Tracks which prefilled fields the user has explicitly unlocked for editing.
  const [unlockedFields, setUnlockedFields] = useState<Set<string>>(new Set());

  const unlock = (name: string) =>
    setUnlockedFields((prev) => new Set([...prev, name]));
  const layoutRows = parseBuilderLayoutRows(formMetadata);
  const layoutFields = normalizeFormFieldLayouts(
    ensureProcurementFieldLayouts(fields, formMetadata),
    layoutRows,
  );
  const displayFields = visibleFormFields(layoutFields, values);
  const groups = buildFieldDisplayGroups(displayFields);
  const useProcurementLayout = isProcurementDocumentForm(layoutFields, formMetadata);

  if (useProcurementLayout) {
    return (
      <EApprovalProcurementFormLayout
        groups={groups}
        fields={layoutFields}
        values={values}
        onChange={onChange}
        onFileChange={onFileChange}
        onCameraChange={onCameraChange}
        fileSelections={fileSelections}
        cameraMetadataByField={cameraMetadataByField}
        existingAttachmentsByField={existingAttachmentsByField}
        onRemoveSavedAttachment={onRemoveSavedAttachment}
        removingSavedAttachmentId={removingSavedAttachmentId}
        approverOptions={approverOptions}
        approverOptionsLoading={approverOptionsLoading}
        density={density}
        disabled={disabled}
        fieldErrors={fieldErrors}
        fieldHelpOverrides={fieldHelpOverrides}
        planFeaturesOverride={planFeaturesOverride}
        allowRemoteLookups={allowRemoteLookups}
        formMetadata={formMetadata}
      />
    );
  }

  const renderField = (field: EApprovalFormFieldInput, index: number) => {
    const message = fieldErrors?.[field.name];
    const isPrefilled =
      prefillReadOnlyFields?.has(field.name) && !unlockedFields.has(field.name);
    const isLocked = isPrefilled && field.type !== "section" && field.type !== "divider";

    return (
      <div
        id={`ea-field-${field.name}`}
        data-help={
          field.name === "title"
            ? "ea-compose-fields"
            : field.type === "file" || field.name === "attachments"
              ? "ea-compose-upload"
              : undefined
        }
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
        {isLocked ? (
          <div
            className={cn(
              "mb-1 flex items-center",
              hidePrefillFieldBadges ? "justify-end" : "justify-between",
            )}
          >
            {!hidePrefillFieldBadges ? (
              <span className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                <Lock className="h-2.5 w-2.5" />
                Pre-filled from registry
              </span>
            ) : null}
            <button
              type="button"
              onClick={() => unlock(field.name)}
              className="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] text-muted-foreground hover:text-foreground"
            >
              <Pencil className="h-2.5 w-2.5" />
              Edit
            </button>
          </div>
        ) : null}
        <EApprovalFieldRenderer
          key={fieldInstanceKey(field, index)}
          field={field}
          allFields={layoutFields}
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
          prefillLocked={isLocked}
          density={density}
          planFeaturesOverride={planFeaturesOverride}
          allowRemoteLookups={allowRemoteLookups}
          helpTextOverride={fieldHelpOverrides?.[field.name]}
          allValues={values}
        />
      </div>
    );
  };

  const renderCluster = (nodes: ReturnType<typeof clusterEntriesByLayoutRow>, keyPrefix: string) =>
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

      const scaffoldColumns = layoutRows.find((row) => row.id === node.rowId)?.columns;
      const columnCount: EApprovalLayoutRowColumns =
        scaffoldColumns ?? inferLayoutRowColumnCount(node.entries);
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

  return (
    <div className="space-y-8">
      {groups.map((group, gi) => (
        <div
          key={gi}
          id={`ea-section-${gi}`}
          className={cn("scroll-mt-28", group.header && "rounded-xl border border-border/80 bg-muted/10")}
        >
          {group.header ? (
            <div className="border-b border-border/60 px-4 py-3">
              <p className="text-sm font-medium text-foreground">{group.header.field.label}</p>
            </div>
          ) : null}
          <div className={cn("space-y-5", group.header ? "px-4 py-4" : "")}>
            {renderCluster(clusterEntriesByLayoutRow(group.items), `g${gi}`)}
          </div>
        </div>
      ))}
    </div>
  );
}
