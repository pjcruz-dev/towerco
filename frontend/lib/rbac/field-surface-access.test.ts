import { describe, expect, it } from "vitest";

import {
  fieldEditableOnForm,
  fieldVisibleOnSurface,
  levelFromSurfaces,
  surfacesFromLevel,
} from "@/lib/rbac/field-surface-access";

describe("field surface access", () => {
  it("keeps full access on every surface and editable", () => {
    expect(fieldVisibleOnSurface("full", "form")).toBe(true);
    expect(fieldVisibleOnSurface("full", "table")).toBe(true);
    expect(fieldVisibleOnSurface("full", "record")).toBe(true);
    expect(fieldEditableOnForm("full")).toBe(true);
  });

  it("shows view-only everywhere without fill-in", () => {
    expect(fieldVisibleOnSurface("view", "form")).toBe(true);
    expect(fieldVisibleOnSurface("view", "table")).toBe(true);
    expect(fieldEditableOnForm("view")).toBe(false);
  });

  it("limits table + record, record, and form to those surfaces", () => {
    expect(fieldVisibleOnSurface("table_record", "form")).toBe(false);
    expect(fieldVisibleOnSurface("table_record", "table")).toBe(true);
    expect(fieldVisibleOnSurface("table_record", "record")).toBe(true);
    expect(fieldVisibleOnSurface("record", "table")).toBe(false);
    expect(fieldVisibleOnSurface("record", "record")).toBe(true);
    expect(fieldVisibleOnSurface("form", "form")).toBe(true);
    expect(fieldVisibleOnSurface("form", "table")).toBe(false);
    expect(fieldVisibleOnSurface("form", "record")).toBe(false);
    expect(fieldEditableOnForm("form")).toBe(true);
  });

  it("round-trips form, table and record columns", () => {
    for (const level of ["full", "view", "table_record", "record", "form", "hide"] as const) {
      const surfaces = surfacesFromLevel(level);
      expect(levelFromSurfaces(surfaces.form, surfaces.table, surfaces.record)).toBe(level);
    }
  });

  it("stores table-only as table and record", () => {
    expect(levelFromSurfaces("hide", "full", "hide")).toBe("table_record");
  });

  it("hides a field from every surface", () => {
    expect(fieldVisibleOnSurface("hide", "form")).toBe(false);
    expect(fieldVisibleOnSurface("hide", "table")).toBe(false);
    expect(fieldVisibleOnSurface("hide", "record")).toBe(false);
    expect(fieldEditableOnForm("hide")).toBe(false);
  });
});
