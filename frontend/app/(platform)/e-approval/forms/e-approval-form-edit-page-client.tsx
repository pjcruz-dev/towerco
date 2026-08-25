"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useRouter, useSearchParams } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";

import {
  EApprovalFormImportExportPanel,
  readStoredImportDraft,
  type EApprovalFormImportPayload,
} from "@/components/e-approval/e-approval-form-import-export-panel";
import { EApprovalPrintLayoutEditor } from "@/components/e-approval/e-approval-print-layout-editor";
import { EApprovalSectionCard } from "@/components/e-approval/e-approval-section-card";
import { EApprovalFormDeleteDialog } from "@/components/e-approval/e-approval-form-delete-dialog";
import { EApprovalFormPublishChecklistDialog } from "@/components/e-approval/e-approval-form-publish-checklist-dialog";
import { EApprovalFormCreateWizard } from "@/components/e-approval/e-approval-form-create-wizard";
import { EApprovalFormComposeSettingsCard } from "@/components/e-approval/e-approval-form-compose-settings-card";
import { EApprovalFormRevisionSettingsCard } from "@/components/e-approval/e-approval-form-revision-settings-card";
import { EApprovalFormControlledDocumentSyncCard } from "@/components/e-approval/e-approval-form-controlled-document-sync-card";
import { EApprovalFormBrandLogoPreview } from "@/components/e-approval/e-approval-form-brand-logo-preview";
import { EApprovalFormWorkspaceSettingsCard } from "@/components/e-approval/e-approval-form-workspace-settings-card";
import { EApprovalFormWorkspaceDashboardCard } from "@/components/e-approval/e-approval-form-workspace-dashboard-card";
import { EApprovalFormDocumentNumberCard } from "@/components/e-approval/e-approval-form-document-number-card";
import { EApprovalFormTemplateGallery } from "@/components/e-approval/e-approval-form-template-gallery";
import { EApprovalFormVersionTimeline } from "@/components/e-approval/e-approval-form-version-timeline";
import { EApprovalVisualFormBuilder } from "@/components/e-approval/e-approval-visual-form-builder";
import { EApprovalWorkflowEditor } from "@/components/e-approval/e-approval-workflow-editor";
import { PermissionGate } from "@/components/layout/permission-gate";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { Eye, FileJson, GitBranch, LayoutDashboard, PenLine, Printer, Settings2, Trash2, Workflow } from "lucide-react";
import { EApprovalFormPreview } from "@/components/e-approval/e-approval-form-preview";
import { EApprovalPublicLinksPanel } from "@/components/e-approval/e-approval-public-links-panel";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import {
  mapEApprovalAssignableUsersToOptions,
  useEApprovalAssignableUsers,
} from "@/hooks/use-e-approval-assignable-users";
import {
  serializeEApprovalFormEditorSnapshot,
  useEApprovalFormEditorDirty,
} from "@/hooks/use-e-approval-form-editor-dirty";
import { useEApprovalPlanFeatures } from "@/hooks/use-e-approval-plan-features";
import {
  createEApprovalForm,
  deleteEApprovalForm,
  fetchEApprovalForm,
  fetchEApprovalFormRevisions,
  fetchEApprovalMetadata,
  publishEApprovalForm,
  updateEApprovalForm,
  updateEApprovalPdfLayout,
  uploadEApprovalFormLogo,
} from "@/lib/api/modules/e-approval-api";
import { getErrorMessage } from "@/lib/api/error";
import type {
  EApprovalFormDetail,
  EApprovalFormFieldInput,
  EApprovalWorkflowStepInput,
} from "@/modules/e-approval/types";
import { isFormApiKeysLocked } from "@/modules/e-approval/field-api-key";
import {
  parseBuilderLayoutRows,
  parseFormMetadataJson,
  patchBuilderLayoutRows,
  type EApprovalBuilderLayoutRow,
} from "@/modules/e-approval/builder-layout-rows";
import {
  buildFormPublishChecklist,
  type FormBuilderCheckItem,
} from "@/modules/e-approval/form-builder-checklist";
import {
  resolvePrintTemplateEntryForForm,
} from "@/modules/e-approval/print-template-registry";
import {
  getValidEApprovalWorkflowSteps,
  hasValidEApprovalWorkflowSteps,
  workflowStepStatusLabel,
} from "@/modules/e-approval/workflow-steps";
import { compactWorkflowStepOrdersPreservingTies } from "@/modules/e-approval/workflow-parallel-groups";
import {
  formComposeSettingsFromMetadata,
  mergeFormComposeIntoMetadata,
  type FormComposeEditorSettings,
} from "@/modules/e-approval/form-compose-config";
import {
  formRevisionSettingsFromMetadata,
  mergeFormRevisionIntoMetadata,
  type FormRevisionEditorSettings,
} from "@/modules/e-approval/form-revision-config";
import {
  mergeFormOutboundIntoMetadata,
  parseFormOutboundConfig,
  type FormOutboundEditorSettings,
} from "@/modules/e-approval/form-outbound-config";
import { EApprovalFormOutboundSettingsCard } from "@/components/e-approval/e-approval-form-outbound-settings-card";
import {
  controlledDocumentSyncSettingsFromMetadata,
  mergeControlledDocumentSyncIntoMetadata,
  type ControlledDocumentSyncEditorSettings,
} from "@/modules/e-approval/form-controlled-document-sync";
import {
  mergeWorkspaceIntoMetadata,
  workspaceEditorReadiness,
  workspaceSettingsFromMetadata,
  type FormWorkspaceEditorSettings,
} from "@/modules/e-approval/form-workspace-config";
import {
  dashboardSettingsFromMetadata,
  mergeDashboardIntoWorkspaceMetadata,
  type FormWorkspaceDashboardSettings,
} from "@/modules/e-approval/form-workspace-dashboard-config";
import {
  aclSettingsFromMetadata,
  mergeAclIntoWorkspaceMetadata,
  type FormWorkspaceAclSettings,
} from "@/modules/e-approval/form-workspace-acl-config";
import { EApprovalFormWorkspaceAclCard } from "@/components/e-approval/e-approval-form-workspace-acl-card";
import {
  DEFAULT_FORM_DOCUMENT_NUMBER,
  documentNumberSettingsFromFormDetail,
  documentNumberSettingsToApiPayload,
  type EApprovalFormDocumentNumberSettings,
} from "@/modules/e-approval/form-document-number";
import { migrateLegacyWorkflowOnLoad } from "@/modules/e-approval/workflow-conditions";
import { permissions } from "@/lib/rbac/permissions";
import { useNotificationStore } from "@/stores/notification-store";

type Props = { formId?: string };

export function EApprovalFormEditPageClient({ formId }: Props) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const queryClient = useQueryClient();
  const push = useNotificationStore((s) => s.push);
  const isNew = !formId;
  const [showCreateWizard, setShowCreateWizard] = useState(isNew);
  const initialTab = searchParams.get("tab");
  const defaultTab =
    initialTab === "design" ||
    initialTab === "workflow" ||
    initialTab === "preview" ||
    initialTab === "workspace"
      ? initialTab
      : "setup";

  const [name, setName] = useState("");
  const [description, setDescription] = useState("");
  const [status, setStatus] = useState<"draft" | "published">("draft");
  const [fields, setFields] = useState<EApprovalFormFieldInput[]>([
    { type: "text", name: "summary", label: "Summary" },
  ]);
  const [steps, setSteps] = useState<EApprovalWorkflowStepInput[]>([]);
  const [metadataJson, setMetadataJson] = useState("{}");
  const [brandLogoUrl, setBrandLogoUrl] = useState<string | null>(null);
  const [logoPreviewKey, setLogoPreviewKey] = useState(0);
  const [showImportPanel, setShowImportPanel] = useState(false);
  const [checklistOpen, setChecklistOpen] = useState(false);
  const [checklistItems, setChecklistItems] = useState<FormBuilderCheckItem[]>([]);
  const [pendingAction, setPendingAction] = useState<"save" | "publish" | null>(null);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [acceptsNewSubmissions, setAcceptsNewSubmissions] = useState(true);
  const [confirmFormUpgrade, setConfirmFormUpgrade] = useState(false);
  const [documentNumber, setDocumentNumber] = useState<EApprovalFormDocumentNumberSettings>(
    DEFAULT_FORM_DOCUMENT_NUMBER,
  );

  const applyImportPayload = (payload: EApprovalFormImportPayload) => {
    setName(payload.name);
    setDescription(payload.description);
    setStatus(payload.status);
    setFields(payload.fields);
    setSteps(payload.steps);
    setMetadataJson(payload.metadataJson);
    setBrandLogoUrl(payload.brandLogoUrl);
    setDocumentNumber(payload.documentNumber);
  };

  const formQuery = useQuery({
    queryKey: ["e-approval", "form", formId],
    queryFn: () => fetchEApprovalForm(formId!),
    enabled: !!formId,
    staleTime: 30_000,
  });

  const metadataQuery = useQuery({
    queryKey: ["e-approval", "metadata"],
    queryFn: fetchEApprovalMetadata,
    staleTime: 60_000,
  });
  const knownDepartments = metadataQuery.data?.departments ?? [];

  const revisionsQuery = useQuery({
    queryKey: ["e-approval", "form", formId, "revisions"],
    queryFn: () => fetchEApprovalFormRevisions(formId!),
    enabled: !!formId,
    staleTime: 30_000,
  });

  const lastSyncedFormAt = useRef(0);

  const usersQuery = useEApprovalAssignableUsers(true);
  const planFeatures = useEApprovalPlanFeatures();

  const approverOptions = useMemo(
    () => mapEApprovalAssignableUsersToOptions(usersQuery.data),
    [usersQuery.data],
  );

  const validStepCount = useMemo(() => getValidEApprovalWorkflowSteps(steps).length, [steps]);
  const workflowReady = validStepCount > 0;

  const submissionsCount = formQuery.data?.submissions_count ?? 0;
  const pendingSubmissionsCount = formQuery.data?.pending_submissions_count ?? 0;
  const canDeleteForm = submissionsCount === 0;
  const requiresUpgradeConfirm =
    pendingSubmissionsCount > 0 && (status === "published" || pendingAction === "publish");
  const apiKeysLocked = isFormApiKeysLocked(status, submissionsCount);

  const layoutRows = useMemo(
    () => parseBuilderLayoutRows(parseFormMetadataJson(metadataJson)),
    [metadataJson],
  );

  const controlledDocumentSync = useMemo(
    () => controlledDocumentSyncSettingsFromMetadata(parseFormMetadataJson(metadataJson)),
    [metadataJson],
  );

  const composeSettings = useMemo(
    () => formComposeSettingsFromMetadata(parseFormMetadataJson(metadataJson)),
    [metadataJson],
  );

  const revisionSettings = useMemo(
    () => formRevisionSettingsFromMetadata(parseFormMetadataJson(metadataJson)),
    [metadataJson],
  );

  const outboundSettings = useMemo(
    () => parseFormOutboundConfig(parseFormMetadataJson(metadataJson)),
    [metadataJson],
  );

  const parsedMetadata = useMemo(() => parseFormMetadataJson(metadataJson), [metadataJson]);

  const workspaceSettings = useMemo(
    () => workspaceSettingsFromMetadata(parsedMetadata, name),
    [parsedMetadata, name],
  );

  const workspaceDashboardSettings = useMemo(
    () => dashboardSettingsFromMetadata(parsedMetadata, fields),
    [parsedMetadata, fields],
  );

  const workspaceAclSettings = useMemo(
    () => aclSettingsFromMetadata(parsedMetadata),
    [parsedMetadata],
  );

  const setControlledDocumentSync = (settings: ControlledDocumentSyncEditorSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeControlledDocumentSyncIntoMetadata(meta, settings), null, 2);
    });
  };

  const setComposeSettings = (settings: FormComposeEditorSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeFormComposeIntoMetadata(meta, settings), null, 2);
    });
  };

  const setRevisionSettings = (settings: FormRevisionEditorSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeFormRevisionIntoMetadata(meta, settings), null, 2);
    });
  };

  const setOutboundSettings = (settings: FormOutboundEditorSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeFormOutboundIntoMetadata(meta, settings), null, 2);
    });
  };

  const setWorkspaceSettings = (settings: FormWorkspaceEditorSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeWorkspaceIntoMetadata(meta, settings), null, 2);
    });
  };

  const setWorkspaceDashboardSettings = (settings: FormWorkspaceDashboardSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeDashboardIntoWorkspaceMetadata(meta, settings), null, 2);
    });
  };

  const setWorkspaceAclSettings = (settings: FormWorkspaceAclSettings) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify(mergeAclIntoWorkspaceMetadata(meta, settings), null, 2);
    });
  };

  const handleLayoutRowsChange = (rows: EApprovalBuilderLayoutRow[]) => {
    const meta = parseFormMetadataJson(metadataJson);
    setMetadataJson(JSON.stringify(patchBuilderLayoutRows(meta, rows), null, 2));
  };

  const editorSnapshot = useMemo(
    () => ({
      name,
      description,
      status,
      fields,
      steps,
      metadataJson,
      brandLogoUrl,
      documentNumber,
    }),
    [name, description, status, fields, steps, metadataJson, brandLogoUrl, documentNumber],
  );

  const editorDirtyEnabled = !showCreateWizard;
  const { dirty, markSaved, reconcileAfterSave, resetBaseline } = useEApprovalFormEditorDirty(
    editorSnapshot,
    editorDirtyEnabled,
  );
  const [autosaveState, setAutosaveState] = useState<"idle" | "saving" | "saved" | "error">("idle");
  const autosaveSeqRef = useRef(0);
  const editorSnapshotRef = useRef(editorSnapshot);
  editorSnapshotRef.current = editorSnapshot;

  const applyFormFromDetail = (f: EApprovalFormDetail) => {
    const rawMetadata =
      f.metadata_json && Object.keys(f.metadata_json).length > 0 ? f.metadata_json : {};
    const migration = migrateLegacyWorkflowOnLoad(rawMetadata, f.steps ?? []);

    setName(f.name);
    setDescription(f.description ?? "");
    setStatus(f.status === "published" ? "published" : "draft");
    setFields(f.fields?.length ? f.fields : [{ type: "text", name: "summary", label: "Summary" }]);
    setSteps(migration.steps);
    setBrandLogoUrl(f.brand_logo_url ?? null);
    setAcceptsNewSubmissions(f.accepts_new_submissions !== false);
    setDocumentNumber(documentNumberSettingsFromFormDetail(f));
    setMetadataJson(
      Object.keys(migration.metadata).length > 0 ? JSON.stringify(migration.metadata, null, 2) : "{}",
    );
  };

  const handleRevisionRestored = () => {
    if (formId) {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "revisions"] });
    }
  };

  useEffect(() => {
    if (!isNew) return;
    const stored = readStoredImportDraft();
    if (stored) applyImportPayload(stored);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- run once on new form mount
  }, [isNew]);

  useEffect(() => {
    if (!formQuery.data || isNew) return;
    const updatedAt = formQuery.dataUpdatedAt ?? 0;
    if (updatedAt === lastSyncedFormAt.current) return;
    lastSyncedFormAt.current = updatedAt;

    applyFormFromDetail(formQuery.data);
    resetBaseline(
      serializeEApprovalFormEditorSnapshot({
        name: formQuery.data.name,
        description: formQuery.data.description ?? "",
        status: formQuery.data.status === "published" ? "published" : "draft",
        fields: formQuery.data.fields ?? [],
        steps: formQuery.data.steps ?? [],
        metadataJson:
          formQuery.data.metadata_json && Object.keys(formQuery.data.metadata_json).length > 0
            ? JSON.stringify(formQuery.data.metadata_json, null, 2)
            : "{}",
        brandLogoUrl: formQuery.data.brand_logo_url ?? null,
        documentNumber: documentNumberSettingsFromFormDetail(formQuery.data),
      }),
    );
  }, [formQuery.data, formQuery.dataUpdatedAt, isNew, resetBaseline]);

  const logoMutation = useMutation({
    mutationFn: (file: File) => uploadEApprovalFormLogo(formId!, file),
    onSuccess: (result) => {
      setBrandLogoUrl(result.brand_logo_url);
      setLogoPreviewKey(Date.now());
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId] });
      push({ level: "success", title: "Logo uploaded" });
    },
    onError: (e) => push({ level: "error", title: "Logo upload failed", message: getErrorMessage(e) }),
  });

  const handleMetadataPatch = (patch: Record<string, unknown>) => {
    setMetadataJson((prev) => {
      const meta = parseFormMetadataJson(prev);
      return JSON.stringify({ ...meta, ...patch }, null, 2);
    });
  };

  const syncProcurementPrintTemplate = async (targetFormId: string) => {
    const meta = parseFormMetadataJson(metadataJson);
    const entry = resolvePrintTemplateEntryForForm(meta, fields);
    if (!entry) {
      return;
    }

    await updateEApprovalPdfLayout(targetFormId, {
      template: entry.buildDefaultTemplate(),
      active_preset_id: entry.kind,
    });
  };

  const buildSavePayload = () => {
    const trimmedName = name.trim();
    if (!trimmedName) {
      throw new Error("Form name is required.");
    }

    let metadata_json: Record<string, unknown> | null = null;
    const trimmedMeta = metadataJson.trim();
    if (trimmedMeta && trimmedMeta !== "{}") {
      try {
        metadata_json = JSON.parse(trimmedMeta) as Record<string, unknown>;
      } catch {
        throw new Error("Form metadata must be valid JSON.");
      }
    }

    const workspaceReadiness = workspaceEditorReadiness(
      workspaceSettingsFromMetadata(metadata_json ?? {}, trimmedName),
    );
    if (!workspaceReadiness.ok) {
      throw new Error(workspaceReadiness.message ?? "Workspace settings are invalid.");
    }

    const sanitizedSteps = compactWorkflowStepOrdersPreservingTies(
      getValidEApprovalWorkflowSteps(steps).map((step, index) => ({
        ...step,
        step_order: step.step_order ?? index + 1,
      })),
    );

    if (status === "published" && sanitizedSteps.length === 0) {
      throw new Error("Add at least one workflow step with an approver before publishing.");
    }

    const payload: Record<string, unknown> = {
      name: trimmedName,
      description: description.trim() || null,
      status,
      fields,
      steps: sanitizedSteps,
      metadata_json,
      brand_logo_url: brandLogoUrl,
      accepts_new_submissions: acceptsNewSubmissions,
      ...documentNumberSettingsToApiPayload(documentNumber),
    };

    if (confirmFormUpgrade) {
      payload.confirm_form_upgrade = true;
    }

    return payload;
  };

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = buildSavePayload();
      if (isNew) return createEApprovalForm(payload);
      return updateEApprovalForm(formId!, payload);
    },
    onSuccess: async (form) => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "forms"] });
      const savedFormId = formId ?? form.id;
      let poSyncWarning: string | null = null;
      if (savedFormId) {
        queryClient.invalidateQueries({ queryKey: ["e-approval", "form", savedFormId] });
        queryClient.invalidateQueries({ queryKey: ["e-approval", "form", savedFormId, "revisions"] });
        queryClient.invalidateQueries({ queryKey: ["e-approval", "workspaces"] });
        try {
          await syncProcurementPrintTemplate(savedFormId);
          queryClient.invalidateQueries({ queryKey: ["e-approval", "pdf-layout", savedFormId] });
        } catch {
          poSyncWarning =
            "Procurement print template could not be synced. Use Print / PDF tab to save layout.";
        }
      }
      setChecklistOpen(false);
      setPendingAction(null);
      setConfirmFormUpgrade(false);
      setAutosaveState("idle");
      markSaved();
      push({
        level: poSyncWarning ? "warning" : "success",
        title: isNew ? "Form created" : "Form saved",
        message: poSyncWarning ?? undefined,
      });
      if (isNew) {
        router.push(`/e-approval/forms/${form.id}`);
      }
    },
    onError: (e) => push({ level: "error", title: "Save failed", message: getErrorMessage(e) }),
  });

  const getDraftPayload = (): EApprovalFormImportPayload => ({
    name,
    description,
    status,
    fields,
    steps,
    metadataJson,
    brandLogoUrl,
    documentNumber,
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteEApprovalForm(formId!),
    onSuccess: () => {
      setDeleteDialogOpen(false);
      queryClient.invalidateQueries({ queryKey: ["e-approval", "forms"] });
      push({ level: "success", title: "Form deleted" });
      router.push("/e-approval/forms");
    },
    onError: (e) => push({ level: "error", title: "Delete failed", message: getErrorMessage(e) }),
  });

  const publishMutation = useMutation({
    mutationFn: () => {
      if (!hasValidEApprovalWorkflowSteps(steps)) {
        throw new Error("Add at least one workflow step with an approver before publishing.");
      }
      return publishEApprovalForm(formId!);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "form", formId, "revisions"] });
      queryClient.invalidateQueries({ queryKey: ["e-approval", "forms"] });
      setStatus("published");
      setChecklistOpen(false);
      setPendingAction(null);
      setConfirmFormUpgrade(false);
      markSaved();
      push({ level: "success", title: "Form published" });
    },
    onError: (e) => push({ level: "error", title: "Publish failed", message: getErrorMessage(e) }),
  });

  /**
   * Quiet draft autosave: debounce after edits, never refetch the form detail
   * (that would remount/reset the builder), and reconcile dirty state so typing
   * during the request is not discarded.
   */
  useEffect(() => {
    if (isNew || !formId || status !== "draft") {
      return;
    }
    if (!dirty || !editorDirtyEnabled) {
      return;
    }
    if (
      checklistOpen ||
      deleteDialogOpen ||
      showImportPanel ||
      showCreateWizard ||
      saveMutation.isPending ||
      publishMutation.isPending
    ) {
      return;
    }
    if (!name.trim()) {
      return;
    }

    const trimmedMeta = metadataJson.trim();
    if (trimmedMeta && trimmedMeta !== "{}") {
      try {
        JSON.parse(trimmedMeta);
      } catch {
        return;
      }
    }

    const timer = window.setTimeout(() => {
      const seq = ++autosaveSeqRef.current;

      void (async () => {
        setAutosaveState("saving");
        try {
          // Capture what we persist from the same moment as the request payload.
          const savedSerialized = serializeEApprovalFormEditorSnapshot(editorSnapshotRef.current);
          const payload = buildSavePayload();
          await updateEApprovalForm(formId, payload);
          if (seq !== autosaveSeqRef.current) {
            return;
          }
          // Soft list refresh only — do not invalidate form detail (avoids canvas reset).
          void queryClient.invalidateQueries({ queryKey: ["e-approval", "forms"] });
          reconcileAfterSave(savedSerialized);
          setAutosaveState("saved");
        } catch {
          if (seq !== autosaveSeqRef.current) {
            return;
          }
          setAutosaveState("error");
        }
      })();
    }, 2500);

    return () => window.clearTimeout(timer);
    // eslint-disable-next-line react-hooks/exhaustive-deps -- editorSnapshot resets debounce; buildSavePayload uses live state
  }, [
    dirty,
    editorDirtyEnabled,
    editorSnapshot,
    isNew,
    formId,
    status,
    name,
    metadataJson,
    checklistOpen,
    deleteDialogOpen,
    showImportPanel,
    showCreateWizard,
    saveMutation.isPending,
    publishMutation.isPending,
    reconcileAfterSave,
    queryClient,
  ]);

  useEffect(() => {
    if (autosaveState !== "saved") {
      return;
    }
    const timer = window.setTimeout(() => setAutosaveState("idle"), 2500);
    return () => window.clearTimeout(timer);
  }, [autosaveState]);

  const openChecklist = (action: "save" | "publish") => {
    setConfirmFormUpgrade(false);
    const items = buildFormPublishChecklist({
      formName: name,
      fields,
      steps,
      requireWorkflow: action === "publish" || status === "published",
      pendingSubmissionsCount,
      planFeatures: {
        file_uploads: planFeatures.fileUploadsAllowed,
        max_file_fields: planFeatures.maxFileFields,
        plan_tier: planFeatures.planTier,
      },
      composeSettings,
    });
    setChecklistItems(items);
    setPendingAction(action);
    setChecklistOpen(true);
  };

  const runPendingAction = async () => {
    if (pendingAction === "publish") {
      try {
        await saveMutation.mutateAsync();
        await publishMutation.mutateAsync();
      } catch {
        return;
      }
      return;
    }
    setChecklistOpen(false);
    setPendingAction(null);
    saveMutation.mutate();
  };

  const revisions = revisionsQuery.data ?? [];

  const currentSnapshot = useMemo(
    () => ({
      name,
      description,
      status,
      fields,
      steps,
    }),
    [name, description, status, fields, steps],
  );

  const checklistTitle =
    pendingAction === "publish" ? "Publish checklist" : status === "published" ? "Save published form" : "Save checklist";
  const checklistDescription =
    pendingAction === "publish"
      ? "Review these items before making the form available to requestors."
      : "Fix blocking issues before saving. Warnings can be addressed later.";

  if (isNew && showCreateWizard) {
    return (
      <PermissionGate requiredPermissions={[permissions.eApprovalFormsManage]}>
        <div className="space-y-6">
          <EApprovalFormCreateWizard
            name={name}
            description={description}
            fields={fields}
            steps={steps}
            approverOptions={approverOptions}
            onNameChange={setName}
            onDescriptionChange={setDescription}
            onFieldsChange={setFields}
            onStepsChange={setSteps}
            onTemplateCreated={(id) => router.push(`/e-approval/forms/${id}?tab=design`)}
            onOpenImport={() => {
              setShowCreateWizard(false);
              setShowImportPanel(true);
            }}
            onSkipToEditor={() => setShowCreateWizard(false)}
            onCreated={(id) => router.push(`/e-approval/forms/${id}?tab=design`)}
          />
        </div>
      </PermissionGate>
    );
  }

  return (
    <PermissionGate requiredPermissions={[permissions.eApprovalFormsManage]}>
      <div className="space-y-6">
        <header className="flex flex-wrap items-start justify-between gap-4">
          <div className="min-w-0">
            <p className="text-xs font-medium text-muted-foreground">E-Approval form</p>
            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
              {isNew ? "New form" : name.trim() || "Untitled form"}
            </h1>
          </div>
          <div className="flex flex-wrap items-center justify-end gap-2">
          {autosaveState === "saving" ? (
            <Badge variant="outline" className="h-8 text-muted-foreground">
              Saving draft…
            </Badge>
          ) : autosaveState === "saved" && !dirty ? (
            <Badge variant="outline" className="h-8 border-emerald-300 text-emerald-800 dark:text-emerald-100">
              Draft saved
            </Badge>
          ) : autosaveState === "error" && dirty ? (
            <Badge variant="outline" className="h-8 border-amber-300 text-amber-900 dark:text-amber-100">
              Autosave failed — use Save
            </Badge>
          ) : dirty ? (
            <Badge variant="outline" className="h-8 border-amber-300 text-amber-900 dark:text-amber-100">
              Unsaved changes
            </Badge>
          ) : null}
          <Button type="button" size="sm" variant="outline" onClick={() => setShowImportPanel(true)}>
            <FileJson className="mr-1.5 h-3.5 w-3.5" />
            Form JSON
          </Button>
          {!isNew ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              className="text-destructive hover:bg-destructive/10 hover:text-destructive"
              onClick={() => setDeleteDialogOpen(true)}
              disabled={deleteMutation.isPending}
              title={canDeleteForm ? "Permanently delete this form" : "Cannot delete a form with submissions"}
            >
              <Trash2 className="mr-1.5 h-3.5 w-3.5" />
              Delete
            </Button>
          ) : null}
          {!isNew ? (
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={() => openChecklist("publish")}
              disabled={publishMutation.isPending || !workflowReady}
              title={workflowReady ? undefined : "Add at least one valid workflow step before publishing"}
            >
              Publish
            </Button>
          ) : null}
          <Button
            type="button"
            size="sm"
            onClick={() => openChecklist("save")}
            disabled={!name.trim() || saveMutation.isPending}
          >
            Save
          </Button>
        </div>
        </header>

        <Tabs key={formId ?? "new"} defaultValue={defaultTab} className="space-y-4">
          <TabsList variant="line" className="w-full justify-start gap-1 overflow-x-auto">
            <TabsTrigger value="setup" className="gap-1.5">
              <Settings2 className="h-3.5 w-3.5" />
              Setup
            </TabsTrigger>
            <TabsTrigger value="design" className="gap-1.5">
              <PenLine className="h-3.5 w-3.5" />
              Design
              {!workflowReady ? (
                <Badge variant="outline" className="ml-1 h-5 border-amber-300 px-1 text-[10px] text-amber-800">
                  !
                </Badge>
              ) : null}
            </TabsTrigger>
            <TabsTrigger value="workflow" className="gap-1.5">
              <Workflow className="h-3.5 w-3.5" />
              Workflow
              {!workflowReady ? (
                <Badge variant="outline" className="ml-1 h-5 border-amber-300 px-1 text-[10px] text-amber-800">
                  !
                </Badge>
              ) : null}
            </TabsTrigger>
            <TabsTrigger value="preview" className="gap-1.5">
              <Eye className="h-3.5 w-3.5" />
              Preview
            </TabsTrigger>
            {!isNew ? (
              <TabsTrigger value="history" className="gap-1.5">
                <GitBranch className="h-3.5 w-3.5" />
                History
              </TabsTrigger>
            ) : null}
            {!isNew ? (
              <TabsTrigger value="workspace" className="gap-1.5">
                <LayoutDashboard className="h-3.5 w-3.5" />
                Workspace
                {workspaceSettings.enabled ? (
                  <Badge variant="outline" className="ml-1 h-5 border-primary/30 px-1 text-[10px] text-primary">
                    On
                  </Badge>
                ) : null}
              </TabsTrigger>
            ) : null}
            {!isNew ? (
              <TabsTrigger value="print" className="gap-1.5">
                <Printer className="h-3.5 w-3.5" />
                Print layout
              </TabsTrigger>
            ) : null}
          </TabsList>

          <TabsContent value="setup" className="mt-0 space-y-4">
            {isNew ? (
              <EApprovalFormTemplateGallery onCreated={(id) => router.push(`/e-approval/forms/${id}`)} />
            ) : null}

            <EApprovalSectionCard title="Form details" description="Name and visibility for requestors.">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="ea-form-name">Form name</Label>
                  <Input id="ea-form-name" value={name} onChange={(e) => setName(e.target.value)} />
                </div>
                <div className="space-y-2">
                  <Label htmlFor="ea-form-status">Status</Label>
                  <Select
                    id="ea-form-status"
                    value={status}
                    onChange={(e) => setStatus(e.target.value as "draft" | "published")}
                  >
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                  </Select>
                </div>
                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="ea-form-desc">Description</Label>
                  <Textarea
                    id="ea-form-desc"
                    className="min-h-[80px]"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Shown when requestors pick this form for a new submission."
                  />
                </div>
              </div>
            </EApprovalSectionCard>

            <EApprovalFormDocumentNumberCard
              value={documentNumber}
              onChange={setDocumentNumber}
              fields={fields}
              knownDepartments={knownDepartments}
            />

            <EApprovalFormControlledDocumentSyncCard
              value={controlledDocumentSync}
              onChange={setControlledDocumentSync}
              fields={fields}
            />

            {!isNew ? <EApprovalPublicLinksPanel formId={formId!} formPublished={status === "published"} /> : null}

            {!isNew && status === "published" ? (
              <EApprovalSectionCard
                title="Form lifecycle"
                description="Stop new requests without deleting historical submissions."
              >
                <div className="flex items-center justify-between gap-4">
                  <div className="space-y-1">
                    <p className="text-sm font-medium text-foreground">Accept new submissions</p>
                    <p className="text-sm text-muted-foreground">
                      Turn off to retire this form. Existing submissions and approvals continue unchanged.
                      {pendingSubmissionsCount > 0
                        ? ` ${pendingSubmissionsCount} open submission${pendingSubmissionsCount === 1 ? "" : "s"} remain in flight.`
                        : ""}
                    </p>
                  </div>
                  <Switch checked={acceptsNewSubmissions} onCheckedChange={setAcceptsNewSubmissions} />
                </div>
              </EApprovalSectionCard>
            ) : null}

            {!isNew ? (
              <EApprovalSectionCard
                title="Danger zone"
                description="Permanent actions that cannot be undone."
                className="border-destructive/30"
              >
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="space-y-1">
                    <p className="text-sm font-medium text-foreground">Delete this form</p>
                    {canDeleteForm ? (
                      <p className="text-sm text-muted-foreground">
                        Remove the form definition, workflow, print layout, and public links.
                      </p>
                    ) : (
                      <p className="text-sm text-muted-foreground">
                        Deletion is blocked because this form has {submissionsCount} submission
                        {submissionsCount === 1 ? "" : "s"}.
                      </p>
                    )}
                  </div>
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    onClick={() => setDeleteDialogOpen(true)}
                    disabled={deleteMutation.isPending}
                  >
                    Delete form
                  </Button>
                </div>
              </EApprovalSectionCard>
            ) : null}

            {!isNew ? (
              <EApprovalSectionCard
                title="Branding & advanced metadata"
                description="Logo for print layouts. Controlled document sync is configured above; use JSON for other advanced options."
              >
                <div className="flex flex-wrap items-center gap-3">
                  {brandLogoUrl && formId ? (
                    <EApprovalFormBrandLogoPreview formId={formId} refreshKey={logoPreviewKey} />
                  ) : (
                    <span className="text-sm text-muted-foreground">No logo uploaded.</span>
                  )}
                  <label className="cursor-pointer">
                    <span className="inline-flex h-9 items-center rounded-md border border-input bg-background px-3 text-sm hover:bg-muted">
                      {logoMutation.isPending ? "Uploading…" : "Upload logo"}
                    </span>
                    <input
                      type="file"
                      accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml"
                      className="sr-only"
                      onChange={(e) => {
                        const file = e.target.files?.[0];
                        if (file) logoMutation.mutate(file);
                        e.target.value = "";
                      }}
                    />
                  </label>
                </div>
                <label className="mt-4 flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-3">
                  <span>
                    <span className="block text-sm font-medium text-foreground">Use approval policy matrix</span>
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                      Compile workflow steps from tenant DOA rules at submit time instead of static form steps.
                    </span>
                  </span>
                  <Switch
                    checked={Boolean(parseFormMetadataJson(metadataJson).use_approval_policy)}
                    onCheckedChange={(checked) => {
                      setMetadataJson((prev) => {
                        const meta = parseFormMetadataJson(prev);
                        if (checked) {
                          meta.use_approval_policy = true;
                        } else {
                          delete meta.use_approval_policy;
                        }

                        return JSON.stringify(meta, null, 2);
                      });
                    }}
                  />
                </label>
                <Label className="mt-4 block">
                  <span className="text-xs font-medium text-muted-foreground">Form metadata (JSON)</span>
                  <Textarea
                    className="mt-1 min-h-[140px] font-mono text-xs"
                    value={metadataJson}
                    onChange={(e) => setMetadataJson(e.target.value)}
                    spellCheck={false}
                  />
                </Label>
              </EApprovalSectionCard>
            ) : null}
          </TabsContent>

          <TabsContent value="design" className="mt-0 space-y-6">
            {apiKeysLocked ? (
              <p className="text-xs text-muted-foreground">
                Field API keys are locked because this form is published
                {submissionsCount > 0 ? ` or has ${submissionsCount} submission${submissionsCount === 1 ? "" : "s"}` : ""}.
              </p>
            ) : null}
            <EApprovalFormComposeSettingsCard
              value={composeSettings}
              onChange={setComposeSettings}
              fields={fields}
              formName={name}
              formDescription={description}
              approverOptions={approverOptions}
              metadata={parseFormMetadataJson(metadataJson)}
            />
            <EApprovalVisualFormBuilder
              fields={fields}
              onFieldsChange={setFields}
              layoutRows={layoutRows}
              onLayoutRowsChange={handleLayoutRowsChange}
              onMetadataPatch={handleMetadataPatch}
              apiKeysLocked={apiKeysLocked}
              composeSettings={composeSettings}
            />
            {!isNew && formId ? (
              <EApprovalFormVersionTimeline
                formId={formId}
                revisions={revisions}
                currentSnapshot={currentSnapshot}
                submissionsCount={submissionsCount}
                onRestored={handleRevisionRestored}
              />
            ) : null}
          </TabsContent>

          <TabsContent value="workflow" className="mt-0 space-y-4">
            {!workflowReady ? (
              <div
                className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100"
                role="status"
              >
                <p className="font-medium">Workflow steps required</p>
                <p className="mt-1 text-amber-900/90 dark:text-amber-100/90">
                  Add at least one approver step before publishing.
                </p>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground" role="status">
                {workflowStepStatusLabel(steps)}
              </p>
            )}
            <EApprovalWorkflowEditor
              fields={fields}
              steps={steps}
              onStepsChange={setSteps}
              approverOptions={approverOptions}
              formId={formId}
            />
            <EApprovalFormRevisionSettingsCard
              value={revisionSettings}
              onChange={setRevisionSettings}
              fields={fields}
            />
            <EApprovalFormOutboundSettingsCard
              formId={isNew ? null : formId!}
              value={outboundSettings}
              onChange={setOutboundSettings}
            />
          </TabsContent>

          <TabsContent value="preview" className="mt-0">
            <EApprovalFormPreview
              formName={name}
              formDescription={description}
              fields={fields}
              approverOptions={approverOptions}
              formMetadata={parseFormMetadataJson(metadataJson)}
            />
          </TabsContent>

          {!isNew && formId ? (
            <TabsContent value="history" className="mt-0">
              <EApprovalFormVersionTimeline
                formId={formId}
                revisions={revisions}
                currentSnapshot={currentSnapshot}
                submissionsCount={submissionsCount}
                onRestored={handleRevisionRestored}
              />
            </TabsContent>
          ) : null}

          {!isNew && formId ? (
            <TabsContent value="workspace" className="mt-0 space-y-4">
              <EApprovalFormWorkspaceSettingsCard
                value={workspaceSettings}
                onChange={setWorkspaceSettings}
                formName={name}
                formId={formId}
                formPublished={status === "published"}
                metadata={parsedMetadata}
              />
              {workspaceSettings.enabled ? (
                <EApprovalFormWorkspaceDashboardCard
                  value={workspaceDashboardSettings}
                  onChange={setWorkspaceDashboardSettings}
                  fields={fields}
                />
              ) : null}
              {workspaceSettings.enabled ? (
                <EApprovalFormWorkspaceAclCard
                  value={workspaceAclSettings}
                  onChange={setWorkspaceAclSettings}
                  currentFormId={formId}
                  formRestrictedTo={formQuery.data?.restricted_to ?? null}
                />
              ) : null}
              <EApprovalSectionCard
                title="How workspace works"
                description="Operational layer on top of this form — submit, approve, and print are unchanged."
              >
                <ul className="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                  <li>Requestors see their own submissions; approvers see assigned items.</li>
                  <li>Form admins with workspace visibility see all submissions for this form.</li>
                  <li>Field and workflow changes apply after you publish the form.</li>
                  <li>Print layout and signatures use the existing Print layout tab.</li>
                </ul>
              </EApprovalSectionCard>
            </TabsContent>
          ) : null}

          {!isNew && formId ? (
            <TabsContent value="print" className="mt-0">
              <EApprovalPrintLayoutEditor formId={formId} fields={fields} />
            </TabsContent>
          ) : null}
        </Tabs>

        <EApprovalFormPublishChecklistDialog
          open={checklistOpen}
          onOpenChange={(open) => {
            setChecklistOpen(open);
            if (!open) {
              setPendingAction(null);
            }
          }}
          title={checklistTitle}
          description={checklistDescription}
          items={checklistItems}
          confirmLabel={pendingAction === "publish" ? "Publish" : "Save"}
          confirming={saveMutation.isPending || publishMutation.isPending}
          upgradeConfirm={
            requiresUpgradeConfirm
              ? {
                  required: true,
                  checked: confirmFormUpgrade,
                  onCheckedChange: setConfirmFormUpgrade,
                }
              : undefined
          }
          onConfirm={runPendingAction}
        />

        {!isNew ? (
          <EApprovalFormDeleteDialog
            open={deleteDialogOpen}
            onOpenChange={setDeleteDialogOpen}
            formName={name}
            submissionsCount={submissionsCount}
            confirming={deleteMutation.isPending}
            onConfirm={() => deleteMutation.mutate()}
          />
        ) : null}

        <Dialog open={showImportPanel} onOpenChange={setShowImportPanel}>
          <DialogContent className="w-[min(calc(100vw-1rem),720px)]">
            <DialogHeader>
              <DialogTitle>Form definition JSON</DialogTitle>
            </DialogHeader>
            <DialogBody>
              {showImportPanel ? (
                <EApprovalFormImportExportPanel
                  formId={formId}
                  formName={name}
                  getDraftPayload={getDraftPayload}
                  seedFromDraft
                  onLoadIntoEditor={(payload) => {
                    applyImportPayload(payload);
                    setShowImportPanel(false);
                  }}
                  onImported={(id) => {
                    setShowImportPanel(false);
                    router.push(`/e-approval/forms/${id}?tab=design`);
                  }}
                />
              ) : null}
            </DialogBody>
          </DialogContent>
        </Dialog>
      </div>
    </PermissionGate>
  );
}
