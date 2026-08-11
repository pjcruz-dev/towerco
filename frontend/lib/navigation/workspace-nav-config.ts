import {
  Building2,
  ClipboardCheck,
  CreditCard,
  FileText,
  LifeBuoy,
  Landmark,
  LayoutDashboard,
  Map,
  MapPin,
  Package,
  PiggyBank,
  PlusCircle,
  Settings,
  ScrollText,
  ShoppingCart,
  Users,
  Waypoints,
  Zap,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { ProcurementPlanFeatureKey } from "@/lib/procurement/procurement-plan-features";
import { financeOneRoutes } from "@/lib/navigation/finance-one-routes";

export type WorkspaceSubNavItem = {
  title: string;
  href: string;
  exact?: boolean;
  section?: string;
  permissions: string[];
  badge?: number;
  module?: string;
  procurementPlanFeature?: ProcurementPlanFeatureKey;
};

export type WorkspaceTopNavItem = {
  title: string;
  icon: LucideIcon;
  href?: string;
  exact?: boolean;
  permissions: string[];
  module?: string;
  moduleGate?: "notifications";
  permissionsMatch?: "all" | "any";
  items?: WorkspaceSubNavItem[];
};

export type WorkspaceNavGroup = {
  group: string;
  items: WorkspaceTopNavItem[];
};

export type WorkspaceQuickAction = {
  id: string;
  title: string;
  description: string;
  href: string;
  icon: LucideIcon;
  module?: string;
  moduleGate?: "notifications";
  permissions: string[];
  permissionsMatch?: "all" | "any";
  keywords?: string[];
};

/** Tenant workspace sidebar navigation — single source for sidebar + command palette. */
export const workspaceNavGroups: WorkspaceNavGroup[] = [
  {
    group: "Operations",
    items: [
      {
        title: "Dashboard",
        icon: LayoutDashboard,
        href: "/dashboard",
        permissions: ["dashboard:view"],
        module: "core",
      },
      {
        title: "Sites",
        icon: MapPin,
        href: "/sites",
        permissions: ["sites:view"],
        module: "sites",
      },
      {
        title: "Documents",
        icon: FileText,
        module: "documents",
        permissions: ["documents:view", "documents:template:manage"],
        items: [
          {
            title: "Site binders",
            href: "/documents",
            exact: true,
            permissions: ["documents:view"],
          },
          {
            title: "Binder template",
            href: "/documents/settings",
            permissions: ["documents:template:manage"],
          },
        ],
      },
      {
        title: "Document register",
        icon: ClipboardCheck,
        href: "/documents/controlled",
        module: "document_register",
        permissions: ["documents:controlled:view"],
      },
      {
        title: "Project-One",
        icon: Building2,
        module: "project_one",
        permissions: ["project_one:view"],
        items: [
          { title: "Overview", href: "/project-one", exact: true, section: "Operate", permissions: ["project_one:view"] },
          { title: "Rollouts", href: "/project-one/rollouts", section: "Operate", permissions: ["project_one:rollout:view"] },
          { title: "Projects", href: "/project-one/projects", section: "Operate", permissions: ["project_one:view"] },
          { title: "Approvals", href: "/project-one/approvals", section: "Decide", permissions: ["project_one:view"] },
          {
            title: "Gate approvals",
            href: "/project-one/gate-approvals?awaiting_me=1",
            section: "Decide",
            permissions: ["project_one:rollout:view"],
          },
        ],
      },
      {
        title: "TOWER-ONE",
        icon: Landmark,
        module: "tower_one",
        permissions: ["tower_one:view"],
        items: [
          { title: "Overview", href: "/tower-one", exact: true, permissions: ["tower_one:view"] },
          { title: "Towers", href: "/tower-one/towers", permissions: ["tower_one:view"] },
        ],
      },
      {
        title: "FIBER-ONE",
        icon: Waypoints,
        module: "fiber_one",
        permissions: ["fiber_one:view"],
        items: [
          { title: "Overview", href: "/fiber-one", exact: true, permissions: ["fiber_one:view"] },
          { title: "Routes", href: "/fiber-one/routes", permissions: ["fiber_one:view"] },
        ],
      },
      {
        title: "ASSET-ONE",
        icon: Package,
        module: "asset_one",
        permissions: ["asset_one:view"],
        items: [
          { title: "Overview", href: "/asset-one", exact: true, permissions: ["asset_one:view"] },
          { title: "Assets", href: "/asset-one/assets", permissions: ["asset_one:view"] },
        ],
      },
      {
        title: "Ticketing",
        icon: LifeBuoy,
        module: "ticketing",
        permissions: ["ticketing:view", "ticketing:tickets:create", "ticketing:tickets:manage"],
        items: [
          { title: "Overview", href: "/ticketing", exact: true, section: "Operate", permissions: ["ticketing:view"] },
          { title: "Tickets", href: "/ticketing/tickets", section: "Operate", permissions: ["ticketing:view"] },
          {
            title: "New ticket",
            href: "/ticketing/tickets/new",
            section: "Operate",
            permissions: ["ticketing:tickets:create"],
          },
        ],
      },
      {
        title: "Procurement-One",
        icon: ShoppingCart,
        module: "procurement_one",
        permissions: [
          "procurement_one:view",
          "procurement_one:documents:create",
          "procurement_one:documents:manage",
          "procurement_one:settings:manage",
          "procurement_one:vendors:view",
          "procurement_one:vendors:manage",
        ],
        items: [
          {
            title: "Overview",
            href: "/procurement",
            exact: true,
            section: "Operate",
            permissions: ["procurement_one:view"],
          },
          {
            title: "Purchase requisitions",
            href: "/procurement/prs",
            section: "Operate",
            permissions: ["procurement_one:view"],
          },
          {
            title: "Purchase orders",
            href: "/procurement/pos",
            section: "Operate",
            permissions: ["procurement_one:view"],
          },
          {
            title: "Goods receipts",
            href: "/procurement/grns",
            section: "Operate",
            permissions: ["procurement_one:view"],
            procurementPlanFeature: "goods_receipt",
          },
          {
            title: "Inventory",
            href: "/procurement/inventory",
            section: "Operate",
            permissions: ["procurement_one:inventory:view"],
            procurementPlanFeature: "inventory",
          },
          {
            title: "RFQ & sourcing",
            href: "/procurement/rfqs",
            section: "Operate",
            permissions: ["procurement_one:view"],
            procurementPlanFeature: "rfq_sourcing",
          },
          {
            title: "Vendors",
            href: "/procurement/vendors",
            section: "Operate",
            permissions: ["procurement_one:vendors:view"],
          },
        ],
      },
      {
        title: "Finance-One",
        icon: PiggyBank,
        module: "finance_one",
        permissions: [
          "finance_one:view",
          "finance_one:documents:manage",
          "finance_one:budget:manage",
          "finance_one:payments:manage",
        ],
        items: [
          {
            title: "Overview",
            href: financeOneRoutes.home,
            exact: true,
            permissions: ["finance_one:view"],
          },
          {
            title: "Budget & encumbrance",
            href: financeOneRoutes.budget,
            permissions: ["finance_one:view"],
          },
          {
            title: "AP invoices",
            href: financeOneRoutes.apInvoices,
            permissions: ["finance_one:view"],
            procurementPlanFeature: "ap_invoices",
          },
          {
            title: "Payment tracking",
            href: financeOneRoutes.payments,
            permissions: ["finance_one:view"],
            procurementPlanFeature: "payment_tracking",
          },
          {
            title: "Vendor contracts",
            href: financeOneRoutes.contracts,
            permissions: ["finance_one:view"],
            procurementPlanFeature: "vendor_contracts",
          },
          {
            title: "Reports & exports",
            href: financeOneRoutes.reports,
            permissions: ["finance_one:reports:view"],
            procurementPlanFeature: "reporting_exports",
          },
        ],
      },
      {
        title: "E-Approval",
        icon: ClipboardCheck,
        module: "e_approval",
        permissions: [
          "e_approval:view",
          "e_approval:submissions:view",
          "e_approval:submissions:create",
          "e_approval:approve",
          "e_approval:forms:manage",
          "e_approval:settings:manage",
          "e_approval:audit:view",
        ],
        items: [
          { title: "Overview", href: "/e-approval", exact: true, section: "Operate", permissions: ["e_approval:view"] },
          { title: "Forms", href: "/e-approval/forms", section: "Operate", permissions: ["e_approval:forms:manage"] },
          { title: "Submissions", href: "/e-approval/submissions", section: "Operate", permissions: ["e_approval:submissions:view"] },
          { title: "Approvals", href: "/e-approval/approvals?awaiting_me=1", section: "Decide", permissions: ["e_approval:approve"] },
          { title: "Reports", href: "/e-approval/reports", section: "Operate", permissions: ["e_approval:audit:view"] },
        ],
      },
      {
        title: "GIS",
        icon: Map,
        href: "/gis",
        permissions: ["gis:view"],
        module: "gis",
      },
    ],
  },
  {
    group: "Administration",
    items: [
      {
        title: "Team & Access",
        icon: Users,
        module: "team_access",
        permissions: ["user:manage", "role:manage"],
        items: [
          { title: "Users", href: "/users", permissions: ["user:manage"] },
          { title: "Roles & permissions", href: "/users/roles", permissions: ["role:manage"] },
        ],
      },
      {
        title: "Audit trail",
        icon: ScrollText,
        href: "/governance/audit",
        permissions: ["workspace:audit:view"],
        module: "core",
      },
      {
        title: "Billing",
        icon: CreditCard,
        href: "/billing",
        permissions: ["billing:view"],
        module: "billings",
      },
      {
        title: "Settings",
        icon: Settings,
        module: "core",
        permissions: [
          "tenant:manage",
          "e_approval:settings:manage",
          "procurement_one:settings:manage",
          "ticketing:settings:manage",
          "project_one:view",
          "ai_assistant:knowledge:manage",
          // Personal module profile only — not Platform Overview.
          "e_approval:view",
        ],
        permissionsMatch: "any",
        items: [
          {
            title: "Overview",
            href: "/settings",
            exact: true,
            section: "Platform",
            // Admin hub only — hide from normal users who would see an empty page.
            permissions: [
              "tenant:manage",
              "e_approval:settings:manage",
              "procurement_one:settings:manage",
              "ticketing:settings:manage",
              "project_one:view",
              "ai_assistant:knowledge:manage",
            ],
          },
          { title: "Sign-in & security", href: "/admin/settings", section: "Platform", permissions: ["tenant:manage"] },
          { title: "KPI & SLA", href: "/admin/settings/kpi", section: "Platform", permissions: ["tenant:manage"] },
          {
            title: "Assistant knowledge",
            href: "/settings/ai-assistant/knowledge",
            section: "AI Assistant",
            module: "ai_assistant",
            permissions: ["ai_assistant:knowledge:manage"],
          },
          {
            title: "My E-Approval profile",
            href: "/e-approval/profile",
            section: "E-Approval",
            module: "e_approval",
            permissions: ["e_approval:view"],
          },
          {
            title: "Module policies",
            href: "/e-approval/settings",
            section: "E-Approval",
            module: "e_approval",
            permissions: ["e_approval:settings:manage"],
          },
          {
            title: "Approval policies",
            href: "/e-approval/approval-policies",
            section: "E-Approval",
            module: "e_approval",
            permissions: ["e_approval:settings:manage"],
          },
          {
            title: "Master data",
            href: "/e-approval/master-data",
            section: "E-Approval",
            module: "e_approval",
            permissions: ["e_approval:settings:manage"],
          },
          {
            title: "Procurement settings",
            href: "/procurement/settings",
            section: "Procurement-One",
            module: "procurement_one",
            permissions: ["procurement_one:settings:manage"],
          },
          {
            title: "Ticketing settings",
            href: "/ticketing/settings",
            section: "Ticketing",
            module: "ticketing",
            permissions: ["ticketing:settings:manage"],
          },
          {
            title: "Rollout playbook",
            href: "/project-one/rollout-playbook",
            section: "Project-One",
            module: "project_one",
            permissions: ["project_one:view"],
          },
          {
            title: "Public holidays",
            href: "/project-one/public-holidays",
            section: "Project-One",
            module: "project_one",
            permissions: ["project_one:view"],
          },
          {
            title: "Geography lookups",
            href: "/project-one/geography",
            section: "Project-One",
            module: "project_one",
            permissions: ["project_one:view"],
          },
        ],
      },
    ],
  },
];

/** High-intent shortcuts surfaced in the command palette "Do" group. */
export const workspaceQuickActions: WorkspaceQuickAction[] = [
  {
    id: "documents-expiring",
    title: "Documents expiring soon",
    description: "Review leases, permits, and contracts across sites",
    href: "/documents",
    icon: FileText,
    module: "documents",
    permissions: ["documents:view"],
    keywords: ["binder", "lease", "expiry", "contract", "document"],
  },
  {
    id: "ea-new-request",
    title: "New E-Approval request",
    description: "Choose a published form and start a submission",
    href: "/e-approval/submissions/new",
    icon: PlusCircle,
    module: "e_approval",
    permissions: ["e_approval:submissions:create"],
    keywords: ["submit", "form", "request", "approval"],
  },
  {
    id: "ea-my-approvals",
    title: "My E-Approval inbox",
    description: "Open submissions awaiting your decision",
    href: "/e-approval/approvals?awaiting_me=1",
    icon: ClipboardCheck,
    module: "e_approval",
    permissions: ["e_approval:approve"],
    keywords: ["pending", "decide", "approve"],
  },
  {
    id: "ticketing-new",
    title: "Create ticket",
    description: "Log an operational or support issue",
    href: "/ticketing/tickets/new",
    icon: PlusCircle,
    module: "ticketing",
    permissions: ["ticketing:tickets:create"],
    keywords: ["support", "issue", "helpdesk"],
  },
  {
    id: "project-one-gates",
    title: "My gate approvals",
    description: "Review rollout gates assigned to you",
    href: "/project-one/gate-approvals?awaiting_me=1",
    icon: Zap,
    module: "project_one",
    permissions: ["project_one:rollout:view"],
    keywords: ["rollout", "gate", "project"],
  },
];
