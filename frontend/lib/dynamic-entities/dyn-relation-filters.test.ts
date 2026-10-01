import { describe, expect, it } from "vitest";

import {
  parseDynRelationFilters,
  relationFilterFieldOptions,
  relationFiltersToRecordFilterParams,
} from "@/lib/dynamic-entities/dyn-relation-filters";
import type { DynField } from "@/lib/api/modules/dynamic-entities-api";

describe("parseDynRelationFilters", () => {
  it("accepts Metacoresoft-style operators", () => {
    expect(
      parseDynRelationFilters({
        filters: [
          { field: "status", op: "eq", value: "Active" },
          { field: "total_payable", op: "gt", value: "0" },
          { field: "tax_type", op: "in", value: "VAT,Non-VAT" },
          { field: "name", op: "equals", value: "Acme" },
        ],
      }),
    ).toEqual([
      { field: "status", op: "eq", value: "Active" },
      { field: "total_payable", op: "gt", value: "0" },
      { field: "tax_type", op: "in", value: "VAT,Non-VAT" },
      { field: "name", op: "eq", value: "Acme" },
    ]);
  });
});

describe("relationFiltersToRecordFilterParams", () => {
  it("maps ops to nested filter params", () => {
    expect(
      relationFiltersToRecordFilterParams([
        { field: "status", op: "neq", value: "Inactive" },
        { field: "amount", op: "lt", value: "100" },
        { field: "code", op: "in", value: "A,B" },
      ]),
    ).toEqual({
      status: { neq: "Inactive" },
      amount: { lt: "100" },
      code: { in: "A,B" },
    });
  });
});

describe("relationFilterFieldOptions", () => {
  it("includes builtins and custom fields", () => {
    const fields = [
      { name: "supplier_code", label: "Supplier Code", is_system_field: false },
      { name: "actions", label: "Actions", is_system_field: true },
      { name: "status", label: "Status", is_system_field: true },
    ] as DynField[];

    expect(relationFilterFieldOptions(fields).map((f) => f.name)).toEqual([
      "id",
      "status",
      "title",
      "supplier_code",
    ]);
  });
});
