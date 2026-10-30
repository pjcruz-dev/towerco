import { describe, expect, it } from "vitest";

import {
  buildOrgChartIndex,
  collectOrgFilterOptions,
  filterOrgChartIndex,
  filterOrgPeople,
  ORG_CHART_NO_DEPARTMENT,
  orgChartRoots,
  personInitials,
  pickDefaultFocus,
  resolveManager,
} from "./org-chart";

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
  it("nests INFRA SUITE reports and external Entra managers", () => {
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

  it("shows an Entra-only manager above a person with no INFRA SUITE manager_id", () => {
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

  it("collapses department spellings that differ only by and, ampersand, or case", () => {
    const index = buildOrgChartIndex([
      {
        id: "ops-and",
        name: "Ops And",
        email: "ops-and@example.com",
        job_title: "Lead",
        department: "Operations and Maintenance",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "ops-amp",
        name: "Ops Amp",
        email: "ops-amp@example.com",
        job_title: "Tech",
        department: "Operations & Maintenance",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "sag-lower",
        name: "Sag Lower",
        email: "sag-lower@example.com",
        job_title: "Lead",
        department: "SAG and Lessor Management",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "sag-upper",
        name: "Sag Upper",
        email: "sag-upper@example.com",
        job_title: "Analyst",
        department: "SAG And Lessor Management",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "hr",
        name: "Hr Person",
        email: "hr@example.com",
        job_title: "HR",
        department: "Human Resources",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "hr-admin",
        name: "Hr Admin",
        email: "hr-admin@example.com",
        job_title: "HR Admin",
        department: "Human Resources and Admin",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "eng",
        name: "Eng Person",
        email: "eng@example.com",
        job_title: "Engineer",
        department: "Engineering & Design",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
    ]);

    expect(index.nodes.map((node) => node.id).sort()).toEqual([
      "eng",
      "hr",
      "hr-admin",
      "ops-amp",
      "ops-and",
      "sag-lower",
      "sag-upper",
    ]);
    expect(collectOrgFilterOptions(index.nodes).departments).toEqual([
      "Engineering & Design",
      "Human Resources",
      "Human Resources and Admin",
      "Operations and Maintenance",
      "SAG and Lessor Management",
    ]);
    expect(index.byId.get("ops-amp")?.department).toBe("Operations & Maintenance");
    expect(index.byId.get("ops-and")?.department).toBe("Operations and Maintenance");
    expect(index.byId.get("sag-upper")?.department).toBe("SAG And Lessor Management");
    expect(index.byId.get("sag-lower")?.department).toBe("SAG and Lessor Management");
    expect(index.byId.get("hr")?.department).toBe("Human Resources");
    expect(index.byId.get("hr-admin")?.department).toBe("Human Resources and Admin");
    expect(index.byId.get("eng")?.department).toBe("Engineering & Design");

    const filtered = filterOrgChartIndex(index, {
      department: "Operations and Maintenance",
      license: "",
    });
    expect([...filtered.byId.keys()].sort()).toEqual(["ops-amp", "ops-and"]);
    expect(filtered.byId.get("ops-amp")?.department).toBe("Operations & Maintenance");
    expect(filtered.byId.get("ops-and")?.department).toBe("Operations and Maintenance");

    const hrOnly = filterOrgChartIndex(index, {
      department: "Human Resources",
      license: "",
    });
    expect([...hrOnly.byId.keys()]).toEqual(["hr"]);
  });

  it("keeps the department spelling used by more people", () => {
    const index = buildOrgChartIndex([
      {
        id: "a",
        name: "Ada",
        email: "ada@example.com",
        job_title: "Lead",
        department: "Operations & Maintenance",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "b",
        name: "Ben",
        email: "ben@example.com",
        job_title: "Tech",
        department: "Operations & Maintenance",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "c",
        name: "Cara",
        email: "cara@example.com",
        job_title: "Tech",
        department: "Operations and Maintenance",
        manager_id: null,
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
    ]);

    expect(collectOrgFilterOptions(index.nodes).departments).toEqual(["Operations & Maintenance"]);
    expect(index.nodes).toHaveLength(3);
    expect(index.byId.get("a")?.department).toBe("Operations & Maintenance");
    expect(index.byId.get("b")?.department).toBe("Operations & Maintenance");
    expect(index.byId.get("c")?.department).toBe("Operations and Maintenance");

    const filtered = filterOrgChartIndex(index, {
      department: "Operations & Maintenance",
      license: "",
    });
    expect([...filtered.byId.keys()].sort()).toEqual(["a", "b", "c"]);
    expect(filtered.byId.get("c")?.department).toBe("Operations and Maintenance");
  });

  it("collects filter options and keeps ancestors when filtering", () => {
    const index = buildOrgChartIndex(filterPeople);
    const options = collectOrgFilterOptions(index.nodes);
    expect(options.departments).toEqual(["Engineering", "Finance"]);
    expect(options.hasUnassignedDepartment).toBe(false);

    const filtered = filterOrgChartIndex(index, {
      department: "Engineering",
      license: "",
    });
    expect(filtered.byId.has("a")).toBe(true);
    expect(filtered.byId.has("b")).toBe(true);
    expect(filtered.byId.has("c")).toBe(false);
  });

  it("inherits blank departments from ancestors in the manager chain", () => {
    const people = [
      {
        id: "demetrio",
        name: "Demetrio Pilar",
        email: "dpilar@example.com",
        job_title: "Director",
        department: "Project Implementation",
        manager_id: null,
        manager_name: null,
        direct_report_count: 1,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "christopher",
        name: "Christopher Emmanuel Agorto",
        email: "ccagorto@example.com",
        job_title: null,
        department: null,
        manager_id: "demetrio",
        manager_name: null,
        direct_report_count: 2,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "arvin",
        name: "Arvin John Gervacio",
        email: "afgervacio@example.com",
        job_title: null,
        department: null,
        manager_id: "christopher",
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Standard",
        roles: [],
      },
      {
        id: "jerry",
        name: "Jerry Balino",
        email: "jdbalino@example.com",
        job_title: null,
        department: null,
        manager_id: "christopher",
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Standard",
        roles: [],
      },
    ];

    const index = buildOrgChartIndex(people);
    expect(index.byId.get("christopher")?.department).toBe("Project Implementation");
    expect(index.byId.get("arvin")?.department).toBe("Project Implementation");
    expect(index.byId.get("jerry")?.department).toBe("Project Implementation");
    expect(collectOrgFilterOptions(index.nodes).hasUnassignedDepartment).toBe(false);
  });

  it("keeps descendants of department matches even when they have no department", () => {
    const people = [
      {
        id: "terrence",
        name: "Terrence Galang",
        email: "terrence@example.com",
        job_title: "Lead",
        department: "Technology and Quality Governance",
        manager_id: "alvin",
        manager_name: null,
        direct_report_count: 2,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "alvin",
        name: "Alvin Tolentino",
        email: "alvin@example.com",
        job_title: "Director",
        department: "Executive Office",
        manager_id: null,
        manager_name: null,
        direct_report_count: 1,
        license_label: "Business Standard",
        roles: [],
      },
      {
        id: "denver",
        name: "Denver Arquiza",
        email: "denver@example.com",
        job_title: "Engineer",
        department: "Technology and Quality Governance",
        manager_id: "terrence",
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Premium",
        roles: [],
      },
      {
        id: "jerico",
        name: "Jerico Fortuna",
        email: "jerico@example.com",
        job_title: "ISO Officer",
        department: null,
        manager_id: "terrence",
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Standard +1",
        roles: [],
      },
      {
        id: "outsider",
        name: "Other Team",
        email: "other@example.com",
        job_title: "Analyst",
        department: "Finance",
        manager_id: "alvin",
        manager_name: null,
        direct_report_count: 0,
        license_label: "Business Standard",
        roles: [],
      },
    ];

    const index = buildOrgChartIndex(people);
    // Jerico inherits Terrence's department for display; filter still keeps the team under Terrence.
    expect(index.byId.get("jerico")?.department).toBe("Technology and Quality Governance");
    expect(collectOrgFilterOptions(index.nodes).hasUnassignedDepartment).toBe(false);

    const filtered = filterOrgChartIndex(index, {
      department: "Technology and Quality Governance",
      license: "",
    });
    expect(filtered.byId.has("alvin")).toBe(true);
    expect(filtered.byId.has("terrence")).toBe(true);
    expect(filtered.byId.has("denver")).toBe(true);
    expect(filtered.byId.has("jerico")).toBe(true);
    expect(filtered.byId.has("outsider")).toBe(false);
    expect(filtered.reports.get("terrence")?.map((n) => n.id).sort()).toEqual(["denver", "jerico"]);

    const unassigned = filterOrgChartIndex(index, {
      department: ORG_CHART_NO_DEPARTMENT,
      license: "",
    });
    expect(unassigned.byId.size).toBe(0);
  });

  it("searches role names in people search", () => {
    const index = buildOrgChartIndex(filterPeople);
    expect(filterOrgPeople(index.nodes, "tenant_admin").map((n) => n.id)).toEqual(["a"]);
  });
});
