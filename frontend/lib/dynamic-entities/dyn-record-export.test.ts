import { describe, expect, it } from "vitest";

import type { DynRecordListRow } from "@/lib/api/modules/dynamic-entities-api";

import {
  assembleExportRows,
  relationsForExport,
  rowsToCsv,
} from "@/lib/dynamic-entities/dyn-record-export";

function row(partial: Partial<DynRecordListRow> & Pick<DynRecordListRow, "id">): DynRecordListRow {
  return {
    entity_id: "entity",
    entity_slug: "tower_sites",
    status: "Active",
    title: "Site A",
    parent_record_id: null,
    columns: {},
    updated_at: null,
    created_at: null,
    ...partial,
  };
}

describe("dyn record export", () => {
  it("lists tower site relations in the export-options order", () => {
    const relations = relationsForExport([
      { entity_slug: "land_leases", foreign_field: "tower_site_id", label: "Land Leases" },
      { entity_slug: "saq_trackers", foreign_field: "tower_site_id", label: "SAQ Trackers" },
      { entity_slug: "telco_contracts", foreign_field: "tower_site_id", label: "Telco Contracts" },
    ]);
    expect(relations.map((relation) => relation.label)).toEqual([
      "SAQ Trackers",
      "Telco Contracts / Site Licences",
      "Land Leases",
    ]);
  });

  it("lists child rows under the matching parent", () => {
    const parent = row({ id: "site-1", title: "Tower 1", columns: { site_code: "T1" } });
    const child = row({
      id: "saq-1",
      entity_slug: "saq_trackers",
      title: "SAQ 1",
      parent_record_id: "site-1",
      columns: { tracker_no: "SQ-1" },
    });
    const rows = assembleExportRows({
      parents: [parent],
      parentColumns: [{ id: "title", label: "Site Name" }],
      relations: [
        { entity_slug: "saq_trackers", foreign_field: "tower_site_id", label: "SAQ Trackers" },
      ],
      childrenByRelation: { saq_trackers: [child] },
      childColumns: { saq_trackers: [{ id: "title", label: "Tracker" }] },
    });
    expect(rows).toEqual([
      ["Site Name"],
      ["Tower 1"],
      ["--- SAQ Trackers ---"],
      ["Tracker"],
      ["SAQ 1"],
      [],
    ]);
    expect(rowsToCsv([["Site Name", 'Say "hi"'], ["Tower 1", "a,b"]])).toContain('"Say ""hi"""');
  });
});
