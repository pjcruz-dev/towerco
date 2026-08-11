"use client";

import { EApprovalSubmissionFieldDisplay } from "@/components/e-approval/e-approval-submission-field-display";
import { getDuplicateApproverIds } from "@/modules/e-approval/display";
import {
  buildSubmissionFormContentGroups,
  type SubmissionFormAttachment,
  type SubmissionFormContentItem,
  type SubmissionFormFieldSnapshot,
  type SubmissionFormValueRow,
} from "@/modules/e-approval/submission-form-content";
import type { ComposeReviewTable } from "@/modules/e-approval/form-compose-review";
import { cn } from "@/lib/utils";
import { useMemo } from "react";

type Props = {
  values: SubmissionFormValueRow[];
  formFields?: SubmissionFormFieldSnapshot[];
  attachments?: SubmissionFormAttachment[];
  className?: string;
};

function ContentTable({ table }: { table: ComposeReviewTable }) {
  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table className="w-full min-w-[20rem] border-collapse text-left text-sm">
        <thead>
          <tr className="bg-slate-700 text-white dark:bg-slate-800">
            {table.headers.map((header) => (
              <th
                key={header}
                scope="col"
                className="px-3 py-2 text-xs font-medium whitespace-nowrap"
              >
                {header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {table.rows.map((row, rowIndex) => (
            <tr
              key={`content-row-${rowIndex}`}
              className={cn(
                "border-t border-border",
                rowIndex % 2 === 1 ? "bg-muted/20" : "bg-card",
              )}
            >
              {row.map((cell, cellIndex) => (
                <td
                  key={`content-cell-${rowIndex}-${cellIndex}`}
                  className={cn(
                    "px-3 py-2 align-top break-words",
                    cell === "—" && "text-muted-foreground",
                  )}
                >
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ContentItem({
  item,
  duplicateApproverIds,
}: {
  item: SubmissionFormContentItem;
  duplicateApproverIds: Set<string>;
}) {
  const label = item.value.label ?? item.value.field_name ?? "Field";

  return (
    <div className={cn(item.fullWidth && "md:col-span-2")}>
      <dt className="text-xs font-medium text-muted-foreground">{label}</dt>
      <dd className="mt-1">
        {item.table ? (
          <ContentTable table={item.table} />
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
}: Props) {
  const groups = useMemo(
    () => buildSubmissionFormContentGroups(values, formFields, attachments),
    [attachments, formFields, values],
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
              />
            ))}
          </dl>
        </section>
      ))}
    </div>
  );
}
