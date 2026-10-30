"use client";

import { EApprovalLineItemsTable } from "@/components/e-approval/e-approval-compose-review-summary";
import { EApprovalSubmissionFieldDisplay } from "@/components/e-approval/e-approval-submission-field-display";
import { getDuplicateApproverIds } from "@/modules/e-approval/display";
import {
  buildSubmissionFormContentGroups,
  type SubmissionFormAttachment,
  type SubmissionFormContentItem,
  type SubmissionFormFieldSnapshot,
  type SubmissionFormValueRow,
} from "@/modules/e-approval/submission-form-content";
import { cn } from "@/lib/utils";
import { useMemo, type ReactNode } from "react";

type Props = {
  values: SubmissionFormValueRow[];
  formFields?: SubmissionFormFieldSnapshot[];
  attachments?: SubmissionFormAttachment[];
  className?: string;
  /** File and camera fields stay in place. Return the review for that field, or null to show the stored name. */
  renderFileReview?: (fieldName: string) => ReactNode;
};

function ContentItem({
  item,
  duplicateApproverIds,
  renderFileReview,
}: {
  item: SubmissionFormContentItem;
  duplicateApproverIds: Set<string>;
  renderFileReview?: (fieldName: string) => ReactNode;
}) {
  const label = item.value.label ?? item.value.field_name ?? "Field";
  const fieldName = item.value.field_name?.trim() || item.formField?.name || "";
  const fileReview = item.fileReview && fieldName && renderFileReview ? renderFileReview(fieldName) : null;

  return (
    <div className={cn((item.fullWidth || fileReview) && "md:col-span-2")}>
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="mt-1">
        {fileReview ? (
          fileReview
        ) : item.table ? (
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
        )}
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
}: Props) {
  const groups = useMemo(
    () =>
      buildSubmissionFormContentGroups(values, formFields, attachments, {
        keepFileFields: Boolean(renderFileReview),
      }),
    [attachments, formFields, renderFileReview, values],
  );
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
            {group.items.map((item) => (
              <ContentItem
                key={item.key}
                item={item}
                duplicateApproverIds={duplicateApproverIds}
                renderFileReview={renderFileReview}
              />
            ))}
          </dl>
        </section>
      ))}
    </div>
  );
}
