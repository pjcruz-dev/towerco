import {
  Building2,
  ClipboardCheck,
  ClipboardList,
  FileText,
  LifeBuoy,
  Landmark,
  MapPin,
  Package,
  User,
  Waypoints,
  Zap,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { WorkspaceSearchResult } from "@/lib/api/modules/workspace-search-api";
import { TENANT_MODULE_LABELS } from "@/lib/tenant/enabled-modules";
import type { WorkspaceCommandItem } from "@/lib/navigation/workspace-command-index";
import { formatEApprovalStatusLabel } from "@/modules/e-approval/status-display";

const ENTITY_ICONS: Record<string, LucideIcon> = {
  "e_approval:submission": ClipboardCheck,
  "e_approval:form": ClipboardList,
  "ticketing:ticket": LifeBuoy,
  "sites:site": MapPin,
  "documents:document": FileText,
  "documents:controlled_document": ClipboardCheck,
  "tower_one:tower": Landmark,
  "asset_one:asset": Package,
  "fiber_one:fiber_route": Waypoints,
  "project_one:project": Building2,
  "project_one:rollout": Zap,
  "team_access:user": User,
};

function resolveEntityIcon(module: string, entityType: string): LucideIcon {
  return ENTITY_ICONS[`${module}:${entityType}`] ?? MapPin;
}

function entityTypeLabel(module: string, entityType: string): string {
  const labels: Record<string, string> = {
    submission: "Submission",
    form: "Form",
    ticket: "Ticket",
    site: "Site",
    document: "Document",
    controlled_document: "Controlled document",
    tower: "Tower",
    asset: "Asset",
    fiber_route: "Fiber route",
    project: "Project",
    rollout: "Rollout",
    user: "User",
  };

  const moduleLabel = TENANT_MODULE_LABELS[module] ?? module.replace(/_/g, " ");
  const typeLabel = labels[entityType] ?? entityType.replace(/_/g, " ");

  return `${moduleLabel} · ${typeLabel}`;
}

function resolveStatusLabel(result: WorkspaceSearchResult): string | null {
  if (result.status_label && result.status_label.trim() !== "") {
    return result.status_label.trim();
  }

  if (!result.status || result.status.trim() === "") {
    return null;
  }

  if (result.module === "e_approval") {
    return formatEApprovalStatusLabel(result.status);
  }

  return result.status
    .split(/[_\s-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

export function workspaceSearchResultsToCommandItems(
  results: WorkspaceSearchResult[],
): WorkspaceCommandItem[] {
  return results.map((result) => {
    const statusLabel = resolveStatusLabel(result);
    const descriptionParts = [entityTypeLabel(result.module, result.entity_type), result.subtitle]
      .filter(Boolean)
      .join(" · ");

    return {
      id: `entity:${result.module}:${result.entity_type}:${result.id}`,
      kind: "entity",
      title: result.title,
      description: descriptionParts || undefined,
      href: result.href,
      icon: resolveEntityIcon(result.module, result.entity_type),
      group: "Find",
      module: result.module,
      status: result.status,
      statusLabel,
      keywords: [
        result.title,
        result.subtitle ?? "",
        result.status ?? "",
        statusLabel ?? "",
        result.waiting_on ?? "",
        result.current_step != null ? `step ${result.current_step}` : "",
        result.entity_type,
        result.module,
      ],
    };
  });
}
