"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useEffect, useState, type ReactNode } from "react";
import type { RolloutAssignableUser } from "@/lib/api/modules/rollout-api";

import { FormInput } from "@/components/forms/form-input";
import { AcronymLabel } from "@/components/help/acronym-label";
import { AcronymText } from "@/components/help/acronym-text";
import { RolloutGeographySelect, suggestedTerritoryForRegion } from "@/components/rollout/rollout-geography-select";
import { SiteProfileLocationFields } from "@/components/rollout/site-profile-location-fields";
import { Button } from "@/components/ui/button";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { getErrorMessage } from "@/lib/api/error";
import { fetchProjectOneProjectsIndex } from "@/lib/api/modules/project-one-api";
import {
  fetchRolloutAssignableUsers,
  patchRolloutMetadata,
  patchRolloutSiteProfile,
} from "@/lib/api/modules/rollout-api";
import type { RolloutDetail } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function RolloutMetadataEditSheet({ rolloutId, detail, open, onOpenChange }: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [searchRingName, setSearchRingName] = useState("");
  const [region, setRegion] = useState("");
  const [territory, setTerritory] = useState("");
  const [area, setArea] = useState("");
  const [allianceTag, setAllianceTag] = useState("");
  const [mnoAnchorSiteId, setMnoAnchorSiteId] = useState("");
  const [siteLicenseRemarks, setSiteLicenseRemarks] = useState("");
  const [energizationTempoDate, setEnergizationTempoDate] = useState("");
  const [rftiSignedTempoDate, setRftiSignedTempoDate] = useState("");
  const [endorsementRef, setEndorsementRef] = useState("");
  const [endorsementDate, setEndorsementDate] = useState("");
  const [saqOwnerId, setSaqOwnerId] = useState("");
  const [cmePmId, setCmePmId] = useState("");
  const [pmoOwnerId, setPmoOwnerId] = useState("");
  const [projectId, setProjectId] = useState("");
  const [fullAddress, setFullAddress] = useState("");
  const [latitude, setLatitude] = useState("");
  const [longitude, setLongitude] = useState("");

  const usersQuery = useQuery({
    queryKey: ["project-one", "assignable-users"],
    queryFn: fetchRolloutAssignableUsers,
    enabled: open,
  });
  const users = usersQuery.data ?? [];

  const projectsQuery = useQuery({
    queryKey: ["project-one", "projects", "rollout-edit", detail?.site?.id],
    queryFn: () =>
      fetchProjectOneProjectsIndex({
        page: 1,
        per_page: 100,
        site_id: detail?.site?.id,
      }),
    enabled: open,
  });
  const projects = projectsQuery.data?.data ?? [];

  useEffect(() => {
    if (!detail || !open) {
      return;
    }
    setSearchRingName(detail.search_ring_name ?? "");
    setRegion(detail.region ?? "");
    setTerritory(detail.territory ?? "");
    setArea(detail.area ?? "");
    setAllianceTag(detail.alliance_tag ?? "");
    setMnoAnchorSiteId(detail.mno_anchor_site_id ?? "");
    setSiteLicenseRemarks(detail.site_license_remarks ?? "");
    setEnergizationTempoDate(detail.energization_tempo_date ?? "");
    setRftiSignedTempoDate(detail.rfti_signed_tempo_date ?? "");
    setEndorsementRef(detail.endorsement_ref ?? "");
    setEndorsementDate(detail.endorsement_date ?? "");
    setSaqOwnerId(detail.saq_owner_id ?? "");
    setCmePmId(detail.cme_pm_id ?? "");
    setPmoOwnerId(detail.pmo_owner_id ?? "");
    setProjectId(detail.project?.id ?? "");
    setFullAddress(detail.site?.full_address ?? "");
    setLatitude(detail.site?.latitude != null ? String(detail.site.latitude) : "");
    setLongitude(detail.site?.longitude != null ? String(detail.site.longitude) : "");
  }, [detail, open]);

  const siteCoordinateError = (() => {
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
  })();

  const mutation = useMutation({
    mutationFn: async () => {
      if (siteCoordinateError) {
        throw new Error(siteCoordinateError);
      }
      await patchRolloutMetadata(rolloutId, {
        search_ring_name: searchRingName.trim() || null,
        region: region.trim() || null,
        territory: territory.trim() || null,
        area: area.trim() || null,
        alliance_tag: allianceTag.trim() || null,
        mno_anchor_site_id: mnoAnchorSiteId.trim() || null,
        site_license_remarks: siteLicenseRemarks.trim() || null,
        energization_tempo_date: energizationTempoDate.trim() || null,
        rfti_signed_tempo_date: rftiSignedTempoDate.trim() || null,
        endorsement_ref: endorsementRef.trim() || null,
        endorsement_date: endorsementDate.trim() || null,
        saq_owner_id: saqOwnerId || null,
        cme_pm_id: cmePmId || null,
        pmo_owner_id: pmoOwnerId || null,
        project_id: projectId || null,
      });

      const hasCoords = latitude.trim() !== "" && longitude.trim() !== "";
      await patchRolloutSiteProfile(rolloutId, {
        full_address: fullAddress.trim() || null,
        latitude: hasCoords ? Number(latitude.trim()) : null,
        longitude: hasCoords ? Number(longitude.trim()) : null,
      });
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
      await queryClient.invalidateQueries({ queryKey: ["project-one", "projects"] });
      push({ level: "success", title: "Rollout updated", message: "Metadata and site profile saved." });
      onOpenChange(false);
    },
    onError: (error) => {
      push({ level: "error", title: "Update failed", message: getErrorMessage(error) });
    },
  });

  const locked = detail?.status === "completed" || detail?.status === "cancelled" || detail?.is_batch;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader>
          <SheetTitle>Edit rollout metadata</SheetTitle>
          <SheetDescription>
            <AcronymText text="PMO fields only. MNO, project type, and SLA cannot be changed after creation." />
          </SheetDescription>
        </SheetHeader>

        <div className="space-y-4 px-4 py-2">
          <FormInput label="Search ring name" value={searchRingName} onChange={(e) => setSearchRingName(e.target.value)} />
          <RolloutGeographySelect
            kind="region"
            label="Region"
            value={region}
            onChange={(code) => {
              setRegion(code);
              const suggested = suggestedTerritoryForRegion(code);
              if (suggested && (!territory || territory === "NCR")) {
                setTerritory(suggested);
              }
            }}
            disabled={locked}
          />
          <FormInput label="Area" value={area} onChange={(e) => setArea(e.target.value)} />
          <RolloutGeographySelect
            kind="territory"
            label="Territory"
            value={territory}
            onChange={setTerritory}
            disabled={locked}
          />

          <div className="space-y-3">
            <div>
              <p className="text-sm font-medium text-foreground">Site profile</p>
              <p className="mt-0.5 text-xs text-muted-foreground">
                Canonical address and coordinates for this rollout site.
              </p>
            </div>
            <SiteProfileLocationFields
              fullAddress={fullAddress}
              latitude={latitude}
              longitude={longitude}
              onFullAddressChange={setFullAddress}
              onLatitudeChange={setLatitude}
              onLongitudeChange={setLongitude}
              coordinateError={siteCoordinateError}
              disabled={locked}
            />
          </div>

          <FormInput label="Alliance tag" value={allianceTag} onChange={(e) => setAllianceTag(e.target.value)} />
          <FormInput
            label="MNO anchor site ID"
            value={mnoAnchorSiteId}
            onChange={(e) => setMnoAnchorSiteId(e.target.value)}
          />
          <FormInput label="Endorsement ref" value={endorsementRef} onChange={(e) => setEndorsementRef(e.target.value)} />
          <FormInput
            label="Endorsement date"
            date
            value={endorsementDate}
            onChange={(e) => setEndorsementDate(e.target.value)}
          />
          <FormInput
            label="Energization tempo date"
            date
            value={energizationTempoDate}
            onChange={(e) => setEnergizationTempoDate(e.target.value)}
          />
          <FormInput
            label="RFTI signed (tempo)"
            date
            value={rftiSignedTempoDate}
            onChange={(e) => setRftiSignedTempoDate(e.target.value)}
          />
          <FormInput
            label="Site license remarks"
            value={siteLicenseRemarks}
            onChange={(e) => setSiteLicenseRemarks(e.target.value)}
          />
          <OwnerSelectField
            label={<AcronymLabel term="SAQ">SAQ owner</AcronymLabel>}
            hint="Assign saq_approver or manager; required for SAQ gate step 1."
            value={saqOwnerId}
            onChange={setSaqOwnerId}
            users={users}
            isLoading={usersQuery.isLoading}
            isError={usersQuery.isError}
          />
          <OwnerSelectField
            label="CME PM"
            hint="Assign cme_approver or manager for construction gates."
            value={cmePmId}
            onChange={setCmePmId}
            users={users}
            isLoading={usersQuery.isLoading}
            isError={usersQuery.isError}
          />
          <OwnerSelectField
            label={<AcronymLabel term="PMO">PMO owner</AcronymLabel>}
            hint="Assign pmo_approver or manager; required for PMO gate step 2."
            value={pmoOwnerId}
            onChange={setPmoOwnerId}
            users={users}
            isLoading={usersQuery.isLoading}
            isError={usersQuery.isError}
          />
          <label className="space-y-1.5 text-sm">
            <span className="font-medium text-foreground">Linked project</span>
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

        <SheetFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Close
          </Button>
          <Button
            type="button"
            disabled={locked || mutation.isPending || Boolean(siteCoordinateError)}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending ? "Saving…" : "Save changes"}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

function OwnerSelectField({
  label,
  hint,
  value,
  onChange,
  users,
  isLoading,
  isError,
}: {
  label: ReactNode;
  hint?: string;
  value: string;
  onChange: (value: string) => void;
  users: RolloutAssignableUser[];
  isLoading: boolean;
  isError: boolean;
}) {
  return (
    <label className="space-y-1.5 text-sm">
      <span className="font-medium text-foreground">{label}</span>
      {hint ? <span className="block text-xs font-normal text-muted-foreground">{hint}</span> : null}
      <select
        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
        value={value}
        disabled={isLoading || isError}
        onChange={(e) => onChange(e.target.value)}
      >
        <option value="">{isLoading ? "Loading users…" : "Unassigned"}</option>
        {users.map((user) => (
          <option key={user.id} value={user.id}>
            {user.name} · {user.email}
          </option>
        ))}
      </select>
      {isError ? (
        <span className="text-xs text-destructive">Could not load users. Refresh and try again.</span>
      ) : null}
      {!isLoading && !isError && users.length === 0 ? (
        <span className="text-xs text-muted-foreground">No active users in this tenant. Add users under Team & Access.</span>
      ) : null}
    </label>
  );
}
