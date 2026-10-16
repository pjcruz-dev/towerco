"use client";

import { useState, type ReactNode } from "react";
import { Lock, Pencil } from "lucide-react";

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
  type EApprovalLayoutClusterNode,
  type EApprovalLayoutRowColumns,
} from "@/modules/e-approval/field-layout";
import { formatGridCurrencyDisplay } from "@/modules/e-approval/grid-row-formulas";
import type { EApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";
import type { EApprovalFieldListEntry } from "@/modules/e-approval/form-field-groups";

const EXPENSE_SUMMARY_RANK: Record<string, number> = {
  requested_amount: 0,
  total_reimbursement: 0,
  vat_total: 1,
  non_vatable_total: 2,
  vatable_net: 3,
};

function expenseSummaryEntries(node: EApprovalLayoutClusterNode): EApprovalFieldListEntry[] | null {
  if (node.kind === "row" && isExpenseSummaryRow(node)) {
    return node.entries;
  }
  if (node.kind === "field" && node.entry.field.name in EXPENSE_SUMMARY_RANK) {
    return [node.entry];
  }
  return null;
}

function followingExpenseSummary(nodes: EApprovalLayoutClusterNode[], from: number): EApprovalFieldListEntry[] {
  const collected: EApprovalFieldListEntry[] = [];
  for (let look = from + 1; look < nodes.length; look += 1) {
    const candidate = nodes[look];
    if (!candidate) {
      break;
    }
    const entries = expenseSummaryEntries(candidate);
    if (!entries) {
      break;
    }
    collected.push(...entries);
  }
  return collected;
}

function expenseSummarySkip(nodes: EApprovalLayoutClusterNode[], from: number): number {
  let skip = 0;
  for (let look = from + 1; look < nodes.length; look += 1) {
    const candidate = nodes[look];
    if (!candidate || !expenseSummaryEntries(candidate)) {
      break;
    }
    skip += 1;
  }
  return skip;
}

function isExpenseSummaryRow(node: EApprovalLayoutClusterNode): node is Extract<EApprovalLayoutClusterNode, { kind: "row" }> {
  return (
    node.kind === "row" &&
    node.entries.length > 0 &&
    node.entries.every((entry) => entry.field.name in EXPENSE_SUMMARY_RANK)
  );
}

function fieldIsRequired(field: EApprovalFormFieldInput): boolean {
  const validation = field.validation;
  if (!validation || typeof validation !== "object" || Array.isArray(validation)) {
    return false;
  }
  return validation.required === true;
}

type Props = {
  fields: EApprovalFormFieldInput[];
  /** Full form, including fields on other steps. Linked grids need this. */
  catalogFields?: EApprovalFormFieldInput[];
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
  catalogFields,
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
  const layoutFields = normalizeFormFieldLayouts(fields, layoutRows);
  const fieldCatalog = catalogFields && catalogFields.length > 0 ? catalogFields : layoutFields;
  const displayFields = visibleFormFields(layoutFields, values);
  const groups = buildFieldDisplayGroups(displayFields);

  const renderField = (
    field: EApprovalFormFieldInput,
    index: number,
    gridSummary?: {
      totals: Array<{ label: string; value: string; required?: boolean }>;
      errors: string[];
    },
  ) => {
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
          allFields={fieldCatalog}
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
          gridSummary={gridSummary}
        />
      </div>
    );
  };

  const renderCluster = (nodes: EApprovalLayoutClusterNode[], keyPrefix: string) => {
    const rendered: ReactNode[] = [];
    for (let ni = 0; ni < nodes.length; ni += 1) {
      const node = nodes[ni];
      if (!node) {
        continue;
      }
      const summary =
        node.kind === "field" && node.entry.field.type === "grid" && node.entry.field.name === "expense_lines"
          ? followingExpenseSummary(nodes, ni)
          : [];
      if (node.kind === "field" && summary.length > 0) {
        rendered.push(
          <div key={`${keyPrefix}-summary-${ni}`}>
            {renderField(node.entry.field, node.entry.index, {
              totals: [...summary]
                .sort(
                  (left, right) =>
                    (EXPENSE_SUMMARY_RANK[left.field.name] ?? 9) -
                    (EXPENSE_SUMMARY_RANK[right.field.name] ?? 9),
                )
                .map((entry) => ({
                  label: entry.field.label,
                  value: formatGridCurrencyDisplay(values[entry.field.name] ?? ""),
                  required: fieldIsRequired(entry.field),
                })),
              errors: summary
                .map((entry) => fieldErrors?.[entry.field.name])
                .filter((message): message is string => Boolean(message)),
            })}
          </div>,
        );
        ni += summary.length > 0 ? expenseSummarySkip(nodes, ni) : 0;
        continue;
      }
      rendered.push(renderClusterNode(node, ni, keyPrefix));
    }
    return rendered;
  };

  const renderClusterNode = (node: EApprovalLayoutClusterNode, ni: number, keyPrefix: string) => {
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
  };

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
