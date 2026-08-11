import { describe, expect, it } from "vitest";

import {
  buildDefaultTableColumns,
  dashboardSettingsFromMetadata,
  enabledWidgets,
  mergeDashboardIntoWorkspaceMetadata,
  resolveSavedViewFilters,
  visibleTableColumns,
} from "@/modules/e-approval/form-workspace-dashboard-config";

describe("form workspace dashboard config", () => {
  it("builds default table columns with form fields", () => {
    const columns = buildDefaultTableColumns([
      { type: "text", name: "title", label: "Title" },
      { type: "section", name: "section_1", label: "Section" },
      { type: "select", name: "category", label: "Category" },
    ]);

    expect(columns.some((column) => column.field_name === "title")).toBe(true);
    expect(columns.some((column) => column.field_name === "section_1")).toBe(false);
  });

  it("merges dashboard settings into workspace metadata", () => {
    const merged = mergeDashboardIntoWorkspaceMetadata(
      { workspace: { enabled: true, slug: "iso-approval" } },
      {
        widgets: [{ id: "kpis", type: "kpis", enabled: true, order: 1 }],
        table_columns: [{ key: "document_no", label: "Document", kind: "system", visible: true, order: 1 }],
        saved_views: [{ id: "pending", label: "Pending", status: "pending", order: 1 }],
      },
    );

    expect(merged.workspace).toMatchObject({
      dashboard: {
        widgets: [{ id: "kpis", type: "kpis", enabled: true, order: 1 }],
      },
    });
  });

  it("parses dashboard settings from metadata", () => {
    const settings = dashboardSettingsFromMetadata({
      workspace: {
        dashboard: {
          widgets: [{ id: "submissions_table", type: "submissions_table", enabled: true, order: 1 }],
          saved_views: [{ id: "mine", label: "Mine", mine: true, order: 1 }],
        },
      },
    });

    expect(enabledWidgets(settings.widgets)[0]?.type).toBe("submissions_table");
    expect(resolveSavedViewFilters(settings.saved_views[0])).toMatchObject({ mine: true });
  });

  it("returns visible columns in order", () => {
    const columns = visibleTableColumns([
      { key: "status", label: "Status", kind: "system", visible: true, order: 2 },
      { key: "document_no", label: "Document", kind: "system", visible: true, order: 1 },
      { key: "created_at", label: "Submitted", kind: "system", visible: false, order: 3 },
    ]);

    expect(columns.map((column) => column.key)).toEqual(["document_no", "status"]);
  });
});
