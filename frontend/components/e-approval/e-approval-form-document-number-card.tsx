"use client";

import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Switch } from "@/components/ui/switch";
import {
  buildDocumentNumberPreview,
  DOCUMENT_NUMBER_BUILTIN_TOKENS,
  DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER,
  documentNumberFieldTokens,
  templateIncludesToken,
  toggleTemplateToken,
  type EApprovalFormDocumentNumberSettings,
} from "@/modules/e-approval/form-document-number";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";
import { cn } from "@/lib/utils";

type Props = {
  value: EApprovalFormDocumentNumberSettings;
  onChange: (next: EApprovalFormDocumentNumberSettings) => void;
  fields: EApprovalFormFieldInput[];
  disabled?: boolean;
};

function TokenChip({
  label,
  token,
  active,
  disabled,
  onToggle,
}: {
  label: string;
  token: string;
  active: boolean;
  disabled?: boolean;
  onToggle: (enabled: boolean) => void;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={() => onToggle(!active)}
      className={cn(
        "rounded-md border px-2.5 py-1.5 text-left text-xs transition-colors",
        active
          ? "border-primary bg-primary/10 text-primary"
          : "border-border bg-background text-foreground hover:bg-muted",
        disabled && "cursor-not-allowed opacity-50",
      )}
    >
      <span className="block font-medium">{label}</span>
      <code className="mt-0.5 block text-[11px] text-muted-foreground">{token}</code>
    </button>
  );
}

export function EApprovalFormDocumentNumberCard({ value, onChange, fields, disabled }: Props) {
  const patch = (partial: Partial<EApprovalFormDocumentNumberSettings>) => {
    onChange({ ...value, ...partial });
  };

  const fieldTokens = documentNumberFieldTokens(fields);
  const template = value.docNoCustomEnabled ? value.docNoTemplate : "";
  const preview = buildDocumentNumberPreview(value, fields);

  const handleTokenToggle = (token: string, enabled: boolean) => {
    patch({ docNoTemplate: toggleTemplateToken(template, token, enabled) });
  };

  return (
    <EApprovalSectionCard
      title="Document number"
      description="How submission document codes are generated for this form (e.g. ATC-QMS-P-001)."
    >
      <div className="grid gap-4 md:grid-cols-2">
        <div className="space-y-2">
          <Label htmlFor="ea-form-owner-code">Owner code</Label>
          <Input
            id="ea-form-owner-code"
            value={value.ownerCode}
            disabled={disabled}
            placeholder="GEN"
            onChange={(e) => patch({ ownerCode: e.target.value.toUpperCase() })}
          />
          <p className="text-xs text-muted-foreground">Short prefix when using default numbering.</p>
        </div>
        <div className="space-y-2">
          <Label htmlFor="ea-form-doc-type-code">Document type code</Label>
          <Input
            id="ea-form-doc-type-code"
            value={value.docTypeCode}
            disabled={disabled}
            placeholder="F"
            onChange={(e) => patch({ docTypeCode: e.target.value.toUpperCase() })}
          />
          <p className="text-xs text-muted-foreground">Second segment for default numbering.</p>
        </div>
      </div>

      <label className="mt-4 flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
        <span>
          <span className="block text-sm font-medium text-foreground">Custom document number template</span>
          <span className="mt-0.5 block text-xs text-muted-foreground">
            Tick fields below to build the template from your form design.
          </span>
        </span>
        <Switch
          checked={value.docNoCustomEnabled}
          disabled={disabled}
          onCheckedChange={(checked) => patch({ docNoCustomEnabled: checked })}
        />
      </label>

      {value.docNoCustomEnabled ? (
        <div className="mt-4 space-y-4">
          <div className="space-y-2">
            <Label htmlFor="ea-form-doc-no-template">Number template</Label>
            <Input
              id="ea-form-doc-no-template"
              value={value.docNoTemplate}
              disabled={disabled}
              placeholder={DOCUMENT_NUMBER_TEMPLATE_PLACEHOLDER}
              onChange={(e) => patch({ docNoTemplate: e.target.value })}
            />
          </div>

          <div className="space-y-2">
            <p className="text-xs font-medium text-foreground">Form fields</p>
            {fieldTokens.length > 0 ? (
              <div className="flex flex-wrap gap-2">
                {fieldTokens.map((item) => (
                  <TokenChip
                    key={item.id}
                    label={item.label}
                    token={item.token}
                    active={templateIncludesToken(template, item.token)}
                    disabled={disabled}
                    onToggle={(enabled) => handleTokenToggle(item.token, enabled)}
                  />
                ))}
              </div>
            ) : (
              <p className="text-xs text-muted-foreground">
                Add short text, number, or dropdown fields on the Design tab — they will appear here.
              </p>
            )}
          </div>

          <div className="space-y-2">
            <p className="text-xs font-medium text-foreground">Built-in tokens</p>
            <div className="flex flex-wrap gap-2">
              {DOCUMENT_NUMBER_BUILTIN_TOKENS.map((item) => (
                <TokenChip
                  key={item.id}
                  label={item.label}
                  token={item.token}
                  active={templateIncludesToken(template, item.token)}
                  disabled={disabled}
                  onToggle={(enabled) => handleTokenToggle(item.token, enabled)}
                />
              ))}
            </div>
          </div>

          <p className="text-xs text-muted-foreground">
            Tip for ISO: tick <strong>Department</strong>, <strong>Document type</strong>, and{" "}
            <strong>Sequence</strong>. Start the template with <code className="rounded bg-muted px-1">ATC</code> if
            you want that prefix.
          </p>
        </div>
      ) : null}

      <p className="mt-4 rounded-lg border border-border/80 bg-muted/20 px-3 py-2 text-xs text-muted-foreground">
        <span className="font-medium text-foreground">Example: </span>
        <code className="text-foreground">{preview}</code>
      </p>
    </EApprovalSectionCard>
  );
}
