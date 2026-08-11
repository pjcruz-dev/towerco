import type { EApprovalFormWorkspaceSummary } from "@/modules/e-approval/form-workspace-types";
import type { WorkspaceCommandItem } from "@/lib/navigation/workspace-command-index";
import { LayoutDashboard } from "lucide-react";

export function formWorkspacesToCommandItems(
  workspaces: EApprovalFormWorkspaceSummary[],
): WorkspaceCommandItem[] {
  return workspaces.map((workspace) => ({
    id: `workspace:${workspace.slug}`,
    kind: "navigate",
    title: workspace.title,
    description: workspace.is_multi_form
      ? `Multi-form workspace · ${workspace.form_name}`
      : `Form workspace · ${workspace.form_name}`,
    href: `/e-approval/w/${workspace.slug}`,
    icon: LayoutDashboard,
    group: "E-Approval",
    parent: "E-Approval",
    section: "Workspaces",
    module: "e_approval",
    keywords: [workspace.title, workspace.form_name, workspace.slug, "workspace", "e-approval"],
  }));
}
