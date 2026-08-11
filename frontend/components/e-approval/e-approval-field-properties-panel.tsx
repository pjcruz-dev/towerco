"use client";

import { useState } from "react";

import { EApprovalFieldComputedOptionsEditor } from "@/components/e-approval/e-approval-field-computed-options-editor";
import { EApprovalFieldTypeOptionsEditor } from "@/components/e-approval/e-approval-field-type-options-editor";
import { EApprovalFieldVisibilityEditor } from "@/components/e-approval/e-approval-field-visibility-editor";
import { useEApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import { EApprovalGridColumnsEditor } from "@/components/e-approval/e-approval-grid-columns-editor";
import { EApprovalChecklistMatrixOptionsEditor } from "@/components/e-approval/e-approval-checklist-matrix-options-editor";
import { EApprovalMatrixOptionsEditor } from "@/components/e-approval/e-approval-matrix-options-editor";
import { EApprovalSizeMatrixOptionsEditor } from "@/components/e-approval/e-approval-size-matrix-options-editor";
import { EApprovalSelectOptionsEditor } from "@/components/e-approval/e-approval-select-options-editor";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import {
  collectFieldApiKeys,
  isFieldApiKeyEditable,
  suggestApiKeyFromLabel,
} from "@/modules/e-approval/field-api-key";
import {
  collectLayoutRowIds,
  createLayoutRowId,
  FIELD_LAYOUT_WIDTH_LABELS,
  fieldSupportsLayout,
  findNextAvailableRowSlot,
  inferLayoutRowColumnCount,
  layoutWidthForRowColumns,
  parseFieldLayout,
  patchFieldLayout,
  type EApprovalFieldLayoutWidth,
} from "@/modules/e-approval/field-layout";
import { isCheckboxMulti } from "@/modules/e-approval/field-checkbox";
import { parseInstructionBody, setInstructionBody } from "@/modules/e-approval/field-instruction";
import { parseMatrixFieldOptions, setMatrixFieldOptions } from "@/modules/e-approval/field-matrix";
import {
  parseChecklistMatrixFieldOptions,
  setChecklistMatrixFieldOptions,
} from "@/modules/e-approval/field-checklist-matrix";
import { parseSizeMatrixRows, setSizeMatrixRows } from "@/modules/e-approval/field-size-matrix";
import { Textarea } from "@/components/ui/textarea";
import { fieldSupportsVisibilityRules } from "@/modules/e-approval/field-visibility";
import {
  fieldSupportsTypeOptions,
  fieldSupportsValidationRules,
  parseFieldValidation,
  patchFieldValidation,
} from "@/modules/e-approval/field-validation";
import { fieldSupportsComputedTotal } from "@/modules/e-approval/field-computed-options";
import { mergeFieldOptions } from "@/modules/e-approval/field-options";
import {
  E_APPROVAL_FIELD_TYPES,
  formatEApprovalFieldTypeLabel,
  parseGridColumnDefs,
  setGridColumnDefs,
} from "@/modules/e-approval/field-types";
import { Button } from "@/components/ui/button";
import type { LayoutRowScaffold } from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

type Props = {
  field: EApprovalFormFieldInput;
  allFields: EApprovalFormFieldInput[];
  fieldIndex: number;
  layoutRows?: LayoutRowScaffold[];
  apiKeysLocked: boolean;
  onChange: (patch: Partial<EApprovalFormFieldInput>) => void;
};

export function EApprovalFieldPropertiesPanel({
  field,
  allFields,
  fieldIndex,
  layoutRows = [],
  apiKeysLocked,
  onChange,
}: Props) {
  const [showAdvanced, setShowAdvanced] = useState(false);
  const apiKeyEditable = isFieldApiKeyEditable(apiKeysLocked, field);
  const validation = parseFieldValidation(field);
  const layout = parseFieldLayout(field);
  const plan = useEApprovalPlanFeatures();
  const showRules = fieldSupportsValidationRules(field.type);
  const showVisibility = fieldSupportsVisibilityRules(field.type);
  const showLayout = fieldSupportsLayout(field.type);
  const showTypeOptions = fieldSupportsTypeOptions(field.type);
  const rowIds = collectLayoutRowIds(allFields);
  const rowEntries = layout.row_id
    ? allFields
        .map((f, i) => ({ field: f, index: i }))
        .filter((e) => parseFieldLayout(e.field).row_id === layout.row_id)
    : [];
  const rowColumnCount = layout.row_id
    ? (layoutRows.find((row) => row.id === layout.row_id)?.columns ??
      layout.row_columns ??
      inferLayoutRowColumnCount(rowEntries))
    : 2;

  const columnCountForRowId = (rowId: string): number =>
    layoutRows.find((row) => row.id === rowId)?.columns ??
    inferLayoutRowColumnCount(
      allFields
        .map((f, i) => ({ field: f, index: i }))
        .filter((e) => parseFieldLayout(e.field).row_id === rowId || e.index === fieldIndex),
    );

  const handleLabelChange = (label: string) => {
    const patch: Partial<EApprovalFormFieldInput> = { label };
    if (apiKeyEditable) {
      const taken = collectFieldApiKeys(allFields, fieldIndex);
      patch.name = suggestApiKeyFromLabel(label, taken);
    }
    onChange(patch);
  };

  const updateValidation = (patch: Parameters<typeof patchFieldValidation>[1]) => {
    onChange({ validation: patchFieldValidation(field, patch) });
  };

  const updateOptions = (patch: Record<string, unknown>) => {
    onChange({ options: mergeFieldOptions(field, patch) });
  };

  const updateLayout = (patch: Parameters<typeof patchFieldLayout>[1]) => {
    updateOptions(patchFieldLayout(field, patch));
  };

  const removeFromColumnRow = () => {
    updateLayout({ row_id: undefined, slot: undefined, width: "full" });
  };

  return (
    <div className="min-w-0 space-y-3 overflow-x-hidden">
      <div className="space-y-1">
        <Label htmlFor="ea-field-label">Label</Label>
        <Input
          id="ea-field-label"
          value={field.label}
          onChange={(e) => handleLabelChange(e.target.value)}
          placeholder="Shown to requestors"
        />
        <p className="text-xs text-muted-foreground">Display name on the form and in exports.</p>
      </div>

      <div className="space-y-1">
        <Label htmlFor="ea-field-type">Field type</Label>
        <Select id="ea-field-type" value={field.type} onChange={(e) => onChange({ type: e.target.value })}>
          {E_APPROVAL_FIELD_TYPES.map((t) => (
            <option key={t} value={t}>
              {formatEApprovalFieldTypeLabel(t)}
            </option>
          ))}
        </Select>
      </div>

      {showLayout ? (
        <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
          <p className="text-xs font-medium text-foreground">Layout</p>
          <div className="space-y-1">
            <Label htmlFor="ea-field-width">Layout width</Label>
            {layout.row_id ? (
              <div className="space-y-2">
                <p className="rounded-md border border-border/60 bg-muted/30 px-2 py-2 text-xs text-muted-foreground">
                  In a {rowColumnCount}-column row, width is{" "}
                  <span className="font-medium text-foreground">
                    {FIELD_LAYOUT_WIDTH_LABELS[layoutWidthForRowColumns(rowColumnCount)]}
                  </span>
                  . Remove the field from the row to use full width or another size.
                </p>
                <Button type="button" size="sm" variant="outline" className="w-full" onClick={removeFromColumnRow}>
                  Remove from column row
                </Button>
              </div>
            ) : (
              <Select
                id="ea-field-width"
                value={layout.width}
                onChange={(e) => updateLayout({ width: e.target.value as EApprovalFieldLayoutWidth })}
              >
                {(Object.keys(FIELD_LAYOUT_WIDTH_LABELS) as EApprovalFieldLayoutWidth[]).map((w) => (
                  <option key={w} value={w}>
                    {FIELD_LAYOUT_WIDTH_LABELS[w]}
                  </option>
                ))}
              </Select>
            )}
          </div>
          <div className="space-y-1">
            <Label htmlFor="ea-field-row">Row group</Label>
            <Select
              id="ea-field-row"
              value={layout.row_id ?? ""}
              onChange={(e) => {
                const v = e.target.value;
                if (v === "__new__") {
                  const rowId = createLayoutRowId();
                  updateLayout({
                    row_id: rowId,
                    width: layoutWidthForRowColumns(2),
                    slot: 0,
                    row_columns: 2,
                  });
                  return;
                }
                if (!v) {
                  updateLayout({ row_id: undefined, slot: undefined, width: "full" });
                  return;
                }
                const columns = columnCountForRowId(v);
                const slot = findNextAvailableRowSlot(v, allFields, columns, fieldIndex);
                updateLayout({
                  row_id: v,
                  width: layoutWidthForRowColumns(columns),
                  slot,
                  row_columns: columns,
                });
              }}
            >
              <option value="">None — stacked vertically</option>
              {rowIds.map((id) => (
                <option key={id} value={id}>
                  Row {id.replace(/^row_/, "").slice(0, 8)}
                </option>
              ))}
              <option value="__new__">+ Create new row</option>
            </Select>
            {layout.row_id ? (
              <div className="space-y-1">
                <Label htmlFor="ea-field-slot">
                  Column slot (1–{rowColumnCount}, 0-based index)
                </Label>
                <Input
                  id="ea-field-slot"
                  type="number"
                  min={0}
                  max={rowColumnCount - 1}
                  value={layout.slot ?? 0}
                  onChange={(e) => {
                    const slot = Math.min(
                      rowColumnCount - 1,
                      Math.max(0, Number(e.target.value) || 0),
                    );
                    updateLayout({
                      slot,
                      width: layoutWidthForRowColumns(rowColumnCount),
                      row_columns: rowColumnCount,
                    });
                  }}
                />
                <p className="text-xs text-muted-foreground">
                  Slot 0 is the left column in this {rowColumnCount}-column row. Drag fields onto empty slots in the
                  canvas to place them automatically.
                </p>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}

      {field.type === "file" || field.type === "camera" ? (
        !plan.fileUploadsAllowed ? (
          <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
            File and camera uploads are disabled on the <span className="font-medium">{plan.planTier}</span> plan. Upgrade
            to Professional or Enterprise, or remove this field before publishing.
          </p>
        ) : null
      ) : null}

      {showTypeOptions ? (
        <EApprovalFieldTypeOptionsEditor
          field={field}
          onChange={updateOptions}
          onValidationChange={(validation) => {
            if (validation) {
              onChange({ validation });
            }
          }}
        />
      ) : null}

      {fieldSupportsComputedTotal(field) ? (
        <EApprovalFieldComputedOptionsEditor
          field={field}
          allFields={allFields}
          fieldIndex={fieldIndex}
          onChange={updateOptions}
          onValidationChange={(patch) => updateValidation(patch)}
        />
      ) : null}

      {showVisibility ? (
        <EApprovalFieldVisibilityEditor
          field={field}
          allFields={allFields}
          fieldIndex={fieldIndex}
          onChange={updateOptions}
        />
      ) : null}

      {showRules ? (
        <div className="space-y-3 rounded-lg border border-border/60 bg-muted/10 p-3">
          <p className="text-xs font-medium text-foreground">Validation & display</p>
          <label className="flex items-center gap-2 text-sm">
            <Checkbox
              checked={validation.required ?? false}
              onCheckedChange={(v) => updateValidation({ required: v === true })}
              className="size-4"
            />
            {field.type === "checkbox"
              ? isCheckboxMulti(field)
                ? "Required (at least one option)"
                : "Required (must be checked)"
              : field.type === "matrix" || field.type === "size_matrix"
                ? "Required (answer every row)"
                : field.type === "checklist_matrix"
                  ? "Required (select at least one row)"
                : "Required field"}
          </label>
          {field.type !== "checkbox" &&
          field.type !== "matrix" &&
          field.type !== "size_matrix" &&
          field.type !== "checklist_matrix" &&
          field.type !== "file" &&
          field.type !== "camera" &&
          field.type !== "signature" ? (
            <div className="space-y-1">
              <Label htmlFor="ea-field-placeholder">Placeholder</Label>
              <Input
                id="ea-field-placeholder"
                value={validation.placeholder ?? ""}
                onChange={(e) => updateValidation({ placeholder: e.target.value })}
                placeholder="Hint text inside the input"
              />
            </div>
          ) : null}
          {(field.type === "text" || field.type === "textarea") && (
            <div className="space-y-1">
              <Label htmlFor="ea-field-maxlen">Max length</Label>
              <Input
                id="ea-field-maxlen"
                type="number"
                min={1}
                value={validation.max_length ?? ""}
                onChange={(e) => {
                  const n = e.target.value === "" ? undefined : Number(e.target.value);
                  updateValidation({ max_length: n && n > 0 ? n : undefined });
                }}
                placeholder="Optional"
              />
            </div>
          )}
          <div className="space-y-1">
            <Label htmlFor="ea-field-default">Default value</Label>
            <Input
              id="ea-field-default"
              value={validation.default ?? ""}
              onChange={(e) => updateValidation({ default: e.target.value })}
              placeholder="Pre-filled when opening the form"
            />
          </div>
          <div className="space-y-1">
            <Label htmlFor="ea-field-help">Help text</Label>
            <Input
              id="ea-field-help"
              value={validation.help_text ?? ""}
              onChange={(e) => updateValidation({ help_text: e.target.value })}
              placeholder="Short hint shown below the field"
            />
          </div>
        </div>
      ) : null}

      <div className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
        <span className="font-medium text-foreground">API key:</span>{" "}
        <code className="font-mono text-[11px]">{field.name}</code>
        {apiKeysLocked && field.id ? (
          <p className="mt-1">Locked because the form is published or has submissions. Renaming can break existing data.</p>
        ) : (
          <p className="mt-1">Auto-generated from the label. Used in workflows, exports, and integrations.</p>
        )}
      </div>

      <button
        type="button"
        className="text-xs font-medium text-primary hover:underline"
        onClick={() => setShowAdvanced((v) => !v)}
      >
        {showAdvanced ? "Hide advanced" : "Advanced: edit API key"}
      </button>

      {showAdvanced ? (
        <div className="space-y-1">
          <Label htmlFor="ea-field-name">Name (API key)</Label>
          <Input
            id="ea-field-name"
            value={field.name}
            disabled={!apiKeyEditable}
            onChange={(e) => onChange({ name: e.target.value.replace(/\s+/g, "_") })}
            className="font-mono text-xs"
          />
          {!apiKeyEditable ? (
            <p className="text-xs text-amber-800 dark:text-amber-200">This key cannot be changed after publish or first submission.</p>
          ) : null}
        </div>
      ) : null}

      {(field.type === "select" || field.type === "radio" || field.type === "checkbox") && (
        <>
          {field.type === "checkbox" ? (
            <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
              With options → multi-select checklist. Remove all options (and clear master data) for a single Yes/No
              confirmation.
            </p>
          ) : null}
          <EApprovalSelectOptionsEditor field={field} onChange={updateOptions} />
        </>
      )}
      {field.type === "grid" && (
        <EApprovalGridColumnsEditor
          columns={parseGridColumnDefs(field)}
          onChange={(columns) => updateOptions(setGridColumnDefs(columns) as Record<string, unknown>)}
        />
      )}
      {field.type === "instruction" && (
        <div className="space-y-2">
          <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
            Static guidance for requestors (not an answer field). Use Label as an optional title.
          </p>
          <div className="space-y-1">
            <Label htmlFor="ea-instruction-body">Body</Label>
            <Textarea
              id="ea-instruction-body"
              rows={6}
              value={parseInstructionBody(field)}
              onChange={(e) => updateOptions(setInstructionBody(field, e.target.value))}
              placeholder={"a. First instruction…\nb. Second instruction…"}
            />
          </div>
        </div>
      )}
      {field.type === "matrix" && (
        <>
          <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
            Each row is answered with one column choice (default Yes / No). Optionally add a notes column per row.
          </p>
          <EApprovalMatrixOptionsEditor
            rows={parseMatrixFieldOptions(field).rows}
            columns={parseMatrixFieldOptions(field).columns}
            rowNotes={parseMatrixFieldOptions(field).row_notes === true}
            rowNotesLabel={parseMatrixFieldOptions(field).row_notes_label ?? "Notes"}
            onChange={(next) => updateOptions(setMatrixFieldOptions(field, next))}
          />
        </>
      )}
      {field.type === "size_matrix" && (
        <>
          <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
            Configure each row as Size (W × H + NA) or Text line (Other / Existing Utilities). Text rows are optional.
          </p>
          <EApprovalSizeMatrixOptionsEditor
            rows={parseSizeMatrixRows(field)}
            onChange={(rows) => updateOptions(setSizeMatrixRows(field, rows))}
          />
        </>
      )}
      {field.type === "checklist_matrix" && (
        <>
          <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
            Fixed checkbox rows plus configurable columns (short text, number, currency, date, or dropdown).
          </p>
          <EApprovalChecklistMatrixOptionsEditor
            rows={parseChecklistMatrixFieldOptions(field).rows}
            columns={parseChecklistMatrixFieldOptions(field).columns}
            rowSelectLabel={parseChecklistMatrixFieldOptions(field).row_select_label ?? "Cost Application"}
            onChange={(next) => updateOptions(setChecklistMatrixFieldOptions(field, next))}
          />
        </>
      )}

      {field.type === "grid" ? (
        <p className="rounded-lg border border-border/60 bg-muted/20 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
          Tip: add a <span className="font-medium text-foreground">Currency</span> field above this grid and enable{" "}
          <span className="font-medium text-foreground">Auto-calculated total</span> in its properties, or drag a finance
          shortcut from the catalog.
        </p>
      ) : null}
    </div>
  );
}
