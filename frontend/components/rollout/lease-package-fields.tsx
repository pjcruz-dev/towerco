"use client";

import Link from "next/link";

import { AcronymText } from "@/components/help/acronym-text";
import { FileUploadField } from "@/components/forms/file-upload-field";
import { FormInput } from "@/components/forms/form-input";
import type { RolloutLeasePackage, RolloutMediaLink } from "@/modules/rollout/types";

const lessorIdTypes = [
  { value: "gov_id", label: "Government ID" },
  { value: "corporate", label: "Corporate entity" },
  { value: "hoa", label: "HOA / association" },
  { value: "other", label: "Other" },
];

type Props = {
  rolloutId: string;
  value: RolloutLeasePackage;
  onChange: (next: RolloutLeasePackage) => void;
  disabled?: boolean;
};

export function LeasePackageFields({ rolloutId, value, onChange, disabled }: Props) {
  const documents = value.documents ?? [];
  const binderHref = documents.find((doc) => doc.document_href)?.document_href;
  const binderLinked = documents.some((doc) => Boolean(doc.document_id || doc.document_href));

  return (
    <div className="space-y-3 rounded-lg border border-border bg-muted/20 p-4">
      <div>
        <h3 className="text-sm font-medium text-foreground">Lease package</h3>
        <p className="mt-1 text-xs text-muted-foreground">
          <AcronymText text="Structured lease documentation for SAQ audit trail." />
        </p>
        <p className="mt-2 text-xs text-muted-foreground">
          Selecting a site candidate (or using Import lease package on the site binder) copies these
          files into the Documents binder. Candidate photos and other SAQ uploads stay on the
          rollout — they do not satisfy gate binder checklist folders.
        </p>
        {binderLinked && binderHref ? (
          <p className="mt-1 text-xs text-emerald-700 dark:text-emerald-400">
            At least one lease file is linked in the site binder.{" "}
            <Link href={binderHref} className="font-medium underline underline-offset-2">
              Open site
            </Link>
          </p>
        ) : null}
      </div>

      <label className="space-y-1.5 text-sm">
        <span className="font-medium">Lessor ID type</span>
        <select
          className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
          value={value.lessor_id_type ?? ""}
          disabled={disabled}
          onChange={(e) => onChange({ ...value, lessor_id_type: e.target.value || null })}
        >
          <option value="">Select type</option>
          {lessorIdTypes.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>

      <FormInput
        label="Lease term (months)"
        type="number"
        min={1}
        value={value.lease_term_months != null ? String(value.lease_term_months) : ""}
        disabled={disabled}
        onChange={(e) =>
          onChange({
            ...value,
            lease_term_months: e.target.value ? Number(e.target.value) : null,
          })
        }
      />

      <label className="space-y-1.5 text-sm">
        <span className="font-medium">Notes</span>
        <textarea
          className="min-h-[72px] w-full rounded-md border bg-background px-3 py-2 text-sm"
          value={value.notes ?? ""}
          disabled={disabled}
          onChange={(e) => onChange({ ...value, notes: e.target.value || null })}
        />
      </label>

      <FileUploadField
        rolloutId={rolloutId}
        context="lease_document"
        label="Lease documents"
        accept="application/pdf,image/*"
        value={documents}
        disabled={disabled}
        onChange={(next: RolloutMediaLink[]) => onChange({ ...value, documents: next })}
      />
    </div>
  );
}

export const emptyLeasePackage = (): RolloutLeasePackage => ({
  lessor_id_type: null,
  lease_term_months: null,
  notes: null,
  documents: [],
});
