"use client";

import { EApprovalLineItemsTable } from "@/components/e-approval/e-approval-compose-review-summary";
import { EApprovalRoleFieldFrame, useEApprovalSubmissionAccess } from "@/components/e-approval/e-approval-role-field-access";
import { EApprovalSubmissionFieldDisplay } from "@/components/e-approval/e-approval-submission-field-display";
import { getDuplicateApproverIds } from "@/modules/e-approval/display";
import {
  buildSubmissionFormContentGroups,
  type SubmissionFormAttachment,
  type SubmissionFormContentItem,
  type SubmissionFormFieldSnapshot,
  type SubmissionFormValueRow,
} from "@/modules/e-approval/submission-form-content";
import { formatComposeReviewValue } from "@/modules/e-approval/form-compose-review";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";
import { useMemo, type ReactNode } from "react";

type Props = {
  values: SubmissionFormValueRow[];
  formFields?: SubmissionFormFieldSnapshot[];
  attachments?: SubmissionFormAttachment[];
  /** Values stored when the last revision was requested. */
  revisionBaseline?: Record<string, string> | null;
  className?: string;
  /** File and camera fields stay in place. Return the review for that field, or null to show the stored name. */
  renderFileReview?: (fieldName: string) => ReactNode;
  /** Wrap a value so a mark can sit on it. File reviews are left as they are. */
  wrapValue?: (fieldName: string, node: ReactNode) => ReactNode;
};

function ContentItem({
  item,
  duplicateApproverIds,
  renderFileReview,
  wrapValue,
  revisionBaseline,
}: {
  item: SubmissionFormContentItem;
  duplicateApproverIds: Set<string>;
  renderFileReview?: (fieldName: string) => ReactNode;
  wrapValue?: (fieldName: string, node: ReactNode) => ReactNode;
  revisionBaseline?: Record<string, string> | null;
}) {
  const label = item.value.label ?? item.value.field_name ?? "Field";
  const fieldName = item.value.field_name?.trim() || item.formField?.name || "";
  const fileReview = item.fileReview && fieldName && renderFileReview ? renderFileReview(fieldName) : null;
  const previousRaw = fieldName && revisionBaseline ? revisionBaseline[fieldName] : undefined;
  const currentRaw = item.value.value ?? "";
  const changed = previousRaw !== undefined && previousRaw !== currentRaw;
  const previousText =
    changed && item.formField
      ? formatComposeReviewValue(item.formField as EApprovalFormFieldInput, previousRaw).trim() || "Empty"
      : changed
        ? previousRaw.trim() || "Empty"
        : "";

  return (
    <div className={cn((item.fullWidth || fileReview) && "md:col-span-2")}>
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className={cn("mt-1", changed && "rounded-md bg-amber-500/10 px-2 py-1.5 ring-1 ring-amber-500/40")}>
        {fileReview
          ? fileReview
          : (() => {
              const value = item.table ? (
                <EApprovalLineItemsTable
                  table={item.table}
                  totals={item.totals}
                  printLayout={item.formField?.name === "expense_lines" || item.value.field_name === "expense_lines"}
                />
              ) : (
                <EApprovalSubmissionFieldDisplay
                  field={item.value}
                  duplicateApproverIds={duplicateApproverIds}
                />
              );
              return wrapValue && fieldName ? wrapValue(fieldName, value) : value;
            })()}
        {changed ? (
          <p className="mt-1.5 text-xs text-muted-foreground">
            <span className="font-medium text-amber-700 dark:text-amber-300">Changed.</span> Previously: {previousText}
          </p>
        ) : null}
      </dd>
    </div>
  );
}

export function EApprovalSubmissionFormContent({
  values,
  formFields,
  attachments = [],
  className,
  renderFileReview,
  wrapValue,
  revisionBaseline,
}: Props) {
  const groups = useMemo(
    () =>
      buildSubmissionFormContentGroups(values, formFields, attachments, {
        keepFileFields: Boolean(renderFileReview),
      }),
    [attachments, formFields, renderFileReview, values],
  );
  const access = useEApprovalSubmissionAccess();
  const roleColumns = useMemo(() => {
    const fromFields = (formFields ?? []).map((field) => ({
      id: field.name,
      label: field.label || field.name,
    }));
    const fromValues = values.map((row) => ({
      id: row.field_name || row.field_id,
      label: row.label || row.field_name || "Field",
    }));
    const seen = new Set<string>();
    return [...fromFields, ...fromValues].filter((field): field is { id: string; label: string } => {
      if (!field.id || seen.has(field.id)) return false;
      seen.add(field.id);
      return true;
    });
  }, [formFields, values]);
  const duplicateApproverIds = useMemo(() => getDuplicateApproverIds(values), [values]);

  if (groups.length === 0) {
    return <p className={cn("mt-4 text-sm text-muted-foreground", className)}>No field values recorded.</p>;
  }

  return (
    <div className={cn("mt-4 space-y-5", className)}>
      {groups.map((group) => (
        <section key={group.id} className="space-y-3">
          {group.title ? (
            <h3 className="border-b border-border pb-1.5 text-sm font-medium text-foreground">
              {group.title}
            </h3>
          ) : null}
          <dl className="grid gap-3 md:grid-cols-2">
            {group.items.map((item) => {
              const fieldName = item.value.field_name?.trim() || item.formField?.name || "";
              if (fieldName && !access.visible(fieldName, "record")) return null;
              const label = item.value.label ?? item.value.field_name ?? "Field";
              const spansRow = item.fullWidth || item.fileReview;
              return (
                <EApprovalRoleFieldFrame
                  key={item.key}
                  fieldName={fieldName || item.key}
                  fieldLabel={label}
                  columns={roleColumns}
                  className={spansRow ? "md:col-span-2" : undefined}
                >
                  <ContentItem
                    item={item}
                    duplicateApproverIds={duplicateApproverIds}
                    renderFileReview={renderFileReview}
                    wrapValue={wrapValue}
                    revisionBaseline={revisionBaseline}
                  />
                </EApprovalRoleFieldFrame>
              );
            })}
          </dl>
        </section>
      ))}
    </div>
  );
}
