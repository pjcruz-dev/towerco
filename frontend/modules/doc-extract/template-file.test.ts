import { describe, expect, it } from "vitest";

import {
  buildDocExtractTemplateFile,
  parseDocExtractTemplateFile,
  uniqueTemplateName,
} from "@/modules/doc-extract/template-file";
import type { DocExtractTemplate } from "@/modules/doc-extract/types";

const sample: DocExtractTemplate = {
  id: "template-1",
  name: "INEC electric bill",
  description: "Statements",
  status: "published",
  fields: [
    {
      key: "inec_bills",
      label: "INEC Bills",
      type: "table",
      hint: "STATEMENT OF ACCOUNT",
      columns: [
        { key: "account_number", label: "Account Number", type: "text" },
        { key: "amount", label: "Amount", type: "currency" },
      ],
    },
  ],
};

describe("doc extract template file", () => {
  it("round-trips a published table template without the tenant id", () => {
    const file = buildDocExtractTemplateFile(sample);
    const parsed = parseDocExtractTemplateFile(JSON.stringify(file));

    expect(file).not.toHaveProperty("id");
    expect(parsed).toEqual({
      name: "INEC electric bill",
      description: "Statements",
      status: "published",
      fields: [
        {
          key: "inec_bills",
          label: "INEC Bills",
          type: "table",
          description: null,
          hint: "STATEMENT OF ACCOUNT",
          columns: [
            { key: "account_number", label: "Account Number", type: "text", description: null },
            { key: "amount", label: "Amount", type: "currency", description: null },
          ],
        },
      ],
    });
  });

  it("rejects a file that is not a template", () => {
    expect(() => parseDocExtractTemplateFile('{"kind":"other","name":"X","fields":[{"label":"A"}]}')).toThrow(
      /not a DocExtract template/,
    );
    expect(() => parseDocExtractTemplateFile("{")).toThrow(/not valid JSON/);
    expect(() => parseDocExtractTemplateFile('{"name":"X","fields":[]}')).toThrow(/at least one field/);
  });

  it("renames an import when that name is already in the catalog", () => {
    expect(uniqueTemplateName("INEC electric bill", ["Other"])).toBe("INEC electric bill");
    expect(uniqueTemplateName("INEC electric bill", ["INEC electric bill"])).toBe(
      "INEC electric bill (imported)",
    );
    expect(
      uniqueTemplateName("INEC electric bill", ["INEC electric bill", "INEC electric bill (imported)"]),
    ).toBe("INEC electric bill (imported 2)");
  });
});
