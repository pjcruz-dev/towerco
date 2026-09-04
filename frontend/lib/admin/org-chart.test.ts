import { describe, expect, it } from "vitest";

import { buildOrgChartIndex, collectOrgFilterOptions, filterOrgChartIndex, filterOrgPeople, orgChartRoots, personInitials, pickDefaultFocus, resolveManager } from "./org-chart";

const people = [
  {
    id: "alvin",
    name: "Alvin Tolentino",
    email: "alvin@example.com",
    job_title: "Director",
    manager_id: null,
    manager_name: null,
    direct_report_count: 1,
  },
  {
    id: "terrence",
    name: "Terrence Galang",
    email: "terrence@example.com",
    job_title: "Engineer",
    manager_id: "alvin",
    manager_name: null,
    direct_report_count: 0,
  },
  {
    id: "alfred",
    name: "Alfred Kevin Sapigao",
    email: "alfred@example.com",
    job_title: "Analyst",
    manager_id: null,
    manager_name: "Maria Teresa Bandiala",
    manager_licensed: true,
    manager_license_label: "E3",
    direct_report_count: 0,
  },
];

describe("buildOrgChartIndex", () => {
  it("nests TowerOS reports and external Entra managers", () => {
    const index = buildOrgChartIndex(people);
    expect(index.reports.get("alvin")?.map((person) => person.id)).toEqual(["terrence"]);
    const external = [...index.byId.values()].find((node) => node.external);
    expect(external?.name).toBe("Maria Teresa Bandiala");
    expect(index.reports.get(external!.id)?.map((person) => person.id)).toEqual(["alfred"]);
    expect(resolveManager(index, index.byId.get("alfred"))?.name).toBe("Maria Teresa Bandiala");
  });

  it("prefers the current user as default focus", () => {
    const index = buildOrgChartIndex(people);
    expect(pickDefaultFocus(index, "terrence")).toBe("terrence");
    expect(pickDefaultFocus(index, null)).toBe("alvin");
  });

  it("shows an Entra-only manager above a person with no TowerOS manager_id", () => {
    const index = buildOrgChartIndex([
      {
        id: "peter",
        name: "Peter Joseph Cruz",
        email: "prcruz@example.com",
        job_title: null,
        manager_id: null,
        manager_name: "Terrence Galang",
        manager_email: "trgalang@example.com",
        manager_licensed: true,
        manager_license_label: "E3",
        direct_report_count: 0,
      },
    ]);

    const manager = resolveManager(index, index.byId.get("peter"));
    expect(manager?.name).toBe("Terrence Galang");
    expect(manager?.email).toBe("trgalang@example.com");
    expect(manager?.external).toBe(true);
    expect(resolveManager(index, manager ?? undefined)).toBeNull();
  });

  it("copies Entra manager department onto the external manager card", () => {
    const index = buildOrgChartIndex([
      {
        id: "peter",
        name: "Peter Joseph Cruz",
        email: "prcruz@example.com",
        job_title: "IT Lead",
        department: "Technology and Quality",
        manager_id: null,
        manager_name: "Terrence Galang",
        manager_email: "trgalang@example.com",
        manager_department: "Technology and Quality",
        manager_licensed: true,
        manager_license_label: "Business Standard",
        direct_report_count: 0,
      },
    ]);

    const manager = resolveManager(index, index.byId.get("peter"));
    expect(manager?.department).toBe("Technology and Quality");
  });

  it("treats Entra-only managers as organization roots", () => {
    const index = buildOrgChartIndex([
      {
        id: "peter",
        name: "Peter Joseph Cruz",
        email: "prcruz@example.com",
        job_title: null,
        manager_id: null,
        manager_name: "Terrence Galang",
        manager_email: "trgalang@example.com",
        manager_licensed: true,
        manager_license_label: "E3",
        direct_report_count: 0,
      },
    ]);
    expect(orgChartRoots(index).map((node) => node.name)).toEqual(["Terrence Galang"]);
  });

  it("does not show an unlicensed Entra-only manager", () => {
    const index = buildOrgChartIndex([
      {
        id: "peter",
        name: "Peter Joseph Cruz",
        email: "prcruz@example.com",
        job_title: null,
        manager_id: null,
        manager_name: "Alvin Tolentino",
        manager_email: "alvin@example.com",
        manager_licensed: false,
        direct_report_count: 0,
      },
    ]);

    expect([...index.byId.values()].some((node) => node.external)).toBe(false);
    expect(orgChartRoots(index).map((node) => node.name)).toEqual(["Peter Joseph Cruz"]);
    expect(resolveManager(index, index.byId.get("peter"))).toBeNull();
  });

  it("nests an Entra-only manager under their MYAPP manager", () => {
    const index = buildOrgChartIndex([
      {
        id: "katrina",
        name: "Katrina Gaw",
        email: "kcgaw@example.com",
        job_title: "Director",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Standard",
      },
      {
        id: "neslie",
        name: "Neslie Valdez",
        email: "nvaldez@example.com",
        job_title: "Analyst",
        manager_id: null,
        manager_name: "Tranquilino Sarmiento",
        manager_email: "tmsarmiento@example.com",
        manager_licensed: true,
        manager_license_label: "Business Standard",
        manager_parent_id: "katrina",
        direct_report_count: 0,
        license_label: "Business Standard",
      },
    ]);

    const external = [...index.byId.values()].find((node) => node.external);
    expect(external?.name).toBe("Tranquilino Sarmiento");
    expect(external?.external).toBe(true);
    expect(index.reports.get("katrina")?.map((node) => node.id)).toEqual([external!.id]);
    expect(index.reports.get(external!.id)?.map((node) => node.id)).toEqual(["neslie"]);
    expect(resolveManager(index, external)?.name).toBe("Katrina Gaw");
    expect(orgChartRoots(index).map((node) => node.name)).toEqual(["Katrina Gaw"]);
  });

  it("builds initials from first and last name", () => {
    expect(personInitials("Peter Joseph Cruz")).toBe("PC");
    expect(personInitials("Admin")).toBe("AD");
  });
});

describe("org chart filters", () => {
  const filterPeople = [
    {
      id: "a",
      name: "Ada",
      email: "ada@example.com",
      job_title: "Lead",
      department: "Engineering",
      manager_id: null,
      manager_name: null,
      direct_report_count: 1,
      license_label: "Business Premium",
      roles: ["tenant_admin"],
    },
    {
      id: "b",
      name: "Ben",
      email: "ben@example.com",
      job_title: "Engineer",
      department: "Engineering",
      manager_id: "a",
      manager_name: null,
      direct_report_count: 0,
      license_label: "Business Standard",
      roles: ["viewer"],
    },
    {
      id: "c",
      name: "Cara",
      email: "cara@example.com",
      job_title: "Analyst",
      department: "Finance",
      manager_id: null,
      manager_name: null,
      direct_report_count: 0,
      license_label: "Business Premium",
      roles: ["viewer"],
    },
  ];

  it("collects filter options and keeps ancestors when filtering", () => {
    const index = buildOrgChartIndex(filterPeople);
    const options = collectOrgFilterOptions(index.nodes);
    expect(options.departments).toEqual(["Engineering", "Finance"]);

    const filtered = filterOrgChartIndex(index, {
      department: "Engineering",
      license: "",
    });
    expect(filtered.byId.has("a")).toBe(true);
    expect(filtered.byId.has("b")).toBe(true);
    expect(filtered.byId.has("c")).toBe(false);
  });

  it("searches role names in people search", () => {
    const index = buildOrgChartIndex(filterPeople);
    expect(filterOrgPeople(index.nodes, "tenant_admin").map((n) => n.id)).toEqual(["a"]);
  });
});
