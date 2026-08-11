import type { EApprovalBuilderLayoutRow } from "@/modules/e-approval/builder-layout-rows";
import { patchFieldLayout, type EApprovalLayoutRowColumns } from "@/modules/e-approval/field-layout";
import type { EApprovalFormFieldInput } from "@/modules/e-approval/types";

/** Canonical TSSR Site Survey field names used by Option B layout. */
export const TSSR_OPTION_B_FIELD_NAMES = {
  section: "b_site_survey_report",
  distance: "a_distance_from_shoreline",
  structure: "b_type_and_height_of_structure",
  buildingMounted: "building_mounted_produce_as_built_drawing_check_structural_stability_of_building_seek_structural",
  roof: "roof",
  attachment: "c_type_of_attachment_anchorage",
  topographic: "c1_topographic_condition_of_the_site",
  rolling: "c2_if_the_site_is_rolling_or_mountains_does_it_require",
  floors: "floors",
  instructions: "instructions",
  dividerBasics: "tssr_divider_site_basics",
  dividerStructure: "tssr_divider_structure",
  dividerComponents: "tssr_divider_building_components",
  dividerTerrain: "tssr_divider_terrain",
  dividerFloors: "tssr_divider_floors",
  sectionSiteOptions: "b_site_options_justifications",
  siteOptionsMap: "b_site_options_map",
  siteOptionsGrid: "b_site_options_and_justifications",
  sectionB1Photos: "b1_site_development_photos",
  panoramicPhotos: "b1b_panoramic_site_photos",
  photoTowerGreenfield: "b1c_proposed_tower_location_greenfield",
  photoEquipmentRooftop: "b1d_proposed_equipment_modified_room_rooftop",
  photoKwhrMeter: "b1e_proposed_kwhr_meter_ecb_tapping_point",
  photoElectricalFacilities: "b1f_nearest_existing_electrical_facilities",
  dividerGoingToSite: "tssr_divider_going_to_proposed_site",
  goingToSiteApproach: "going_to_proposed_site_approach",
  dividerGoingToSiteCaption: "tssr_divider_going_to_proposed_site_caption",
  goingToSiteLocation: "going_to_proposed_site_location",
} as const;

const ROW_BASICS = "row_tssr_site_basics";
const ROW_STRUCTURE = "row_tssr_structure";
const ROW_TERRAIN = "row_tssr_terrain";

function findByName(fields: EApprovalFormFieldInput[], name: string): EApprovalFormFieldInput | undefined {
  return fields.find((field) => field.name === name);
}

function withHalfRow(
  field: EApprovalFormFieldInput,
  rowId: string,
  slot: 0 | 1,
  stackOrder: number,
): EApprovalFormFieldInput {
  return {
    ...field,
    options: patchFieldLayout(field, {
      row_id: rowId,
      slot,
      stack_order: stackOrder,
      row_columns: 2,
      width: "half",
    }),
  };
}

function withFullWidth(field: EApprovalFormFieldInput): EApprovalFormFieldInput {
  return {
    ...field,
    options: patchFieldLayout(field, {
      row_id: undefined,
      slot: undefined,
      stack_order: undefined,
      width: "full",
    }),
  };
}

function dividerField(name: string, label: string, stepOrder: number): EApprovalFormFieldInput {
  return {
    type: "divider",
    name,
    label,
    step_order: stepOrder,
    options: {},
  };
}

function ensureDivider(
  fields: EApprovalFormFieldInput[],
  name: string,
  label: string,
): EApprovalFormFieldInput {
  return findByName(fields, name) ?? dividerField(name, label, 0);
}

export function formSupportsTssrOptionBLayout(fields: EApprovalFormFieldInput[]): boolean {
  const names = TSSR_OPTION_B_FIELD_NAMES;
  return Boolean(
    findByName(fields, names.distance) &&
      findByName(fields, names.structure) &&
      findByName(fields, names.attachment) &&
      findByName(fields, names.roof),
  );
}

/**
 * Option B — sectioned Site Survey layout:
 * 1. Site basics (2-col): A | C
 * 2. Structure (2-col): B | Building Mounted
 * 3. Building components (full): ROOF
 * 4. Terrain (2-col): C1 | C2
 * 5. Floors (full): FLOORS
 * 6. Instructions (full)
 * 7. Site options + B1 photos (when present)
 */
export function applyTssrOptionBLayout(
  fields: EApprovalFormFieldInput[],
  layoutRows: EApprovalBuilderLayoutRow[] = [],
): { fields: EApprovalFormFieldInput[]; layoutRows: EApprovalBuilderLayoutRow[] } {
  const names = TSSR_OPTION_B_FIELD_NAMES;
  const sectionIndex = fields.findIndex((field) => field.name === names.section);
  if (sectionIndex < 0) {
    return { fields, layoutRows };
  }

  const required = [
    names.distance,
    names.structure,
    names.buildingMounted,
    names.roof,
    names.attachment,
    names.topographic,
    names.rolling,
    names.floors,
  ];
  const byName = new Map(fields.map((field) => [field.name, field]));
  for (const name of required) {
    if (!byName.has(name)) {
      return { fields, layoutRows };
    }
  }

  const photoBlockNames = [
    names.sectionSiteOptions,
    names.siteOptionsMap,
    names.siteOptionsGrid,
    names.sectionB1Photos,
    names.panoramicPhotos,
    names.photoTowerGreenfield,
    names.photoEquipmentRooftop,
    names.photoKwhrMeter,
    names.photoElectricalFacilities,
    names.dividerGoingToSite,
    names.goingToSiteApproach,
    names.dividerGoingToSiteCaption,
    names.goingToSiteLocation,
  ] as const;

  const consumed = new Set<string>([
    ...required,
    names.instructions,
    names.dividerBasics,
    names.dividerStructure,
    names.dividerComponents,
    names.dividerTerrain,
    names.dividerFloors,
    ...photoBlockNames,
  ]);

  const prefix = fields.slice(0, sectionIndex + 1);
  const afterSection = fields.slice(sectionIndex + 1);
  const trailing = afterSection.filter((field) => !consumed.has(field.name));

  const distance = withHalfRow(byName.get(names.distance)!, ROW_BASICS, 0, 0);
  const attachment = withHalfRow(byName.get(names.attachment)!, ROW_BASICS, 1, 0);
  const structure = withFullWidth(byName.get(names.structure)!);
  const buildingMounted = withFullWidth(byName.get(names.buildingMounted)!);
  const roof = withFullWidth(byName.get(names.roof)!);
  const topographic = withHalfRow(byName.get(names.topographic)!, ROW_TERRAIN, 0, 0);
  const rolling = withHalfRow(byName.get(names.rolling)!, ROW_TERRAIN, 1, 0);
  const floors = withFullWidth(byName.get(names.floors)!);
  const instructions = byName.has(names.instructions)
    ? withFullWidth(byName.get(names.instructions)!)
    : null;

  const photoBlock = photoBlockNames
    .map((name) => byName.get(name))
    .filter((field): field is EApprovalFormFieldInput => Boolean(field))
    .map((field) =>
      field.type === "divider" || field.type === "section" ? field : withFullWidth(field),
    );

  const block: EApprovalFormFieldInput[] = [
    ensureDivider(fields, names.dividerBasics, "Site basics"),
    distance,
    attachment,
    ensureDivider(fields, names.dividerStructure, "Structure"),
    structure,
    buildingMounted,
    ensureDivider(fields, names.dividerComponents, "Building components"),
    roof,
    ensureDivider(fields, names.dividerTerrain, "Terrain & access conditions"),
    topographic,
    rolling,
    ensureDivider(fields, names.dividerFloors, "Floors / as-built"),
    floors,
    ...(instructions ? [instructions] : []),
    ...photoBlock,
  ];

  const nextFields = [...prefix, ...block, ...trailing].map((field, index) => ({
    ...field,
    step_order: index + 1,
  }));

  const indexOf = (name: string): number => nextFields.findIndex((field) => field.name === name);
  const nextLayoutRows: EApprovalBuilderLayoutRow[] = [
    ...layoutRows.filter(
      (row) =>
        row.id !== ROW_BASICS &&
        row.id !== ROW_STRUCTURE &&
        row.id !== ROW_TERRAIN &&
        row.id !== "row_ms5f1ohq" &&
        row.id !== "row_ms5mucbp",
    ),
    { id: ROW_BASICS, columns: 2 as EApprovalLayoutRowColumns, insert_index: indexOf(names.distance) },
    { id: ROW_TERRAIN, columns: 2 as EApprovalLayoutRowColumns, insert_index: indexOf(names.topographic) },
  ].sort((a, b) => a.insert_index - b.insert_index || a.id.localeCompare(b.id));

  return { fields: nextFields, layoutRows: nextLayoutRows };
}
