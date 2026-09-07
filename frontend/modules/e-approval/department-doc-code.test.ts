import { describe, expect, it } from "vitest";

import { departmentDocCodeFromLabel } from "@/modules/e-approval/department-doc-code";

describe("departmentDocCodeFromLabel", () => {
  it("builds initials from multi-word departments", () => {
    expect(departmentDocCodeFromLabel("Engineering & Design Department")).toBe("EDD");
    expect(departmentDocCodeFromLabel("Engineering & Design")).toBe("ED");
    expect(departmentDocCodeFromLabel("Business Development")).toBe("BD");
    expect(departmentDocCodeFromLabel("Technology and Quality Governance")).toBe("TQG");
    expect(departmentDocCodeFromLabel("Finance and Accounting")).toBe("FA");
    expect(departmentDocCodeFromLabel("SAQ And Lease Management")).toBe("SLM");
  });

  it("keeps short codes and single words", () => {
    expect(departmentDocCodeFromLabel("QMS")).toBe("QMS");
    expect(departmentDocCodeFromLabel("EDD")).toBe("EDD");
    expect(departmentDocCodeFromLabel("Finance")).toBe("FINANCE");
    expect(departmentDocCodeFromLabel("HR")).toBe("HR");
  });
});
