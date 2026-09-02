"use client";

import { Pencil } from "lucide-react";

import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { DynEntityDetail, DynField } from "@/lib/api/modules/dynamic-entities-api";
import type { DynSchemaMode } from "@/components/dynamic-entities/dyn-schema-sheets";
import { dynColumnSpanStyle } from "@/lib/dynamic-entities/form-arrange-rows";

type Group = DynEntityDetail["field_groups"][number];

type Props = {
  title: string;
  group?: Group | null;
  fields: DynField[];
  canManageSchema: boolean;
  mode?: "view" | "form";
  renderField: (field: DynField) => React.ReactNode;
  onSchemaAction: (mode: DynSchemaMode) => void;
};

export function DynSchemaGroupCard({
  title,
  group,
  fields,
  canManageSchema,
  mode: _mode = "view",
  renderField,
  onSchemaAction,
}: Props) {
  return (
    <Card className="rounded-xl">
      <CardHeader className="flex flex-row items-start justify-between gap-2 space-y-0">
        <CardTitle className="text-base font-medium">{title}</CardTitle>
        {canManageSchema ? (
          <RowActionsMenu
            label={`${title} schema actions`}
            items={[
              {
                key: "add-field",
                label: "Add field",
                onSelect: () => onSchemaAction({ kind: "add-field", groupId: group?.id ?? null }),
              },
              { type: "separator", key: "sep-group" },
              {
                key: "new-group",
                label: "New group",
                onSelect: () => onSchemaAction({ kind: "new-group" }),
              },
              {
                key: "edit-group",
                label: group ? `Edit “${group.name}”` : "Edit group",
                hidden: !group,
                onSelect: () => (group ? onSchemaAction({ kind: "edit-group", group }) : undefined),
              },
            ]}
          />
        ) : null}
      </CardHeader>
      <CardContent className="grid grid-cols-12 gap-4">
        {fields.length === 0 ? (
          <p className="col-span-12 text-sm text-muted-foreground">
            {canManageSchema ? "No fields in this group yet. Use Add field." : "No fields in this group."}
          </p>
        ) : null}
        {fields.map((field) => (
          <div
            key={field.id}
            className="relative"
            style={dynColumnSpanStyle(field.column_span)}
          >
            {canManageSchema ? (
              <div className="mb-1 flex items-center justify-between gap-2">
                <span className="text-xs font-medium text-muted-foreground">
                  {field.label}
                  {field.is_required ? " *" : ""}
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-xs"
                  className="text-muted-foreground"
                  aria-label={`Edit field ${field.label}`}
                  onClick={() => onSchemaAction({ kind: "edit-field", field })}
                >
                  <Pencil className="size-3.5" />
                </Button>
              </div>
            ) : null}
            {renderField(field)}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
