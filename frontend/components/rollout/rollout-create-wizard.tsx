"use client";

import Link from "next/link";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { useMemo, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { RolloutGeographySelect, suggestedTerritoryForRegion } from "@/components/rollout/rollout-geography-select";
import { SiteProfileLocationFields } from "@/components/rollout/site-profile-location-fields";
import { Button, buttonVariants } from "@/components/ui/button";
import { RefreshingHint } from "@/components/ui/refreshing-hint";
import { getErrorMessage } from "@/lib/api/error";
import { createRollout, fetchRolloutGeographyLookups, fetchRolloutPlaybookStatus } from "@/lib/api/modules/rollout-api";
import { fetchProjectOneProjectsIndex } from "@/lib/api/modules/project-one-api";
import { useNotificationStore } from "@/stores/notification-store";
import { cn } from "@/lib/utils";

const mnoOptions = [
  { value: "globe", label: "Globe" },
  { value: "smart", label: "Smart" },
  { value: "dito", label: "DITO" },
] as const;

const projectTypeOptions = [
  { value: "bts", label: "BTS (115 working days delivery)" },
  { value: "rtb", label: "RTB (85 working days delivery)" },
  { value: "colocation", label: "Colocation (30 working days delivery)" },
] as const;

const steps = [
  { id: 1, title: "Site & MNO", showMnoLabel: true },
  { id: 2, title: "Program" },
  { id: 3, title: "Playbook" },
  { id: 4, title: "Create" },
] as const;

type Props = {
  initialProjectId?: string;
};

export function RolloutCreateWizard({ initialProjectId = "" }: Props) {
  const router = useRouter();
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [step, setStep] = useState(1);
  const [mno, setMno] = useState<(typeof mnoOptions)[number]["value"]>("globe");
  const [projectType, setProjectType] = useState<(typeof projectTypeOptions)[number]["value"]>("bts");
  const [endorsementRef, setEndorsementRef] = useState("");
  const [endorsementDate, setEndorsementDate] = useState("");
  const [searchRing, setSearchRing] = useState("");
  const [region, setRegion] = useState("");
  const [territory, setTerritory] = useState("");
  const [fullAddress, setFullAddress] = useState("");
  const [latitude, setLatitude] = useState("");
  const [longitude, setLongitude] = useState("");
  const [projectId, setProjectId] = useState(initialProjectId);

  const projectsQuery = useQuery({
    queryKey: ["project-one", "projects", "rollout-new"],
    queryFn: () => fetchProjectOneProjectsIndex({ page: 1, per_page: 100 }),
  });
  const playbookQuery = useQuery({
    queryKey: ["project-one", "rollout-playbook"],
    queryFn: fetchRolloutPlaybookStatus,
    enabled: step >= 3,
  });
  const regionsQuery = useQuery({
    queryKey: ["project-one", "geography", "region", "active"],
    queryFn: () => fetchRolloutGeographyLookups({ kind: "region", activeOnly: true }),
  });
  const territoriesQuery = useQuery({
    queryKey: ["project-one", "geography", "territory", "active"],
    queryFn: () => fetchRolloutGeographyLookups({ kind: "territory", activeOnly: true }),
  });

  const projects = projectsQuery.data?.data ?? [];
  const playbook = playbookQuery.data;

  const regionLabel = useMemo(() => {
    const row = regionsQuery.data?.items.find((item) => item.code === region);
    return row ? `${row.code} — ${row.label}` : region;
  }, [region, regionsQuery.data?.items]);

  const territoryLabel = useMemo(() => {
    if (!territory) return "";
    const row = territoriesQuery.data?.items.find((item) => item.code === territory);
    return row ? `${row.code} — ${row.label}` : territory;
  }, [territory, territoriesQuery.data?.items]);

  const handleRegionChange = (code: string) => {
    setRegion(code);
    const suggested = suggestedTerritoryForRegion(code);
    if (suggested && (!territory || territory === "NCR")) {
      setTerritory(suggested);
    }
  };

  const slaPreview = useMemo(() => {
    const template = playbook?.delivery_periods?.[projectType];
    if (template?.working_days) {
      return `${template.working_days} working days (from assigned playbook)`;
    }
    const fallback: Record<string, number> = { bts: 120, rtb: 85, colocation: 30 };
    return `~${fallback[projectType]} working days (default for ${projectType.toUpperCase()})`;
  }, [playbook?.delivery_periods, projectType]);

  const coordinateError = useMemo(() => {
    const lat = latitude.trim();
    const lng = longitude.trim();
    if (lat === "" && lng === "") {
      return null;
    }
    if (lat === "" || lng === "") {
      return "Latitude and longitude must both be provided or left empty.";
    }
    const latNum = Number(lat);
    const lngNum = Number(lng);
    if (!Number.isFinite(latNum) || latNum < -90 || latNum > 90) {
      return "Latitude must be a number between -90 and 90.";
    }
    if (!Number.isFinite(lngNum) || lngNum < -180 || lngNum > 180) {
      return "Longitude must be a number between -180 and 180.";
    }
    return null;
  }, [latitude, longitude]);

  const mutation = useMutation({
    mutationFn: () => {
      const payload: Parameters<typeof createRollout>[0] = {
        mno,
        project_type: projectType,
        project_id: projectId || undefined,
        endorsement_ref: endorsementRef.trim() || undefined,
        endorsement_date: endorsementDate.trim() || undefined,
        search_ring_name: searchRing.trim() || undefined,
        region: region.trim() || undefined,
        territory: territory.trim() || undefined,
      };

      if (fullAddress.trim()) {
        payload.full_address = fullAddress.trim();
      }
      if (latitude.trim() && longitude.trim()) {
        payload.latitude = Number(latitude);
        payload.longitude = Number(longitude);
      }

      return createRollout(payload);
    },
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      queryClient.invalidateQueries({ queryKey: ["project-one", "dashboard"] });
      push({ level: "success", title: "Rollout created", message: data.rollout_ref });
      router.push(`/project-one/rollouts/${data.id}`);
    },
    onError: (error) => {
      push({ level: "error", title: "Could not create rollout", message: getErrorMessage(error) });
    },
  });

  function canAdvance(): boolean {
    if (step === 1) {
      return region.trim().length > 0 && coordinateError === null;
    }
    if (step === 2) {
      return searchRing.trim().length >= 2;
    }
    return true;
  }

  return (
    <div className="space-y-6">
      <nav aria-label="Rollout creation steps" className="flex flex-wrap gap-2">
        {steps.map((s) => (
          <div
            key={s.id}
            className={cn(
              "rounded-md border px-3 py-1.5 text-xs font-medium",
              step === s.id
                ? "border-primary bg-primary/10 text-primary"
                : step > s.id
                  ? "border-border text-muted-foreground"
                  : "border-dashed border-border text-muted-foreground",
            )}
          >
            <span className="tabular-nums">{s.id}.</span>{" "}
            {"showMnoLabel" in s && s.showMnoLabel ? (
              <>
                Site & <AcronymLabel term="MNO" />
              </>
            ) : (
              s.title
            )}
          </div>
        ))}
      </nav>

      <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
        {step === 1 ? (
          <div className="space-y-4">
            <div>
              <h2 className="text-base font-medium text-foreground">
                Site & <AcronymLabel term="MNO" />
              </h2>
              <p className="mt-1 text-sm text-muted-foreground">
                <AcronymText text="Carrier, program type, and territory drive SLA holiday calendars. Region is the PSA admin code." />
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">
                  <AcronymLabel term="MNO" />
                </span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={mno}
                  onChange={(e) => setMno(e.target.value as typeof mno)}
                >
                  {mnoOptions.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="space-y-1.5 text-sm">
                <span className="font-medium text-foreground">Project type</span>
                <select
                  className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                  value={projectType}
                  onChange={(e) => setProjectType(e.target.value as typeof projectType)}
                >
                  {projectTypeOptions.map((opt) => (
                    <option key={opt.value} value={opt.value}>
                      {opt.label}
                    </option>
                  ))}
                </select>
              </label>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <RolloutGeographySelect
                kind="region"
                label="Region"
                value={region}
                onChange={handleRegionChange}
                required
              />
              <RolloutGeographySelect
                kind="territory"
                label="Territory (optional)"
                value={territory}
                onChange={setTerritory}
              />
            </div>

            <div className="space-y-3">
              <div>
                <h3 className="text-sm font-medium text-foreground">Site profile</h3>
                <p className="mt-1 text-xs text-muted-foreground">
                  Canonical site address and coordinates. Optional — you can update these later on the rollout page.
                </p>
              </div>
              <SiteProfileLocationFields
                fullAddress={fullAddress}
                latitude={latitude}
                longitude={longitude}
                onFullAddressChange={setFullAddress}
                onLatitudeChange={setLatitude}
                onLongitudeChange={setLongitude}
                coordinateError={coordinateError}
                hint="Locate from address, or fill the address from coordinates."
              />
            </div>
          </div>
        ) : null}

        {step === 2 ? (
          <div className="space-y-4">
            <div>
              <h2 className="text-base font-medium text-foreground">Program</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                <AcronymText text="Link to a project and name the search ring — one MNO endorsement becomes one rollout program." />
              </p>
            </div>
            <FormInput
              label="Search ring name"
              placeholder="e.g. Quezon North Ring A"
              value={searchRing}
              onChange={(e) => setSearchRing(e.target.value)}
              required
            />
            <FormInput
              label="Endorsement reference (optional)"
              placeholder="e.g. GLO-END-2026-0142"
              value={endorsementRef}
              onChange={(e) => setEndorsementRef(e.target.value)}
            />
            <FormInput
              label="Endorsement date (optional)"
              date
              value={endorsementDate}
              onChange={(e) => setEndorsementDate(e.target.value)}
            />
            <p className="text-xs text-muted-foreground">
              If set now, Site Tracker enrolment completes on create. You can also set it on the rollout
              timeline after creation.
            </p>
            <label className="space-y-1.5 text-sm">
              <span className="font-medium text-foreground">Linked project (optional)</span>
              <select
                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={projectId}
                onChange={(e) => setProjectId(e.target.value)}
              >
                <option value="">No project</option>
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>
                    {project.name}
                    {project.site ? ` · ${project.site.site_code}` : ""}
                  </option>
                ))}
              </select>
            </label>
          </div>
        ) : null}

        {step === 3 ? (
          <div className="space-y-4">
            <div>
              <h2 className="text-base font-medium text-foreground">Playbook preview</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                <AcronymText text="Timeline and SLA come from the tenant rollout playbook assigned at create time." />
              </p>
            </div>
            {playbookQuery.isFetching ? (
              <RefreshingHint label="Loading playbook" />
            ) : playbook ? (
              <dl className="grid gap-3 rounded-lg border border-border bg-muted/30 p-4 text-sm sm:grid-cols-2">
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">Assigned version</dt>
                  <dd className="font-medium text-foreground">{playbook.assigned_version ?? "Not assigned"}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">
                    <AcronymLabel term="SLA">SLA for this type</AcronymLabel>
                  </dt>
                  <dd className="font-medium text-foreground">{slaPreview}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">
                    <AcronymLabel term="SLA">SLA basis</AcronymLabel>
                  </dt>
                  <dd>{playbook.sla_working_days_only ? "Working days only" : "Calendar days"}</dd>
                </div>
                <div>
                  <dt className="text-xs font-medium text-muted-foreground">PH holidays</dt>
                  <dd>
                    {playbook.public_holidays_count
                      ? `${playbook.public_holidays_count} dates loaded`
                      : "None seeded — check Holidays"}
                  </dd>
                </div>
              </dl>
            ) : (
              <p className="text-sm text-amber-700 dark:text-amber-200">
                <AcronymText text="Playbook status unavailable. You can still create; SLA will use server defaults." />
              </p>
            )}
            <p className="text-xs text-muted-foreground">
              <Link className="text-primary underline-offset-4 hover:underline" href="/project-one/rollout-playbook">
                Open playbook settings
              </Link>{" "}
              to review gate policies and overrides.
            </p>
          </div>
        ) : null}

        {step === 4 ? (
          <div className="space-y-4">
            <div>
              <h2 className="text-base font-medium text-foreground">Review & create</h2>
              <p className="mt-1 text-sm text-muted-foreground">Confirm details before starting the rollout program.</p>
            </div>
            <ul className="space-y-2 rounded-lg border border-border bg-muted/20 p-4 text-sm">
              <li>
                <span className="text-muted-foreground">
                  <AcronymLabel term="MNO" /> / type:
                </span>{" "}
                <span className="font-medium uppercase text-foreground">
                  {mno} · {projectType}
                </span>
              </li>
              <li>
                <span className="text-muted-foreground">Region:</span>{" "}
                <span className="font-medium text-foreground">
                  {regionLabel}
                  {territoryLabel ? ` · ${territoryLabel}` : ""}
                </span>
              </li>
              <li>
                <span className="text-muted-foreground">Search ring:</span>{" "}
                <span className="font-medium text-foreground">{searchRing}</span>
              </li>
              {endorsementRef || endorsementDate ? (
                <li>
                  <span className="text-muted-foreground">Endorsement:</span>{" "}
                  <span className="font-mono text-foreground">
                    {[endorsementDate || null, endorsementRef || null].filter(Boolean).join(" · ")}
                  </span>
                </li>
              ) : (
                <li>
                  <span className="text-muted-foreground">Endorsement:</span>{" "}
                  <span className="text-foreground">Set after create on timeline</span>
                </li>
              )}
              {projectId ? (
                <li>
                  <span className="text-muted-foreground">Project:</span>{" "}
                  <span className="font-medium text-foreground">
                    {projects.find((p) => p.id === projectId)?.name ?? projectId}
                  </span>
                </li>
              ) : null}
              {fullAddress.trim() ? (
                <li>
                  <span className="text-muted-foreground">Full address:</span>{" "}
                  <span className="font-medium text-foreground">{fullAddress.trim()}</span>
                </li>
              ) : null}
              {latitude.trim() && longitude.trim() ? (
                <li>
                  <span className="text-muted-foreground">Coordinates:</span>{" "}
                  <span className="font-mono text-foreground">
                    {latitude.trim()}, {longitude.trim()}
                  </span>
                </li>
              ) : null}
              <li>
                <span className="text-muted-foreground">
                  <AcronymLabel term="SLA" />:
                </span>{" "}
                <span className="font-medium text-foreground">{slaPreview}</span>
              </li>
            </ul>
          </div>
        ) : null}

        <div className="mt-6 flex flex-wrap justify-between gap-2 border-t border-border pt-4">
          <div>
            {step > 1 ? (
              <Button type="button" variant="outline" onClick={() => setStep((s) => s - 1)}>
                Back
              </Button>
            ) : (
              <Link href="/project-one/rollouts" className={buttonVariants({ variant: "outline" })}>
                Cancel
              </Link>
            )}
          </div>
          <div className="flex gap-2">
            {step < 4 ? (
              <Button type="button" disabled={!canAdvance()} onClick={() => setStep((s) => s + 1)}>
                Continue
              </Button>
            ) : (
              <Button
                type="button"
                disabled={mutation.isPending || coordinateError !== null}
                onClick={() => mutation.mutate()}
              >
                {mutation.isPending ? "Creating…" : "Create rollout"}
              </Button>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
