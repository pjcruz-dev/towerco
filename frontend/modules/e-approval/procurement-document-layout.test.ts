import { describe, expect, it } from "vitest";

import {
  ensurePurchaseOrderFieldLayouts,
  ensurePurchaseRequisitionFieldLayouts,
  isProcurementDocumentForm,
  PO_ROW_IDS,
  PR_ROW_IDS,
} from "@/modules/e-approval/procurement-document-layout";
import { buildPurchaseOrderTemplateFields } from "@/modules/e-approval/purchase-order-template";
import { buildPurchaseRequisitionTemplateFields } from "@/modules/e-approval/purchase-requisition-template";
import { parseFieldLayout, patchFieldLayout } from "@/modules/e-approval/field-layout";

describe("procurement document layout", () => {
  it("detects purchase order forms", () => {
    const { fields, metadata } = buildPurchaseOrderTemplateFields(1, new Set());
    expect(isProcurementDocumentForm(fields, metadata)).toBe(true);
  });

  it("assigns 2-column parties and 4-column order rows", () => {
    const { fields, metadata } = buildPurchaseOrderTemplateFields(1, new Set());
    const laidOut = ensurePurchaseOrderFieldLayouts(fields, metadata);

    const supplier = laidOut.find((field) => field.name === "supplier");
    const shipTo = laidOut.find((field) => field.name === "ship_to");
    const delivery = laidOut.find((field) => field.name === "delivery_date");
    const exchange = laidOut.find((field) => field.name === "exchange_rate");

    expect(parseFieldLayout(supplier!).row_id).toBe(PO_ROW_IDS.parties);
    expect(parseFieldLayout(shipTo!).row_id).toBe(PO_ROW_IDS.parties);
    expect(parseFieldLayout(delivery!).row_id).toBe(PO_ROW_IDS.orderMeta);
    expect(parseFieldLayout(exchange!).row_columns).toBe(4);
  });

  it("backfills layouts for legacy PO forms without layout options", () => {
    const legacy = buildPurchaseOrderTemplateFields(1, new Set()).fields.map((field) => ({
      ...field,
      options: field.type === "grid" ? field.options : undefined,
    }));

    const laidOut = ensurePurchaseOrderFieldLayouts(legacy, { print_template_kind: "purchase_order" });
    expect(parseFieldLayout(laidOut.find((field) => field.name === "supplier")!).row_id).toBe(PO_ROW_IDS.parties);
  });

  it("preserves designer row layouts for purchase requisition fields", () => {
    const { fields, metadata } = buildPurchaseRequisitionTemplateFields(1, new Set());
    const customRowId = "row_custom_quotes_urgency";

    const customized = fields.map((field) => {
      if (field.name === "quotes") {
        return {
          ...field,
          options: patchFieldLayout(field, {
            row_id: customRowId,
            slot: 0,
            row_columns: 2,
            width: "half",
          }),
        };
      }
      if (field.name === "urgency") {
        return {
          ...field,
          options: patchFieldLayout(field, {
            row_id: customRowId,
            slot: 1,
            row_columns: 2,
            width: "half",
          }),
        };
      }
      if (field.name === "department") {
        return {
          ...field,
          options: patchFieldLayout(field, {
            row_id: undefined,
            slot: undefined,
            width: "full",
          }),
        };
      }
      return field;
    });

    const laidOut = ensurePurchaseRequisitionFieldLayouts(customized, metadata);

    expect(parseFieldLayout(laidOut.find((field) => field.name === "quotes")!).row_id).toBe(customRowId);
    expect(parseFieldLayout(laidOut.find((field) => field.name === "urgency")!).row_id).toBe(customRowId);
    expect(parseFieldLayout(laidOut.find((field) => field.name === "department")!).row_id).toBeUndefined();
    expect(parseFieldLayout(laidOut.find((field) => field.name === "requisition_title")!).row_id).toBe(PR_ROW_IDS.header);
  });
});
