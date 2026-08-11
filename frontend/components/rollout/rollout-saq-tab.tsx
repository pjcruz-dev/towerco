"use client";

import { useMutation, useQueryClient } from "@tanstack/react-query";
import { Fragment, useEffect, useState } from "react";

import { FormInput } from "@/components/forms/form-input";
import { AcronymText } from "@/components/help/acronym-text";
import { FileUploadField } from "@/components/forms/file-upload-field";
import { emptyLeasePackage, LeasePackageFields } from "@/components/rollout/lease-package-fields";
import { RolloutFieldFormFooter } from "@/components/rollout/rollout-field-form-footer";
import { RolloutMediaPreview } from "@/components/rollout/rollout-media-preview";
import { RolloutSaqMapPanel } from "@/components/rollout/rollout-saq-map-panel";
import {
  PhaseWorkFormFieldSpan,
  PhaseWorkFormSection,
  phaseWorkSheetBodyClass,
  phaseWorkSheetContentClass,
  phaseWorkSheetFooterClass,
} from "@/components/rollout/phase-work-form-section";
import { Button } from "@/components/ui/button";
import { useGeolocation } from "@/hooks/use-geolocation";
import { createClientDraftId, useRolloutDrafts } from "@/hooks/use-rollout-drafts";
import {
  formatCoordinate,
  hasCoordinatePair,
  toCoordinatePair,
  validateCoordinatePair,
  type CoordinateCaptureMethod,
} from "@/lib/rollout/coordinates";
import { parseCandidatesIdentifiedCount } from "@/lib/rollout/hunting-log";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { getErrorMessage } from "@/lib/api/error";
import {
  createRolloutCandidate,
  createRolloutHuntingLog,
  rejectRolloutCandidate,
  selectRolloutCandidate,
  updateRolloutCandidate,
} from "@/lib/api/modules/rollout-api";
import { phaseWorkSaqGridClass } from "@/lib/rollout/phase-work-layout";
import {
  activeSaqCandidateCount,
  hasSelectedSaqCandidate,
  isEndorsementEstablished,
  isSaqReadyToSelect,
  isSiteHuntingGateReady,
} from "@/lib/rollout/phase-gate-readiness";
import { cn } from "@/lib/utils";
import type { RolloutCandidate, RolloutDetail, RolloutLeasePackage, RolloutMediaLink } from "@/modules/rollout/types";
import { useNotificationStore } from "@/stores/notification-store";

const rejectionReasons = [
  { value: "power_unavailable", label: "Power unavailable" },
  { value: "row_blocked", label: "ROW blocked" },
  { value: "lessor_declined", label: "Lessor declined" },
  { value: "hazard", label: "Safety / hazard" },
  { value: "lease_rate_high", label: "Lease rate too high" },
  { value: "manual_review", label: "Manual review" },
] as const;

type Props = {
  rolloutId: string;
  detail: RolloutDetail | undefined;
  canManage: boolean;
  /** Timeline embed: side sheets for create forms + 2-column list/map layout. */
  embedded?: boolean;
};

type SaqDockTab = "candidates" | "logs" | "map";

/** SAQ site-hunting work (candidates, logs, map) — embeddable under timeline or standalone tab. */
export function RolloutSaqWorkPanel({
  rolloutId,
  detail,
  canManage,
  embedded = false,
}: Props) {
  const queryClient = useQueryClient();
  const push = useNotificationStore((state) => state.push);

  const [showCandidateForm, setShowCandidateForm] = useState(false);
  const [showLogForm, setShowLogForm] = useState(false);
  const [rejectTarget, setRejectTarget] = useState<{ id: string; label: string } | null>(null);
  const [editTarget, setEditTarget] = useState<RolloutCandidate | null>(null);
  const [rejectCode, setRejectCode] = useState<(typeof rejectionReasons)[number]["value"]>("manual_review");
  const [rejectNotes, setRejectNotes] = useState("");

  const [label, setLabel] = useState("");
  const [lessorName, setLessorName] = useState("");
  const [lessorContact, setLessorContact] = useState("");
  const [leaseRate, setLeaseRate] = useState("");
  const [latitude, setLatitude] = useState("");
  const [longitude, setLongitude] = useState("");
  const [rowNotes, setRowNotes] = useState("");
  const [powerNotes, setPowerNotes] = useState("");
  const [hazardNotes, setHazardNotes] = useState("");
  const [photoLinks, setPhotoLinks] = useState<RolloutMediaLink[]>([]);
  const [leasePackage, setLeasePackage] = useState<RolloutLeasePackage>(emptyLeasePackage());

  const [logDate, setLogDate] = useState(new Date().toISOString().slice(0, 10));
  const [logSummary, setLogSummary] = useState("");
  const [logCount, setLogCount] = useState("");
  const [logPhotoLinks, setLogPhotoLinks] = useState<RolloutMediaLink[]>([]);
  const [dockTab, setDockTab] = useState<SaqDockTab>("candidates");

  const defaultMapCoords = (): { lat: number; lng: number } => {
    const firstCandidate = detail?.candidates?.find((c) => c.latitude != null && c.longitude != null);
    const fromCandidate = toCoordinatePair(firstCandidate?.latitude, firstCandidate?.longitude);
    if (fromCandidate) {
      return fromCandidate;
    }

    const fromSite = toCoordinatePair(detail?.site?.latitude, detail?.site?.longitude);
    if (fromSite) {
      return fromSite;
    }

    return { lat: 14.676, lng: 121.0437 };
  };

  const [draggableCoords, setDraggableCoords] = useState<{ lat: number; lng: number } | null>(null);
  const [coordinateCaptureMethod, setCoordinateCaptureMethod] = useState<CoordinateCaptureMethod | null>(null);
  const [coordinateAccuracyM, setCoordinateAccuracyM] = useState<number | null>(null);
  const [coordinateHint, setCoordinateHint] = useState<string | null>(null);

  const syncCoordsToForm = (coords: { lat: string | number; lng: string | number }) => {
    setLatitude(formatCoordinate(coords.lat));
    setLongitude(formatCoordinate(coords.lng));
  };

  const geolocation = useGeolocation();
  const { queueDraft, isNetworkError } = useRolloutDrafts(rolloutId);
  const [candidateDraftId, setCandidateDraftId] = useState(() => createClientDraftId());
  const [logDraftId, setLogDraftId] = useState(() => createClientDraftId());

  useEffect(() => {
    if (geolocation.lat == null || geolocation.lng == null) {
      return;
    }

    const coords = { lat: geolocation.lat, lng: geolocation.lng };
    setDraggableCoords(coords);
    syncCoordsToForm(coords);
    setCoordinateCaptureMethod("gps");
    setCoordinateAccuracyM(geolocation.accuracyM);
    setCoordinateHint(null);
  }, [geolocation.accuracyM, geolocation.lat, geolocation.lng]);

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts", "detail", rolloutId] });
    queryClient.invalidateQueries({ queryKey: ["project-one", "rollouts"] });
  };

  const selectMutation = useMutation({
    mutationFn: (candidateId: string) => selectRolloutCandidate(candidateId),
    onSuccess: () => {
      invalidate();
      push({
        level: "success",
        title: "Candidate selected",
        message: "TCO Site ID issued. Request Site Hunting gate approval when ready.",
      });
    },
    onError: (error) =>
      push({ level: "error", title: "Selection failed", message: getErrorMessage(error) }),
  });

  const rejectMutation = useMutation({
    mutationFn: ({ id, code, notes }: { id: string; code: string; notes: string }) =>
      rejectRolloutCandidate(id, { rejection_reason_code: code, rejection_notes: notes || undefined }),
    onSuccess: () => {
      invalidate();
      setRejectTarget(null);
      setRejectNotes("");
      push({ level: "success", title: "Candidate rejected" });
    },
    onError: (error) =>
      push({ level: "error", title: "Reject failed", message: getErrorMessage(error) }),
  });

  const resolveCoordinateFields = () => {
    if (!hasCoordinatePair(latitude, longitude)) {
      return {};
    }

    const validated = validateCoordinatePair(latitude, longitude);
    if (!validated.ok) {
      throw new Error(validated.message);
    }

    if (validated.swapped) {
      syncCoordsToForm({ lat: validated.lat, lng: validated.lng });
      setCoordinateHint("Latitude and longitude were swapped — corrected automatically.");
    } else {
      setCoordinateHint(null);
    }

    return {
      latitude: validated.lat,
      longitude: validated.lng,
      coordinate_capture_method: coordinateCaptureMethod ?? "manual",
      coordinate_accuracy_m: coordinateAccuracyM ?? undefined,
      coordinates_captured_at: new Date().toISOString(),
    };
  };

  const candidatePayload = (clientDraftId?: string) => ({
    ...(clientDraftId ? { client_draft_id: clientDraftId } : {}),
    label: label.trim() || undefined,
    lessor_name: lessorName.trim() || undefined,
    lessor_contact: lessorContact.trim() || undefined,
    proposed_lease_rate_php: leaseRate ? Number(leaseRate) : undefined,
    row_notes: rowNotes.trim() || undefined,
    power_notes: powerNotes.trim() || undefined,
    hazard_notes: hazardNotes.trim() || undefined,
    photo_links: photoLinks.map(({ file_id, label: photoLabel }) => ({
      file_id,
      label: photoLabel ?? undefined,
    })),
    lease_package: {
      lessor_id_type: leasePackage.lessor_id_type ?? undefined,
      lease_term_months: leasePackage.lease_term_months ?? undefined,
      notes: leasePackage.notes ?? undefined,
      documents: (leasePackage.documents ?? []).map(({ file_id, label: docLabel }) => ({
        file_id,
        label: docLabel ?? undefined,
      })),
    },
  });

  const buildCandidatePayload = (clientDraftId?: string) => ({
    ...candidatePayload(clientDraftId),
    ...resolveCoordinateFields(),
  });

  const resetCandidateForm = () => {
    setLabel("");
    setLessorName("");
    setLessorContact("");
    setLeaseRate("");
    setLatitude("");
    setLongitude("");
    setDraggableCoords(null);
    setCoordinateCaptureMethod(null);
    setCoordinateAccuracyM(null);
    setCoordinateHint(null);
    setRowNotes("");
    setPowerNotes("");
    setHazardNotes("");
    setPhotoLinks([]);
    setLeasePackage(emptyLeasePackage());
  };

  const loadCandidateIntoForm = (candidate: RolloutCandidate) => {
    setLabel(candidate.label ?? "");
    setLessorName(candidate.lessor_name ?? "");
    setLessorContact(candidate.lessor_contact ?? "");
    setLeaseRate(candidate.proposed_lease_rate_php != null ? String(candidate.proposed_lease_rate_php) : "");
    setLatitude(candidate.latitude != null ? String(candidate.latitude) : "");
    setLongitude(candidate.longitude != null ? String(candidate.longitude) : "");
    setRowNotes(candidate.row_notes ?? "");
    setPowerNotes(candidate.power_notes ?? "");
    setHazardNotes(candidate.hazard_notes ?? "");
    setPhotoLinks(candidate.photo_links ?? []);
    setLeasePackage(candidate.lease_package ?? emptyLeasePackage());
  };

  const buildHuntingLogPayload = (clientDraftId?: string) => {
    const candidateCount = detail?.candidates?.length ?? 0;
    const parsed = parseCandidatesIdentifiedCount(logCount, candidateCount);
    if (parsed.error) {
      throw new Error(parsed.error);
    }

    return {
      ...(clientDraftId ? { client_draft_id: clientDraftId } : {}),
      log_date: logDate,
      summary: logSummary.trim() || undefined,
      candidates_identified_count: parsed.value,
      photo_links: logPhotoLinks.map(({ file_id, label: photoLabel }) => ({
        file_id,
        label: photoLabel ?? undefined,
      })),
    };
  };

  const queueCandidateDraft = () => {
    queueDraft({
      client_draft_id: candidateDraftId,
      kind: "candidate_create",
      rolloutId,
      payload: buildCandidatePayload(candidateDraftId),
      createdAt: new Date().toISOString(),
    });
    setShowCandidateForm(false);
    setDraggableCoords(null);
    resetCandidateForm();
    setCandidateDraftId(createClientDraftId());
  };

  const queueHuntingLogDraft = () => {
    try {
      queueDraft({
        client_draft_id: logDraftId,
        kind: "hunting_log",
        rolloutId,
        payload: buildHuntingLogPayload(logDraftId),
        createdAt: new Date().toISOString(),
      });
    } catch (error) {
      push({
        level: "error",
        title: "Invalid hunting log",
        message: error instanceof Error ? error.message : "Check candidates identified count.",
      });
      return;
    }
    setShowLogForm(false);
    setLogSummary("");
    setLogCount("");
    setLogPhotoLinks([]);
    setLogDraftId(createClientDraftId());
  };

  const createCandidateMutation = useMutation({
    mutationFn: () => createRolloutCandidate(rolloutId, buildCandidatePayload(candidateDraftId)),
    onSuccess: () => {
      invalidate();
      closeCandidateForm();
      setCandidateDraftId(createClientDraftId());
      push({ level: "success", title: "Candidate added" });
    },
    onError: (error) => {
      if (isNetworkError(error) || !navigator.onLine) {
        queueCandidateDraft();
        return;
      }
      push({ level: "error", title: "Could not add candidate", message: getErrorMessage(error) });
    },
  });

  const updateCandidateMutation = useMutation({
    mutationFn: () => {
      if (!editTarget) throw new Error("No candidate selected");
      return updateRolloutCandidate(editTarget.id, buildCandidatePayload());
    },
    onSuccess: () => {
      invalidate();
      setEditTarget(null);
      resetCandidateForm();
      push({ level: "success", title: "Candidate updated" });
    },
    onError: (error) =>
      push({ level: "error", title: "Could not update candidate", message: getErrorMessage(error) }),
  });

  const huntingLogMutation = useMutation({
    mutationFn: () => createRolloutHuntingLog(rolloutId, buildHuntingLogPayload(logDraftId)),
    onSuccess: () => {
      invalidate();
      setShowLogForm(false);
      setLogSummary("");
      setLogCount("");
      setLogPhotoLinks([]);
      setLogDraftId(createClientDraftId());
      push({ level: "success", title: "Hunting log saved" });
    },
    onError: (error) => {
      if (isNetworkError(error) || !navigator.onLine) {
        queueHuntingLogDraft();
        return;
      }
      push({ level: "error", title: "Could not save hunting log", message: getErrorMessage(error) });
    },
  });

  const openCandidateForm = () => {
    const coords = defaultMapCoords();
    setDraggableCoords(coords);
    syncCoordsToForm(coords);
    setCandidateDraftId(createClientDraftId());
    setShowCandidateForm(true);
  };

  const closeCandidateForm = () => {
    setShowCandidateForm(false);
    setDraggableCoords(null);
    resetCandidateForm();
  };

  const openLogForm = () => {
    setLogDraftId(createClientDraftId());
    setShowLogForm(true);
  };

  const submitCandidate = (e: React.FormEvent) => {
    e.preventDefault();
    if (!navigator.onLine) {
      queueCandidateDraft();
      return;
    }
    try {
      buildCandidatePayload(candidateDraftId);
    } catch (error) {
      push({
        level: "error",
        title: "Invalid coordinates",
        message: error instanceof Error ? error.message : "Check latitude and longitude.",
      });
      return;
    }
    createCandidateMutation.mutate();
  };

  const candidateForm = (
    <form id="saq-candidate-form" className="space-y-1" onSubmit={submitCandidate}>
      <PhaseWorkFormSection title="Candidate details" description="Label, lessor, and proposed lease.">
        <PhaseWorkFormFieldSpan>
          <FormInput
            touchFriendly
            label="Label"
            value={label}
            onChange={(e) => setLabel(e.target.value)}
            placeholder="Candidate label"
          />
        </PhaseWorkFormFieldSpan>
        <FormInput touchFriendly label="Lessor" value={lessorName} onChange={(e) => setLessorName(e.target.value)} />
        <FormInput touchFriendly label="Lessor contact" value={lessorContact} onChange={(e) => setLessorContact(e.target.value)} />
        <FormInput
          touchFriendly
          label="Proposed lease (PHP/mo)"
          value={leaseRate}
          onChange={(e) => setLeaseRate(e.target.value)}
          inputMode="decimal"
        />
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Location" description="GPS, coordinates, and map pin.">
        <PhaseWorkFormFieldSpan>
          <div className="flex flex-col gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="w-full sm:w-auto"
              disabled={geolocation.isLoading}
              onClick={() => geolocation.request()}
            >
              {geolocation.isLoading ? "Reading GPS…" : "Use my location"}
            </Button>
            {geolocation.error ? <p className="text-xs text-destructive">{geolocation.error}</p> : null}
            {geolocation.accuracyM != null ? (
              <p className="text-xs text-muted-foreground">GPS accuracy ±{Math.round(geolocation.accuracyM)} m</p>
            ) : null}
            {coordinateHint ? <p className="text-xs text-amber-700 dark:text-amber-300">{coordinateHint}</p> : null}
          </div>
        </PhaseWorkFormFieldSpan>
        <FormInput
          touchFriendly
          label="Latitude"
          value={latitude}
          onChange={(e) => {
            setLatitude(e.target.value);
            setCoordinateCaptureMethod("manual");
            setCoordinateAccuracyM(null);
          }}
          inputMode="decimal"
        />
        <FormInput
          touchFriendly
          label="Longitude"
          value={longitude}
          onChange={(e) => {
            setLongitude(e.target.value);
            setCoordinateCaptureMethod("manual");
            setCoordinateAccuracyM(null);
          }}
          inputMode="decimal"
        />
        <PhaseWorkFormFieldSpan>
          <p className="mb-2 text-xs text-muted-foreground">
            Confirm the pin on the map before saving — drag to fine-tune after GPS.
          </p>
          <RolloutSaqMapPanel
            detail={detail}
            draggableCoords={draggableCoords}
            onDraggableCoordsChange={(coords) => {
              setDraggableCoords(coords);
              syncCoordsToForm(coords);
              setCoordinateCaptureMethod("map_drag");
              setCoordinateAccuracyM(null);
              setCoordinateHint(null);
            }}
          />
        </PhaseWorkFormFieldSpan>
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Site notes" columns="1">
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">ROW notes</span>
          <textarea
            className="min-h-[72px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={rowNotes}
            onChange={(e) => setRowNotes(e.target.value)}
          />
        </label>
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">Power notes</span>
          <textarea
            className="min-h-[72px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={powerNotes}
            onChange={(e) => setPowerNotes(e.target.value)}
          />
        </label>
        <label className="space-y-1.5">
          <span className="text-sm font-medium text-foreground">Hazard notes</span>
          <textarea
            className="min-h-[72px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={hazardNotes}
            onChange={(e) => setHazardNotes(e.target.value)}
          />
        </label>
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Photos & lease" columns="1">
        <FileUploadField
          rolloutId={rolloutId}
          context="candidate_photo"
          label="Site photos"
          capture="environment"
          value={photoLinks}
          onChange={setPhotoLinks}
        />
        <LeasePackageFields rolloutId={rolloutId} value={leasePackage} onChange={setLeasePackage} />
      </PhaseWorkFormSection>

      {!embedded ? (
        <div className="pt-2">
          <RolloutFieldFormFooter
            submitLabel="Save candidate"
            isSubmitting={createCandidateMutation.isPending}
            showSaveDraft
            onSaveDraft={queueCandidateDraft}
          />
        </div>
      ) : null}
    </form>
  );

  const huntingLogForm = (
    <form
      id="saq-hunting-log-form"
      className="space-y-1"
      onSubmit={(e) => {
        e.preventDefault();
        try {
          buildHuntingLogPayload(logDraftId);
        } catch (error) {
          push({
            level: "error",
            title: "Could not save hunting log",
            message: error instanceof Error ? error.message : "Check candidates identified count.",
          });
          return;
        }
        if (!navigator.onLine) {
          queueHuntingLogDraft();
          return;
        }
        huntingLogMutation.mutate();
      }}
    >
      <PhaseWorkFormSection title="Daily log">
        <FormInput touchFriendly label="Log date" date value={logDate} onChange={(e) => setLogDate(e.target.value)} />
        <FormInput
          touchFriendly
          label="Candidates identified"
          type="number"
          min={0}
          value={logCount}
          onChange={(e) => setLogCount(e.target.value)}
          inputMode="numeric"
          placeholder={detail?.candidates?.length ? String(detail.candidates.length) : "e.g. 3"}
        />
        <PhaseWorkFormFieldSpan>
          <label className="space-y-1.5">
            <span className="text-sm font-medium text-foreground">Summary</span>
            <textarea
              className="min-h-[96px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
              value={logSummary}
              onChange={(e) => setLogSummary(e.target.value)}
              placeholder="Field activity summary for the day"
            />
          </label>
        </PhaseWorkFormFieldSpan>
      </PhaseWorkFormSection>

      <PhaseWorkFormSection title="Field photos" columns="1">
        <FileUploadField
          rolloutId={rolloutId}
          context="hunting_log"
          label="Photos"
          capture="environment"
          value={logPhotoLinks}
          onChange={setLogPhotoLinks}
        />
      </PhaseWorkFormSection>

      {!embedded ? (
        <div className="pt-2">
          <RolloutFieldFormFooter
            submitLabel="Save hunting log"
            isSubmitting={huntingLogMutation.isPending}
            showSaveDraft
            onSaveDraft={queueHuntingLogDraft}
          />
        </div>
      ) : null}
    </form>
  );

  return (
    <div className="space-y-4">
      {detail && !isEndorsementEstablished(detail) ? (
        <div className="rounded-lg border border-amber-200 bg-amber-50/80 px-3 py-2 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
          Complete <span className="font-medium">Endorsement</span> (Site Tracker enrolment) before adding
          candidates or hunting logs.
        </div>
      ) : null}
      {detail && isEndorsementEstablished(detail) && !isSiteHuntingGateReady(detail) ? (
        <div className="rounded-lg border border-border bg-card px-3 py-2 text-sm shadow-sm">
          <p className="font-medium text-foreground">SAQ Site Hunting</p>
          <ul className="mt-1 list-inside list-disc text-xs text-muted-foreground">
            <li>
              Active candidates: {activeSaqCandidateCount(detail)}/3
              {activeSaqCandidateCount(detail) < 3
                ? ` — add ${3 - activeSaqCandidateCount(detail)} more`
                : " ✓"}
            </li>
            <li>
              Select one candidate
              {hasSelectedSaqCandidate(detail) ? " ✓" : isSaqReadyToSelect(detail) ? " — ready to select" : " — after 3 candidates"}
            </li>
            <li>Then request Site Hunting gate approval (SAQ → PMO)</li>
          </ul>
        </div>
      ) : null}
      {detail && isSiteHuntingGateReady(detail) ? (
        <div className="rounded-lg border border-green-200 bg-green-50/80 px-3 py-2 text-sm text-green-950 dark:border-green-900 dark:bg-green-950/30 dark:text-green-100">
          Ready — request or pass the <span className="font-medium">Site Hunting</span> gate on the timeline.
        </div>
      ) : null}
      {canManage ? (
        <div className="flex flex-wrap gap-2">
          <Button
            size={embedded ? "sm" : "lg"}
            className={embedded ? "" : "min-h-11"}
            variant="outline"
            disabled={!detail || !isEndorsementEstablished(detail)}
            onClick={() => (showCandidateForm && !embedded ? closeCandidateForm() : openCandidateForm())}
          >
            Add candidate
          </Button>
          <Button
            size={embedded ? "sm" : "lg"}
            className={embedded ? "" : "min-h-11"}
            variant="outline"
            disabled={!detail || !isEndorsementEstablished(detail)}
            onClick={() => (showLogForm && !embedded ? setShowLogForm(false) : openLogForm())}
          >
            Log hunting day
          </Button>
        </div>
      ) : null}

      {!embedded && showCandidateForm && canManage ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5">{candidateForm}</div>
      ) : null}

      {!embedded && showLogForm && canManage ? (
        <div className="rounded-xl border border-border bg-card p-4 shadow-sm">{huntingLogForm}</div>
      ) : null}

      {embedded ? (
        <div className="flex gap-1 border-b border-border">
          {(
            [
              { key: "candidates" as const, label: "Candidates" },
              { key: "logs" as const, label: "Hunting logs" },
              { key: "map" as const, label: "Map" },
            ] as const
          ).map((tab) => (
            <button
              key={tab.key}
              type="button"
              className={cn(
                "border-b-2 px-3 py-2 text-sm font-medium transition-colors",
                dockTab === tab.key
                  ? "border-primary text-foreground"
                  : "border-transparent text-muted-foreground hover:text-foreground",
              )}
              onClick={() => setDockTab(tab.key)}
            >
              {tab.label}
            </button>
          ))}
        </div>
      ) : null}

      <div className={embedded ? "space-y-4" : "grid gap-4 xl:grid-cols-[1.2fr_1fr]"}>
        {(!embedded || dockTab === "candidates") ? (
        <Fragment>
        <div className="space-y-3 md:hidden">
          {(detail?.candidates ?? []).length === 0 ? (
            <p className="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">
              <AcronymText text="No candidates yet. SAQ requires at least 3 scouted candidates." />
            </p>
          ) : null}
          {(detail?.candidates ?? []).map((candidate) => (
            <article key={candidate.id} className="rounded-xl border border-border bg-card p-4 shadow-sm">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <p className="font-medium text-foreground">{candidate.label ?? `Candidate ${candidate.candidate_number}`}</p>
                  <p className="text-sm capitalize text-muted-foreground">{candidate.status}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{candidate.lessor_name ?? "No lessor"}</p>
                </div>
                <span className="text-xs text-muted-foreground">#{candidate.candidate_number}</span>
              </div>
              <div className="mt-3">
                <RolloutMediaPreview items={candidate.photo_links} />
              </div>
              {canManage && candidate.status !== "selected" && candidate.status !== "rejected" ? (
                <div className="mt-3 flex flex-wrap gap-2">
                  <Button
                    size="lg"
                    className="min-h-11 flex-1"
                    variant="outline"
                    onClick={() => {
                      loadCandidateIntoForm(candidate);
                      setEditTarget(candidate);
                      if (candidate.latitude != null && candidate.longitude != null) {
                        const coords = toCoordinatePair(candidate.latitude, candidate.longitude);
                        if (coords) {
                          setDraggableCoords(coords);
                        }
                      }
                    }}
                  >
                    Edit
                  </Button>
                  <Button
                    size="lg"
                    className="min-h-11 flex-1"
                    variant="outline"
                    disabled={!detail || !isSaqReadyToSelect(detail) || selectMutation.isPending}
                    title={
                      detail && !isSaqReadyToSelect(detail)
                        ? "Need 3 active candidates before selecting"
                        : undefined
                    }
                    onClick={() => selectMutation.mutate(candidate.id)}
                  >
                    Select
                  </Button>
                </div>
              ) : null}
            </article>
          ))}
        </div>

        <div className={`overflow-hidden rounded-lg border border-border bg-muted/20 ${embedded ? "" : "hidden md:block"}`}>
          <Table>
          <TableHeader>
            <TableRow>
              <TableHead>#</TableHead>
              <TableHead>Label</TableHead>
              <TableHead>Lessor</TableHead>
              <TableHead>Status</TableHead>
              {canManage ? <TableHead className="text-right">Actions</TableHead> : null}
            </TableRow>
          </TableHeader>
          <TableBody>
            {(detail?.candidates ?? []).length === 0 ? (
              <TableRow>
                <TableCell colSpan={canManage ? 5 : 4} className="py-10 text-center">
                  <div className="space-y-3">
                    <p className="text-sm text-muted-foreground">
                      <AcronymText text="No candidates yet. SAQ requires at least 3 scouted candidates." />
                    </p>
                    {canManage ? (
                      <Button size="sm" onClick={openCandidateForm}>
                        Add first candidate
                      </Button>
                    ) : null}
                  </div>
                </TableCell>
              </TableRow>
            ) : null}
            {(detail?.candidates ?? []).map((candidate) => (
              <TableRow key={candidate.id}>
                <TableCell>{candidate.candidate_number}</TableCell>
                <TableCell>{candidate.label ?? "—"}</TableCell>
                <TableCell>
                  <div className="space-y-2">
                    <span>{candidate.lessor_name ?? "—"}</span>
                    <RolloutMediaPreview items={candidate.photo_links} />
                  </div>
                </TableCell>
                <TableCell className="capitalize">{candidate.status}</TableCell>
                {canManage ? (
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-1">
                      {candidate.status !== "selected" && candidate.status !== "rejected" ? (
                        <>
                          <Button size="sm" variant="ghost" onClick={() => {
                            loadCandidateIntoForm(candidate);
                            setEditTarget(candidate);
                            if (candidate.latitude != null && candidate.longitude != null) {
                              const coords = toCoordinatePair(candidate.latitude, candidate.longitude);
                              if (coords) {
                                setDraggableCoords(coords);
                              }
                            }
                          }}>
                            Edit
                          </Button>
                          <Button
                            size="sm"
                            variant="outline"
                            disabled={!detail || !isSaqReadyToSelect(detail) || selectMutation.isPending}
                            title={
                              detail && !isSaqReadyToSelect(detail)
                                ? "Need 3 active candidates before selecting"
                                : undefined
                            }
                            onClick={() => selectMutation.mutate(candidate.id)}
                          >
                            Select
                          </Button>
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() =>
                              setRejectTarget({
                                id: candidate.id,
                                label: candidate.label ?? `Candidate ${candidate.candidate_number}`,
                              })
                            }
                          >
                            Reject
                          </Button>
                        </>
                      ) : null}
                    </div>
                  </TableCell>
                ) : null}
              </TableRow>
            ))}
          </TableBody>
        </Table>
        </div>
        </Fragment>
        ) : null}

        {(!embedded || dockTab === "map") ? (
        <div className={`rounded-lg border border-border bg-muted/20 p-3 ${embedded ? "min-h-[320px]" : "rounded-xl bg-card p-4 shadow-sm"}`}>
          <h2 className="mb-2 text-sm font-medium text-foreground">Candidate map</h2>
          <p className="mb-3 text-xs text-muted-foreground">
            Selected site and scouted candidates for this rollout program.
          </p>
          <RolloutSaqMapPanel detail={detail} />
        </div>
        ) : null}
      </div>

      {(!embedded || dockTab === "logs") ? (
      <div className="rounded-lg border border-border bg-muted/20 p-4">
        <h2 className="text-sm font-medium text-foreground">Hunting logs</h2>
        {(detail?.hunting_logs ?? []).length === 0 ? (
          <div className="mt-3 space-y-2">
            <p className="text-sm text-muted-foreground">No daily hunting logs recorded yet.</p>
            {canManage ? (
              <Button size="sm" variant="outline" onClick={openLogForm}>
                Log hunting day
              </Button>
            ) : null}
          </div>
        ) : (
          <ul className="mt-3 space-y-2 text-sm text-muted-foreground">
            {detail?.hunting_logs.map((log) => (
              <li key={log.id} className="border-b border-border pb-2 last:border-0">
                <span className="font-medium text-foreground">{log.log_date}</span>
                {log.candidates_identified_count != null ? ` · ${log.candidates_identified_count} identified` : ""} —{" "}
                {log.summary ?? "—"}
                <div className="mt-2">
                  <RolloutMediaPreview items={log.photo_links} />
                </div>
              </li>
            ))}
          </ul>
        )}
      </div>
      ) : null}

      {embedded && canManage ? (
        <Sheet open={showCandidateForm} onOpenChange={(open) => (open ? openCandidateForm() : closeCandidateForm())}>
          <SheetContent side="right" className={phaseWorkSheetContentClass}>
            <SheetHeader className="shrink-0 border-b border-border">
              <SheetTitle>Add candidate</SheetTitle>
              <SheetDescription>Scout a site candidate without leaving the timeline.</SheetDescription>
            </SheetHeader>
            <div className={phaseWorkSheetBodyClass}>{candidateForm}</div>
            <div className={phaseWorkSheetFooterClass}>
              <RolloutFieldFormFooter
                submitLabel="Save candidate"
                isSubmitting={createCandidateMutation.isPending}
                showSaveDraft
                onSaveDraft={queueCandidateDraft}
                formId="saq-candidate-form"
              />
            </div>
          </SheetContent>
        </Sheet>
      ) : null}

      {embedded && canManage ? (
        <Sheet
          open={showLogForm}
          onOpenChange={(open) => {
            if (open) {
              openLogForm();
            } else {
              setShowLogForm(false);
              setLogSummary("");
              setLogCount("");
              setLogPhotoLinks([]);
            }
          }}
        >
          <SheetContent side="right" className={phaseWorkSheetContentClass}>
            <SheetHeader className="shrink-0 border-b border-border">
              <SheetTitle>Log hunting day</SheetTitle>
              <SheetDescription>Record daily field activity for site hunting.</SheetDescription>
            </SheetHeader>
            <div className={phaseWorkSheetBodyClass}>{huntingLogForm}</div>
            <div className={phaseWorkSheetFooterClass}>
              <RolloutFieldFormFooter
                submitLabel="Save hunting log"
                isSubmitting={huntingLogMutation.isPending}
                showSaveDraft
                onSaveDraft={queueHuntingLogDraft}
                formId="saq-hunting-log-form"
              />
            </div>
          </SheetContent>
        </Sheet>
      ) : null}

      <Sheet
        open={rejectTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setRejectTarget(null);
            setRejectNotes("");
          }
        }}
      >
        <SheetContent side="right" className="flex w-full flex-col sm:max-w-md">
          <SheetHeader>
            <SheetTitle>Reject candidate</SheetTitle>
            <SheetDescription>{rejectTarget?.label}</SheetDescription>
          </SheetHeader>
          <div className="flex-1 space-y-4 px-4">
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">Reason</span>
              <select
                className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={rejectCode}
                onChange={(e) => setRejectCode(e.target.value as typeof rejectCode)}
              >
                {rejectionReasons.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">Notes</span>
              <textarea
                className="min-h-[100px] w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus:border-ring"
                value={rejectNotes}
                onChange={(e) => setRejectNotes(e.target.value)}
                placeholder="Optional audit notes"
              />
            </label>
          </div>
          <SheetFooter className="border-t border-border pt-4">
            <Button
              variant="destructive"
              disabled={rejectMutation.isPending || !rejectTarget}
              onClick={() => {
                if (!rejectTarget) return;
                rejectMutation.mutate({ id: rejectTarget.id, code: rejectCode, notes: rejectNotes });
              }}
            >
              Confirm reject
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>

      <Sheet
        open={editTarget !== null}
        onOpenChange={(open) => {
          if (!open) {
            setEditTarget(null);
            resetCandidateForm();
          }
        }}
      >
        <SheetContent side="right" className="flex w-full flex-col sm:max-w-lg">
          <SheetHeader>
            <SheetTitle>Edit candidate</SheetTitle>
            <SheetDescription>{editTarget?.label ?? `Candidate ${editTarget?.candidate_number ?? ""}`}</SheetDescription>
          </SheetHeader>
          <div className="flex-1 space-y-3 overflow-y-auto px-4">
            <FormInput label="Label" value={label} onChange={(e) => setLabel(e.target.value)} />
            <FormInput label="Lessor" value={lessorName} onChange={(e) => setLessorName(e.target.value)} />
            <FormInput label="Lessor contact" value={lessorContact} onChange={(e) => setLessorContact(e.target.value)} />
            <FormInput label="Proposed lease (PHP/mo)" value={leaseRate} onChange={(e) => setLeaseRate(e.target.value)} />
            <div className="grid gap-3 sm:grid-cols-2">
              <FormInput label="Latitude" value={latitude} onChange={(e) => setLatitude(e.target.value)} />
              <FormInput label="Longitude" value={longitude} onChange={(e) => setLongitude(e.target.value)} />
            </div>
            <RolloutSaqMapPanel
              detail={detail}
              draggableCoords={draggableCoords}
              onDraggableCoordsChange={(coords) => {
                setDraggableCoords(coords);
                syncCoordsToForm(coords);
              }}
            />
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">ROW notes</span>
              <textarea className="min-h-[72px] w-full rounded-md border bg-background px-3 py-2 text-sm" value={rowNotes} onChange={(e) => setRowNotes(e.target.value)} />
            </label>
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">Power notes</span>
              <textarea className="min-h-[72px] w-full rounded-md border bg-background px-3 py-2 text-sm" value={powerNotes} onChange={(e) => setPowerNotes(e.target.value)} />
            </label>
            <label className="space-y-1.5 text-sm">
              <span className="font-medium">Hazard notes</span>
              <textarea className="min-h-[72px] w-full rounded-md border bg-background px-3 py-2 text-sm" value={hazardNotes} onChange={(e) => setHazardNotes(e.target.value)} />
            </label>
            <FileUploadField
              rolloutId={rolloutId}
              context="candidate_photo"
              label="Site photos"
              value={photoLinks}
              onChange={setPhotoLinks}
            />
            <LeasePackageFields rolloutId={rolloutId} value={leasePackage} onChange={setLeasePackage} />
          </div>
          <SheetFooter className="border-t border-border pt-4">
            <Button
              disabled={updateCandidateMutation.isPending}
              onClick={() => {
                try {
                  buildCandidatePayload();
                } catch (error) {
                  push({
                    level: "error",
                    title: "Invalid coordinates",
                    message: error instanceof Error ? error.message : "Check latitude and longitude.",
                  });
                  return;
                }
                updateCandidateMutation.mutate();
              }}
            >
              Save changes
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </div>
  );
}

/** @deprecated Use RolloutSaqWorkPanel — kept for backwards compatibility. */
export const RolloutSaqTab = RolloutSaqWorkPanel;
