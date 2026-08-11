"use client";

import { EApprovalCameraField } from "@/components/e-approval/e-approval-camera-field";
import { EApprovalChecklistMatrixField } from "@/components/e-approval/e-approval-checklist-matrix-field";
import { EApprovalProcurementLinkField } from "@/components/e-approval/e-approval-procurement-link-field";
import { Checkbox } from "@/components/ui/checkbox";
import { DatePicker } from "@/components/ui/date-picker";
import { DateRangePicker } from "@/components/ui/date-range-picker";
import { CurrencyInput } from "@/components/ui/currency-input";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { formatCurrencyGrouping } from "@/lib/format-currency-input";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { SelectField } from "@/components/ui/select-field";
import { Textarea } from "@/components/ui/textarea";
import { EApprovalFileField } from "@/components/e-approval/e-approval-file-field";
import { EApprovalGridField } from "@/components/e-approval/e-approval-grid-field";
import { EApprovalLocationField } from "@/components/e-approval/e-approval-location-field";
import { EApprovalMatrixField } from "@/components/e-approval/e-approval-matrix-field";
import { EApprovalSizeMatrixField } from "@/components/e-approval/e-approval-size-matrix-field";
import { EApprovalRatingField } from "@/components/e-approval/e-approval-rating-field";
import { EApprovalSignaturePad } from "@/components/e-approval/e-approval-signature-pad";
import { EApprovalTagsField } from "@/components/e-approval/e-approval-tags-field";
import { useEApprovalPlanFeatures, type EApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import { useEApprovalFieldChoices } from "@/hooks/use-e-approval-field-choices";
import { cn } from "@/lib/utils";
import {
  getCheckboxCompanionValue,
  isCheckboxMulti,
  isCheckboxTruthy,
  parseCheckboxValues,
  setCheckboxCompanionValue,
  toggleCheckboxValue,
} from "@/modules/e-approval/field-checkbox";
import {
  parseCompanionSizeValue,
  serializeCompanionSizeValue,
} from "@/modules/e-approval/field-companion-size";
import { formatInstructionBodyForDisplay, parseInstructionBody } from "@/modules/e-approval/field-instruction";
import { parseSelectChoices, resolveFieldDisplayLabel } from "@/modules/e-approval/field-options";
import { isProcurementLinkField } from "@/modules/e-approval/procurement-link-fields";
import { computedFieldHelpText, isFieldComputedReadOnly } from "@/modules/e-approval/field-computed";
import { fieldHelpText, fieldMaxLength, fieldPlaceholder } from "@/modules/e-approval/field-validation";
import {
  parseDateRangeValue,
  serializeDateRangeValue,
} from "@/modules/e-approval/field-type-options";
import { resolveApproverFieldValue } from "@/modules/e-approval/approver-field-support";
import {
  parseApproverListValue,
  toggleApproverListId,
} from "@/modules/e-approval/approver-list-field";
import type { EApprovalCameraPhotoMetadata } from "@/modules/e-approval/field-camera-options";
import type { EApprovalSavedAttachmentRef } from "@/modules/e-approval/draft-attachments";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  onFileChange?: (files: File[]) => void;
  onCameraChange?: (files: File[], metadataByName: Record<string, EApprovalCameraPhotoMetadata>) => void;
  /** Selected files for `file` / `camera` fields (held outside string values). */
  fileSelection?: File[];
  cameraMetadataByName?: Record<string, EApprovalCameraPhotoMetadata>;
  existingFileAttachments?: EApprovalSavedAttachmentRef[];
  onRemoveSavedAttachment?: (attachmentId: string) => void | Promise<void>;
  removingSavedAttachmentId?: string | null;
  approverOptions?: { id: string; label: string }[];
  approverOptionsLoading?: boolean;
  disabled?: boolean;
  /** Registry pre-fill lock — read-only styling without disabled wash-out. */
  prefillLocked?: boolean;
  /** Use comfortable spacing on requestor submit forms (wider inputs, grid table). */
  density?: "compact" | "comfortable";
  /** When set (e.g. public form), skips authenticated metadata fetch for plan tier. */
  planFeaturesOverride?: EApprovalPlanFeatures;
  /**
   * When false (public / unauthenticated fill), do not call authenticated master-data or
   * procurement entity APIs — use options embedded in the form payload.
   */
  allowRemoteLookups?: boolean;
  helpTextOverride?: string;
  /** All form fields — required for computed/read-only currency fields. */
  allFields?: EApprovalFormFieldInput[];
  allValues?: Record<string, string>;
};

function SelectFieldControl({
  field,
  value,
  onChange,
  disabled,
  variant,
  allowRemoteLookups = true,
}: {
  field: EApprovalFormFieldInput;
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  variant: "select" | "radio" | "checkbox";
  allowRemoteLookups?: boolean;
}) {
  const { choices, isLoading, isError } = useEApprovalFieldChoices(
    field,
    !disabled,
    allowRemoteLookups,
  );
  const emptyLabel = fieldPlaceholder(field) ?? (isLoading ? "Loading options…" : "Select…");
  const selectedValues = variant === "checkbox" ? new Set(parseCheckboxValues(value)) : null;

  if (variant === "select") {
    return (
      <>
        <SelectField
          disabled={disabled || isLoading}
          value={value}
          onChange={onChange}
          placeholder={emptyLabel}
          emptyLabel={emptyLabel}
          options={choices.map((c) => ({
            value: c.value,
            label: c.subtitle ? `${c.label} — ${c.subtitle}` : c.label,
          }))}
        />
        {isError ? (
          <p className="text-xs text-destructive">Could not load options for this field.</p>
        ) : !isLoading && choices.length === 0 ? (
          <p className="text-xs text-muted-foreground">No options configured for this field.</p>
        ) : null}
      </>
    );
  }

  if (variant === "checkbox") {
    const staticByValue = new Map(parseSelectChoices(field).map((choice) => [choice.value, choice]));
    const hasCompanionInputs = choices.some((c) => {
      const inputs = staticByValue.get(c.value)?.inputs ?? c.inputs ?? [];
      return inputs.length > 0;
    });
    const inlineChoices = !hasCompanionInputs && choices.length > 0 && choices.length <= 4;

    return (
      <div
        className={
          inlineChoices
            ? "flex flex-wrap gap-x-4 gap-y-2 text-sm"
            : "flex flex-col gap-2.5 text-sm"
        }
      >
        {choices.map((c) => {
          const checked = selectedValues?.has(c.value) ?? false;
          const inputs = staticByValue.get(c.value)?.inputs ?? c.inputs ?? [];

          if (!hasCompanionInputs) {
            return (
              <label key={c.value} className="flex min-w-0 cursor-pointer items-start gap-2">
                <Checkbox
                  disabled={disabled || isLoading}
                  checked={checked}
                  onCheckedChange={(next) => onChange(toggleCheckboxValue(value, c.value, next === true))}
                  className="mt-0.5"
                />
                <span className="min-w-0">
                  {c.label}
                  {c.subtitle ? (
                    <span className="mt-0.5 block text-xs text-muted-foreground">{c.subtitle}</span>
                  ) : null}
                  {c.help ? (
                    <span className="mt-1 block whitespace-pre-wrap text-xs text-muted-foreground">{c.help}</span>
                  ) : null}
                </span>
              </label>
            );
          }

          const textInputs = inputs.filter((input) => input.type === "text");
          const numberInputs = inputs.filter((input) => input.type === "number");
          const sizeInputs = inputs.filter((input) => input.type === "size");
          // Keep size/NA in a stable column; put Specify on the next line when both exist.
          const deferTextBelow = sizeInputs.length > 0 && textInputs.length > 0;

          return (
            <div key={c.value} className="space-y-1">
              <div className="flex flex-wrap items-start gap-x-3 gap-y-1.5">
                <Checkbox
                  id={`ea-cb-${field.name}-${c.value}`}
                  disabled={disabled || isLoading}
                  checked={checked}
                  onCheckedChange={(next) => onChange(toggleCheckboxValue(value, c.value, next === true))}
                  className="mt-1"
                />
                <label
                  htmlFor={`ea-cb-${field.name}-${c.value}`}
                  className="w-[17rem] shrink-0 cursor-pointer leading-snug text-foreground"
                >
                  {c.label}
                  {c.subtitle ? (
                    <span className="mt-0.5 block text-xs text-muted-foreground">{c.subtitle}</span>
                  ) : null}
                </label>
                {numberInputs.length > 0 || sizeInputs.length > 0 || (!deferTextBelow && textInputs.length > 0) ? (
                  <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    {!deferTextBelow
                      ? textInputs.map((input) => (
                          <span key={`${c.value}-${input.key}`} className="inline-flex items-center gap-1.5">
                            {input.placeholder || input.suffix ? (
                              <span className="whitespace-nowrap text-xs text-muted-foreground">
                                {input.placeholder ?? input.suffix}:
                              </span>
                            ) : null}
                            <Input
                              type="text"
                              disabled={disabled || isLoading || !checked}
                              value={getCheckboxCompanionValue(value, c.value, input.key)}
                              onChange={(e) =>
                                onChange(setCheckboxCompanionValue(value, c.value, input.key, e.target.value))
                              }
                              className="h-8 w-36 max-w-full"
                              aria-label={`${c.label} ${input.placeholder ?? input.suffix ?? input.key}`}
                            />
                          </span>
                        ))
                      : null}
                    {numberInputs.map((input) => (
                      <span key={`${c.value}-${input.key}`} className="inline-flex items-center gap-1.5">
                        {input.placeholder ? (
                          <span className="whitespace-nowrap text-xs text-muted-foreground">
                            {input.placeholder}
                          </span>
                        ) : null}
                        <Input
                          type="number"
                          disabled={disabled || isLoading || !checked}
                          value={getCheckboxCompanionValue(value, c.value, input.key)}
                          onChange={(e) =>
                            onChange(setCheckboxCompanionValue(value, c.value, input.key, e.target.value))
                          }
                          placeholder={input.placeholder ?? ""}
                          className="h-8 w-16"
                          inputMode="decimal"
                          aria-label={`${c.label} ${input.placeholder ?? input.suffix ?? input.key}`}
                        />
                        {input.suffix ? (
                          <span className="whitespace-nowrap text-xs text-muted-foreground">{input.suffix}</span>
                        ) : null}
                      </span>
                    ))}
                    {sizeInputs.map((input) => {
                      const sizeRaw = getCheckboxCompanionValue(value, c.value, input.key);
                      const size = parseCompanionSizeValue(sizeRaw);
                      const na = size.na === true;
                      const sizeDisabled = disabled || isLoading || !checked || na;

                      return (
                        <span
                          key={`${c.value}-${input.key}`}
                          className="inline-flex items-center gap-1.5 whitespace-nowrap"
                        >
                          <span className="text-xs text-muted-foreground">size :</span>
                          <Input
                            type="number"
                            inputMode="decimal"
                            disabled={sizeDisabled}
                            value={na ? "" : (size.w ?? "")}
                            onChange={(e) =>
                              onChange(
                                setCheckboxCompanionValue(
                                  value,
                                  c.value,
                                  input.key,
                                  serializeCompanionSizeValue({ w: e.target.value, h: size.h, na: false }),
                                ),
                              )
                            }
                            className="h-8 w-16"
                            aria-label={`${c.label} width`}
                          />
                          <span className="text-xs text-muted-foreground">x</span>
                          <Input
                            type="number"
                            inputMode="decimal"
                            disabled={sizeDisabled}
                            value={na ? "" : (size.h ?? "")}
                            onChange={(e) =>
                              onChange(
                                setCheckboxCompanionValue(
                                  value,
                                  c.value,
                                  input.key,
                                  serializeCompanionSizeValue({ w: size.w, h: e.target.value, na: false }),
                                ),
                              )
                            }
                            className="h-8 w-16"
                            aria-label={`${c.label} height`}
                          />
                          <label className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Checkbox
                              disabled={disabled || isLoading || !checked}
                              checked={na}
                              onCheckedChange={(next) =>
                                onChange(
                                  setCheckboxCompanionValue(
                                    value,
                                    c.value,
                                    input.key,
                                    serializeCompanionSizeValue(
                                      next === true ? { na: true } : { w: "", h: "" },
                                    ),
                                  ),
                                )
                              }
                              className="size-3.5"
                            />
                            NA
                          </label>
                        </span>
                      );
                    })}
                  </div>
                ) : null}
              </div>
              {deferTextBelow ? (
                <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1 pl-[calc(1rem+0.75rem+17rem)]">
                  {textInputs.map((input) => (
                    <span key={`${c.value}-${input.key}`} className="inline-flex items-center gap-1.5">
                      {input.placeholder || input.suffix ? (
                        <span className="whitespace-nowrap text-xs text-muted-foreground">
                          {input.placeholder ?? input.suffix}:
                        </span>
                      ) : null}
                      <Input
                        type="text"
                        disabled={disabled || isLoading || !checked}
                        value={getCheckboxCompanionValue(value, c.value, input.key)}
                        onChange={(e) =>
                          onChange(setCheckboxCompanionValue(value, c.value, input.key, e.target.value))
                        }
                        className="h-8 w-44 max-w-full"
                        aria-label={`${c.label} ${input.placeholder ?? input.suffix ?? input.key}`}
                      />
                    </span>
                  ))}
                </div>
              ) : null}
              {c.help ? (
                <p className="whitespace-pre-wrap pl-6 text-xs text-muted-foreground">{c.help}</p>
              ) : null}
            </div>
          );
        })}
        {isError ? (
          <p className="text-xs text-destructive">Could not load options for this field.</p>
        ) : !isLoading && choices.length === 0 ? (
          <p className="text-xs text-muted-foreground">No options configured for this field.</p>
        ) : null}
      </div>
    );
  }

  return (
    <RadioGroup
      value={value || null}
      onValueChange={(next) => onChange(String(next ?? ""))}
      disabled={disabled || isLoading}
      className="flex flex-col gap-2 text-sm"
    >
      {choices.map((c) => (
        <label key={c.value} className="flex cursor-pointer items-start gap-2">
          <RadioGroupItem value={c.value} className="mt-0.5" />
          <span>
            {c.label}
            {c.subtitle ? <span className="mt-0.5 block text-xs text-muted-foreground">{c.subtitle}</span> : null}
            {c.help ? <span className="mt-1 block whitespace-pre-wrap text-xs text-muted-foreground">{c.help}</span> : null}
          </span>
        </label>
      ))}
      {isError ? (
        <p className="text-xs text-destructive">Could not load options for this field.</p>
      ) : !isLoading && choices.length === 0 ? (
        <p className="text-xs text-muted-foreground">No options configured for this field.</p>
      ) : null}
    </RadioGroup>
  );
}

export function EApprovalFieldRenderer({
  field,
  value,
  onChange,
  onFileChange,
  onCameraChange,
  fileSelection = [],
  cameraMetadataByName = {},
  existingFileAttachments = [],
  onRemoveSavedAttachment,
  removingSavedAttachmentId = null,
  approverOptions = [],
  approverOptionsLoading = false,
  disabled,
  prefillLocked = false,
  density = "compact",
  planFeaturesOverride,
  allowRemoteLookups = true,
  helpTextOverride,
  allFields = [],
  allValues = {},
}: Props) {
  const hookPlan = useEApprovalPlanFeatures();
  const features = planFeaturesOverride ?? hookPlan;
  const plan = {
    planTier: features.plan_tier,
    fileUploadsAllowed: features.file_uploads,
    maxFileFields: features.max_file_fields,
  };

  if (field.type === "section") {
    return (
      <div className="border-b border-border pb-1 pt-2">
        <p className="text-sm font-medium text-foreground">{resolveFieldDisplayLabel(field)}</p>
      </div>
    );
  }

  if (field.type === "instruction") {
    const title = resolveFieldDisplayLabel(field);
    const body = formatInstructionBodyForDisplay(parseInstructionBody(field));
    const showTitle = title.trim() !== "" && title.trim() !== field.name;

    return (
      <div className="w-full min-w-0 rounded-lg border border-border/70 bg-muted/25 px-3 py-2.5">
        {showTitle ? <p className="text-sm font-medium text-foreground">{title}</p> : null}
        {body !== "" ? (
          <div className={cn("w-full min-w-0 space-y-2", showTitle && "mt-1.5")}>
            {body.split("\n\n").map((paragraph, index) => (
              <p
                key={`instruction-p-${index}`}
                className="w-full min-w-0 text-sm leading-relaxed text-muted-foreground"
              >
                {paragraph}
              </p>
            ))}
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">No instruction text configured.</p>
        )}
      </div>
    );
  }

  if (field.type === "divider") {
    const dividerLabel = resolveFieldDisplayLabel(field);
    if (dividerLabel && dividerLabel !== "—" && dividerLabel !== field.name) {
      return (
        <div className="border-b border-border pb-1 pt-2">
          <p className="text-sm font-medium text-foreground">{dividerLabel}</p>
        </div>
      );
    }

    return <hr className="border-border" />;
  }

  if (field.type === "page_break") {
    const pageBreakLabel = resolveFieldDisplayLabel(field);
    const caption =
      pageBreakLabel && pageBreakLabel !== "—" && pageBreakLabel !== field.name
        ? pageBreakLabel
        : "Page break";

    return (
      <div className="flex items-center gap-3 py-2" role="separator" aria-label={caption}>
        <div className="h-px flex-1 bg-border" />
        <span className="text-xs font-medium text-muted-foreground">{caption}</span>
        <div className="h-px flex-1 bg-border" />
      </div>
    );
  }

  const displayLabel = resolveFieldDisplayLabel(field);
  const placeholder = fieldPlaceholder(field);
  const maxLength = fieldMaxLength(field);
  const helpText = helpTextOverride ?? fieldHelpText(field) ?? computedFieldHelpText(field, allFields);
  const help = helpText ? <p className="text-xs text-muted-foreground">{helpText}</p> : null;
  const computedReadOnly = isFieldComputedReadOnly(field, allFields);
  const readOnlyLocked = prefillLocked || computedReadOnly;
  const inputDisabled = (disabled && !prefillLocked) || computedReadOnly;
  const lockedInputClassName = readOnlyLocked ? "bg-muted/40 text-foreground" : undefined;
  const label = (
    <Label>
      {displayLabel}
      {field.validation && typeof field.validation === "object" && "required" in field.validation && field.validation.required ? (
        <span className="text-destructive"> *</span>
      ) : null}
    </Label>
  );

  if (isProcurementLinkField(field)) {
    return (
      <EApprovalProcurementLinkField
        fieldName={field.name}
        label={displayLabel}
        value={value}
        onChange={onChange}
        allValues={allValues}
        disabled={disabled}
        allowRemoteLookups={allowRemoteLookups}
        helpText={helpTextOverride ?? helpText ?? undefined}
      />
    );
  }

  switch (field.type) {
    case "textarea":
      return (
        <div className="space-y-2">
          {label}
          <Textarea
            disabled={disabled}
            className="min-h-[100px]"
            placeholder={placeholder}
            maxLength={maxLength}
            value={value}
            onChange={(e) => onChange(e.target.value)}
          />
          {help}
        </div>
      );
    case "select":
      return (
        <div className="space-y-2">
          {label}
          <SelectFieldControl
            field={field}
            value={value}
            onChange={onChange}
            disabled={disabled}
            variant="select"
            allowRemoteLookups={allowRemoteLookups}
          />
        </div>
      );
    case "radio":
      return (
        <div className="space-y-2">
          {label}
          <SelectFieldControl
            field={field}
            value={value}
            onChange={onChange}
            disabled={disabled}
            variant="radio"
            allowRemoteLookups={allowRemoteLookups}
          />
        </div>
      );
    case "checkbox":
      if (isCheckboxMulti(field)) {
        return (
          <div className="space-y-2">
            {label}
            <SelectFieldControl
              field={field}
              value={value}
              onChange={onChange}
              disabled={inputDisabled}
              variant="checkbox"
              allowRemoteLookups={allowRemoteLookups}
            />
            {help}
          </div>
        );
      }

      return (
        <div className="space-y-2">
          <label className="flex cursor-pointer items-start gap-2 text-sm">
            <Checkbox
              disabled={inputDisabled}
              checked={isCheckboxTruthy(value)}
              onCheckedChange={(next) => onChange(next === true ? "true" : "false")}
              className="mt-0.5"
            />
            <span>
              {displayLabel}
              {field.validation &&
              typeof field.validation === "object" &&
              "required" in field.validation &&
              field.validation.required ? (
                <span className="text-destructive"> *</span>
              ) : null}
            </span>
          </label>
          {help}
        </div>
      );
    case "matrix":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalMatrixField
            field={field}
            value={value}
            onChange={onChange}
            disabled={inputDisabled}
          />
          {help}
        </div>
      );
    case "size_matrix":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalSizeMatrixField
            field={field}
            value={value}
            onChange={onChange}
            disabled={inputDisabled}
          />
          {help}
        </div>
      );
    case "checklist_matrix":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalChecklistMatrixField
            field={field}
            value={value}
            onChange={onChange}
            disabled={inputDisabled}
          />
          {help}
        </div>
      );
    case "approver": {
      const resolvedValue = resolveApproverFieldValue(value, approverOptions);
      const emptyLabel = approverOptionsLoading ? "Loading users…" : "Select approver";

      return (
        <div className="space-y-2">
          {label}
          <SelectField
            disabled={disabled || approverOptionsLoading}
            value={resolvedValue}
            onChange={onChange}
            placeholder={emptyLabel}
            emptyLabel={emptyLabel}
            options={approverOptions.map((u) => ({
              value: u.id,
              label: u.label,
            }))}
          />
          {!approverOptionsLoading && approverOptions.length === 0 ? (
            <p className="text-xs text-muted-foreground">No active users available. Ask an administrator to add users.</p>
          ) : null}
          {!approverOptionsLoading && resolvedValue && !approverOptions.some((option) => option.id === resolvedValue) ? (
            <p className="text-xs text-muted-foreground">Selected approver is no longer assignable. Choose another user.</p>
          ) : null}
        </div>
      );
    }
    case "approver_list": {
      const selected = new Set(parseApproverListValue(value));

      return (
        <div className="space-y-2">
          {label}
          {approverOptionsLoading ? (
            <p className="text-xs text-muted-foreground">Loading users…</p>
          ) : approverOptions.length === 0 ? (
            <p className="text-xs text-muted-foreground">No active users available. Ask an administrator to add users.</p>
          ) : (
            <div className="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-border bg-card p-3">
              {approverOptions.map((user) => {
                const checked = selected.has(user.id);

                return (
                  <label key={user.id} className="flex cursor-pointer items-start gap-2 text-sm">
                    <Checkbox
                      checked={checked}
                      disabled={disabled}
                      onCheckedChange={() => onChange(toggleApproverListId(value, user.id))}
                      className="mt-0.5"
                    />
                    <span className="min-w-0 leading-5 text-foreground">{user.label}</span>
                  </label>
                );
              })}
            </div>
          )}
          <p className="text-xs text-muted-foreground">
            {selected.size === 0
              ? "Select one or more stakeholders. They will approve in parallel."
              : `${selected.size} selected`}
          </p>
          {help}
        </div>
      );
    }
    case "file":
      if (!plan.fileUploadsAllowed) {
        return (
          <div className="space-y-2">
            {label}
            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
              File uploads require a <span className="font-medium">Professional</span> or{" "}
              <span className="font-medium">Enterprise</span> plan (current: {plan.planTier}).
            </p>
          </div>
        );
      }

      return (
        <div className="space-y-2">
          {label}
          <EApprovalFileField
            field={field}
            disabled={disabled}
            files={fileSelection}
            existingAttachments={existingFileAttachments}
            onRemoveSaved={onRemoveSavedAttachment}
            removingSavedId={removingSavedAttachmentId}
            onChange={(nextFiles) => {
              onFileChange?.(nextFiles);
              onChange(
                nextFiles.length > 0 || existingFileAttachments.length > 0
                  ? [...existingFileAttachments.map((attachment) => attachment.file_name), ...nextFiles.map((file) => file.name)].join(", ")
                  : "",
              );
            }}
          />
        </div>
      );
    case "camera":
      if (!plan.fileUploadsAllowed) {
        return (
          <div className="space-y-2">
            {label}
            <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
              Camera uploads require a <span className="font-medium">Professional</span> or{" "}
              <span className="font-medium">Enterprise</span> plan (current: {plan.planTier}).
            </p>
          </div>
        );
      }

      return (
        <div className="space-y-2">
          {label}
          <EApprovalCameraField
            field={field}
            disabled={disabled}
            files={fileSelection}
            metadataByName={cameraMetadataByName}
            existingAttachments={existingFileAttachments}
            onRemoveSaved={onRemoveSavedAttachment}
            removingSavedId={removingSavedAttachmentId}
            onChange={(nextFiles, nextMeta) => {
              onCameraChange?.(nextFiles, nextMeta);
              onFileChange?.(nextFiles);
              onChange(
                nextFiles.length > 0 || existingFileAttachments.length > 0
                  ? [...existingFileAttachments.map((attachment) => attachment.file_name), ...nextFiles.map((file) => file.name)].join(", ")
                  : "",
              );
            }}
          />
        </div>
      );
    case "signature":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalSignaturePad
            disabled={disabled}
            value={value}
            onChange={onChange}
            placeholder={placeholder}
          />
          {help}
        </div>
      );
    case "rating":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalRatingField field={field} value={value} onChange={onChange} disabled={disabled} />
          {help}
        </div>
      );
    case "location":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalLocationField field={field} value={value} onChange={onChange} disabled={disabled} />
          {help}
        </div>
      );
    case "tags":
      return (
        <div className="space-y-2">
          {label}
          <EApprovalTagsField field={field} value={value} onChange={onChange} disabled={disabled} />
          {help}
        </div>
      );
    case "grid":
      return (
        <div className={cn("min-w-0 space-y-2", density === "comfortable" && "col-span-full w-full")}>
          {label}
          <EApprovalGridField
            field={field}
            value={value}
            onChange={onChange}
            disabled={disabled}
            density={density}
            allowRemoteLookups={allowRemoteLookups}
          />
        </div>
      );
    case "email":
      return (
        <div className="space-y-2">
          {label}
          <Input
            disabled={disabled}
            type="email"
            placeholder={placeholder}
            maxLength={maxLength}
            value={value}
            onChange={(e) => onChange(e.target.value)}
          />
          {help}
        </div>
      );
    case "phone":
      return (
        <div className="space-y-2">
          {label}
          <Input
            disabled={disabled}
            type="tel"
            placeholder={placeholder}
            maxLength={maxLength}
            value={value}
            onChange={(e) => onChange(e.target.value)}
          />
          {help}
        </div>
      );
    case "url":
      return (
        <div className="space-y-2">
          {label}
          <Input
            disabled={disabled}
            type="url"
            placeholder={placeholder ?? "https://"}
            maxLength={maxLength}
            value={value}
            onChange={(e) => onChange(e.target.value)}
          />
          {help}
        </div>
      );
    case "currency":
      if (computedReadOnly) {
        return (
          <div className="space-y-2">
            {label}
            <Input
              disabled={inputDisabled}
              readOnly
              type="text"
              inputMode="decimal"
              value={formatCurrencyGrouping(value)}
              className="bg-muted/40 tabular-nums"
            />
            {help}
          </div>
        );
      }

      return (
        <div className="space-y-2">
          {label}
          <CurrencyInput
            disabled={disabled && !prefillLocked}
            readOnly={readOnlyLocked}
            placeholder={placeholder || "0.00"}
            value={value}
            onChange={onChange}
            className={lockedInputClassName ?? undefined}
          />
          {help}
        </div>
      );
    case "number":
    case "date":
    case "date_range":
      if (field.type === "date_range") {
        const range = parseDateRangeValue(value);
        return (
          <div className="space-y-2">
            {label}
            <DateRangePicker
              disabled={disabled && !prefillLocked}
              readOnly={readOnlyLocked}
              placeholder="Select date range"
              value={range}
              onChange={(next) => onChange(serializeDateRangeValue(next))}
              className={lockedInputClassName ?? undefined}
            />
            {help}
          </div>
        );
      }

      if (field.type === "date") {
        return (
          <div className="space-y-2">
            {label}
            <DatePicker
              disabled={disabled && !prefillLocked}
              readOnly={readOnlyLocked}
              placeholder={placeholder || "Select date"}
              value={value}
              onChange={onChange}
              className={lockedInputClassName ?? undefined}
            />
            {help}
          </div>
        );
      }

      return (
        <div className="space-y-2">
          {label}
          <Input
            disabled={disabled && !prefillLocked}
            readOnly={readOnlyLocked}
            type="number"
            placeholder={placeholder}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={lockedInputClassName ?? (computedReadOnly ? "bg-muted/40 tabular-nums" : undefined)}
          />
          {help}
        </div>
      );
    default:
      return (
        <div className="space-y-2">
          {label}
          <Input
            disabled={disabled && !prefillLocked}
            readOnly={readOnlyLocked}
            placeholder={placeholder}
            maxLength={maxLength}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={lockedInputClassName}
          />
          {help}
        </div>
      );
  }
}
