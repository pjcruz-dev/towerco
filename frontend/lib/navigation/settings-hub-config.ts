import type { LucideIcon } from "lucide-react";
import {
  Building2,
  ClipboardCheck,
  HardDrive,
  LifeBuoy,
  MessageSquareText,
  Settings2,
  Shield,
  ShoppingCart,
  SlidersHorizontal,
} from "lucide-react";

import { permissions } from "@/lib/rbac/permissions";

export type SettingsHubItem = {
  id: string;
  title: string;
  description: string;
  href: string;
  icon: LucideIcon;
  section: string;
  module?: string;
  requiredPermissions: string[];
};

export type SettingsHubSection = {
  id: string;
  title: string;
  items: SettingsHubItem[];
};

export const settingsHubSections: SettingsHubSection[] = [
  {
    id: "platform",
    title: "Platform",
    items: [
      {
        id: "sign-in-security",
        title: "Sign-in & security",
        description: "Microsoft Entra SSO, auto-provision, password policy, and domain allowlists.",
        href: "/admin/settings",
        icon: Shield,
        section: "Platform",
        requiredPermissions: [permissions.tenantManage],
      },
      {
        id: "backups",
        title: "Backups",
        description: "Download completed database backups for this organization.",
        href: "/admin/backups",
        icon: HardDrive,
        section: "Platform",
        requiredPermissions: [permissions.tenantManage],
      },
      {
        id: "kpi-sla",
        title: "KPI & SLA",
        description: "KPI definitions, SLA timers, and workflow templates.",
        href: "/admin/settings/kpi",
        icon: SlidersHorizontal,
        section: "Platform",
        requiredPermissions: [permissions.tenantManage],
      },
    ],
  },
  {
    id: "e-approval",
    title: "E-Approval",
    items: [
      {
        id: "ea-policies",
        title: "Module policies",
        description: "Cash advance, liquidation, and PO overspend rules.",
        href: "/e-approval/settings",
        icon: ClipboardCheck,
        section: "E-Approval",
        module: "e_approval",
        requiredPermissions: [permissions.eApprovalSettingsManage],
      },
      {
        id: "ea-doa",
        title: "Approval policies",
        description: "Delegation-of-authority matrix for compiled workflows.",
        href: "/e-approval/approval-policies",
        icon: ClipboardCheck,
        section: "E-Approval",
        module: "e_approval",
        requiredPermissions: [permissions.eApprovalSettingsManage],
      },
      {
        id: "ea-master-data",
        title: "Master data",
        description: "Lookup sets used by forms, workflows, and vendor registration.",
        href: "/e-approval/master-data",
        icon: ClipboardCheck,
        section: "E-Approval",
        module: "e_approval",
        requiredPermissions: [permissions.eApprovalSettingsManage],
      },
    ],
  },
  {
    id: "procurement",
    title: "Procurement-One",
    items: [
      {
        id: "procurement-settings",
        title: "Module settings",
        description: "Document types, numbering, vendors, budget, inventory, and export policies.",
        href: "/procurement/settings",
        icon: ShoppingCart,
        section: "Procurement-One",
        module: "procurement_one",
        requiredPermissions: [permissions.procurementOneSettingsManage],
      },
    ],
  },
  {
    id: "ticketing",
    title: "Ticketing",
    items: [
      {
        id: "ticketing-settings",
        title: "Module settings",
        description: "IT email routing, SLA timers, categories, and Teams webhook.",
        href: "/ticketing/settings",
        icon: LifeBuoy,
        section: "Ticketing",
        module: "ticketing",
        requiredPermissions: [permissions.ticketingSettingsManage],
      },
    ],
  },
  {
    id: "project-one",
    title: "Project-One",
    items: [
      {
        id: "playbook",
        title: "Rollout playbook",
        description: "Gate templates and discipline steps used across rollouts.",
        href: "/project-one/rollout-playbook",
        icon: Building2,
        section: "Project-One",
        module: "project_one",
        requiredPermissions: [permissions.projectOneView],
      },
      {
        id: "holidays",
        title: "Public holidays",
        description: "Regional holiday calendar for SLA and timeline calculations.",
        href: "/project-one/public-holidays",
        icon: Building2,
        section: "Project-One",
        module: "project_one",
        requiredPermissions: [permissions.projectOneView],
      },
    ],
  },
  {
    id: "ai-assistant",
    title: "AI Assistant",
    items: [
      {
        id: "assistant-knowledge",
        title: "Knowledge base",
        description: "Tenant SOPs and internal help articles used by Ask TowerOS.",
        href: "/settings/ai-assistant/knowledge",
        icon: MessageSquareText,
        section: "AI Assistant",
        module: "ai_assistant",
        requiredPermissions: [permissions.aiAssistantKnowledgeManage],
      },
    ],
  },
];

export const settingsHubFallbackIcon = Settings2;
