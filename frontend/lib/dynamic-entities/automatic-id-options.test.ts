import { describe, expect, it } from "vitest";

import {
  parseDynAutomaticIdOptions,
  serializeDynAutomaticIdOptions,
} from "@/lib/dynamic-entities/automatic-id-options";

describe("automatic-id-options", () => {
  it("parses Metacoresoft-style prefix and format", () => {
    expect(
      parseDynAutomaticIdOptions({ id_prefix: "2307-", id_format: "Y-####" }),
    ).toEqual({ id_prefix: "2307-", id_format: "Y-####" });
  });

  it("serializes defaults", () => {
    expect(serializeDynAutomaticIdOptions({ id_prefix: "", id_format: "" })).toEqual({
      id_format: "####",
    });
    expect(
      serializeDynAutomaticIdOptions({ id_prefix: "2307-", id_format: "Y-####" }),
    ).toEqual({ id_prefix: "2307-", id_format: "Y-####" });
  });
});
