import {
  Activity,
  Building2,
  CircleHelp,
  ClipboardCheck,
  CreditCard,
  FileScan,
  FileText,
  Landmark,
  LayoutDashboard,
  LifeBuoy,
  Map,
  MapPin,
  Package,
  PiggyBank,
  PlusCircle,
  ScrollText,
  Settings,
  Shapes,
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
  permissionsMatch?: "all" | "any";
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
        title: "Sites",
        icon: Waypoints,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "Executive Dashboard",
            href: "/dynamic-entities/executive-dashboard",
            exact: true,
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "CAS Executive Dashboard",
            href: "/dynamic-entities/reports/cas-executive-dashboard",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Tower Sites",
            href: "/dynamic-entities/tower_sites",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Construction Projects",
            href: "/dynamic-entities/construction_projects",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Construction Activities",
            href: "/dynamic-entities/construction_activities",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Postcon Trackers",
            href: "/dynamic-entities/postcon_trackers",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "SAQ Trackers",
            href: "/dynamic-entities/saq_trackers",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Power Trackers",
            href: "/dynamic-entities/power_trackers",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Site Permits",
            href: "/dynamic-entities/site_permits",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Site Documents",
            href: "/dynamic-entities/site_documents",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Land Leases",
            href: "/dynamic-entities/land_leases",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Telco Contracts",
            href: "/dynamic-entities/telco_contracts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Colocations",
            href: "/dynamic-entities/site_colocations",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Electric Utilities",
            href: "/dynamic-entities/electric_utilities",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "All entities",
            href: "/dynamic-entities",
            exact: true,
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Tower Operations",
        icon: Activity,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "LIVE Build & Forecasts",
            href: "/dynamic-entities/reports/live-tower-build-forecasts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Site Portfolio Status",
            href: "/dynamic-entities/reports/site-portfolio-status",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "RFTI Pipeline & SLA",
            href: "/dynamic-entities/reports/rfti-pipeline",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "SAQ Milestone Aging",
            href: "/dynamic-entities/reports/saq-milestone-aging",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Permit Status & Compliance",
            href: "/dynamic-entities/reports/permit-status-compliance",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Energization & Power",
            href: "/dynamic-entities/reports/energization-power-status",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Colocation & Tenancy",
            href: "/dynamic-entities/reports/colocation-tenancy",
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Cost & Capital",
        icon: PiggyBank,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "Materials Issued per Tower",
            href: "/dynamic-entities/reports/materials-issued",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Cost vs Budget per Tower",
            href: "/dynamic-entities/reports/cost-vs-budget",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Capex & Opex per Site",
            href: "/dynamic-entities/reports/capex-opex",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "CapEx Budgets",
            href: "/dynamic-entities/capex_budgets",
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Procurement",
        icon: Package,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "Purchase Monitoring",
            href: "/dynamic-entities/reports/purchase-monitoring",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Daily Sales",
            href: "/dynamic-entities/reports/daily-sales",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Stock on Hand",
            href: "/dynamic-entities/reports/stock-on-hand",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Bank Reconciliation",
            href: "/dynamic-entities/reports/bank-reconciliation",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Purchase Transactions",
            href: "/dynamic-entities/purchase_transactions",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Procurement Requests",
            href: "/dynamic-entities/procurement_requests",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Materials & Equipment",
            href: "/dynamic-entities/products",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Warehouses",
            href: "/dynamic-entities/warehouses",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Stock Movements",
            href: "/dynamic-entities/stock_movements",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Suppliers",
            href: "/dynamic-entities/suppliers",
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Finance",
        icon: Landmark,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "CAS Executive Dashboard",
            href: "/dynamic-entities/reports/cas-executive-dashboard",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "AR & AP Aging",
            href: "/dynamic-entities/reports/ar-ap-aging",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Petty Cash",
            href: "/dynamic-entities/reports/petty-cash",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Chart of Accounts",
            href: "/dynamic-entities/chart_of_accounts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Fiscal Periods",
            href: "/dynamic-entities/fiscal_periods",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "General Ledger",
            href: "/dynamic-entities/general_ledger",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Bank Accounts",
            href: "/dynamic-entities/bank_accounts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Bank Transactions",
            href: "/dynamic-entities/bank_transactions",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Bank Reconciliations",
            href: "/dynamic-entities/bank_reconciliations",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Payments & Receipts",
            href: "/dynamic-entities/payments_and_receipts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Fixed Assets",
            href: "/dynamic-entities/fixed_assets",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Customers",
            href: "/dynamic-entities/customers",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Sales Transactions",
            href: "/dynamic-entities/sales_transactions",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Site Operating Costs",
            href: "/dynamic-entities/site_operating_costs",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "BIR 2307 Certificates",
            href: "/dynamic-entities/bir_form_2307_certificates",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Trial Balance",
            href: "/dynamic-entities/reports/trial-balance",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Income Statement",
            href: "/dynamic-entities/reports/income-statement",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Balance Sheet",
            href: "/dynamic-entities/reports/balance-sheet",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Cash Flow",
            href: "/dynamic-entities/reports/cash-flow",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Monthly Management Accounts",
            href: "/dynamic-entities/reports/monthly-management-accounts",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "BIR Compliance",
            href: "/dynamic-entities/reports/bir-compliance",
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Site Ticketing",
        icon: LifeBuoy,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          {
            title: "Ticketing Board",
            href: "/dynamic-entities/ticketing-board",
            exact: true,
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Site Tickets",
            href: "/dynamic-entities/site_tickets",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Categories",
            href: "/dynamic-entities/ticket_categories",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Activities",
            href: "/dynamic-entities/ticket_activities",
            permissions: ["dynamic_entities:view"],
          },
        ],
      },
      {
        title: "Reference",
        icon: Building2,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:view"],
        items: [
          { title: "Vendors", href: "/dynamic-entities/vendors", permissions: ["dynamic_entities:view"] },
          {
            title: "Territories",
            href: "/dynamic-entities/territories",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Project Teams",
            href: "/dynamic-entities/project_teams",
            permissions: ["dynamic_entities:view"],
          },
          {
            title: "Milestone Catalogue",
            href: "/dynamic-entities/milestone_catalogue",
            permissions: ["dynamic_entities:view"],
          },
          { title: "Branches", href: "/dynamic-entities/branches", permissions: ["dynamic_entities:view"] },
          { title: "Lessors", href: "/dynamic-entities/lessors", permissions: ["dynamic_entities:view"] },
        ],
      },
      {
        title: "System Core",
        icon: Shapes,
        module: "dynamic_entities",
        permissions: ["dynamic_entities:fields:manage", "printables:manage", "sidebar:manage", "api_keys:manage", "system:manage", "html_reports:manage", "workflows:manage", "email_templates:manage", "automation:manage", "search_index:manage", "ai_assistant:prompts:manage"],
        permissionsMatch: "any",
        items: [
          {
            title: "Manage System",
            href: "/admin/system",
            permissions: ["system:manage"],
          },
          {
            title: "Search Index",
            href: "/admin/search-index",
            permissions: ["search_index:manage"],
          },
          {
            title: "Manage Fields",
            href: "/dynamic-entities/fields",
            permissions: ["dynamic_entities:fields:manage"],
          },
          {
            title: "Relation Visual",
            href: "/dynamic-entities/relationships",
            permissions: ["dynamic_entities:fields:manage"],
            module: "dynamic_entities",
          },
          {
            title: "Field Groups",
            href: "/dynamic-entities/field-groups",
            permissions: ["dynamic_entities:fields:manage"],
          },
          {
            title: "Manage Printables",
            href: "/dynamic-entities/printables",
            permissions: ["printables:manage"],
          },
          {
            title: "Manage HTML Reports",
            href: "/dynamic-entities/html-reports",
            permissions: ["html_reports:manage"],
          },
          {
            title: "Report Builder",
            href: "/dynamic-entities/report-builder",
            permissions: ["html_reports:manage"],
          },
          {
            title: "Email Templates",
            href: "/dynamic-entities/email-templates",
            permissions: ["email_templates:manage"],
          },
          {
            title: "Manage Cron Jobs",
            href: "/admin/automation",
            permissions: ["automation:manage"],
          },
          {
            title: "Manage Workflows",
            href: "/dynamic-entities/workflows",
            permissions: ["workflows:manage"],
          },
          {
            title: "Manage AI Prompts",
            href: "/admin/ai-prompts",
            permissions: ["ai_assistant:prompts:manage"],
          },
          {
            title: "Manage Sidebar",
            href: "/admin/sidebar",
            permissions: ["sidebar:manage"],
          },
          {
            title: "Manage REST API",
            href: "/admin/api-keys",
            permissions: ["api_keys:manage"],
          },
        ],
      },
      {
        title: "DocExtract",
        icon: FileScan,
        module: "doc_extract",
        permissions: ["doc-extract:view", "doc-extract:run", "doc-extract:templates:manage"],
        items: [
          {
            title: "Batches",
            href: "/doc-extract",
            exact: true,
            section: "Operate",
            permissions: ["doc-extract:view"],
          },
          {
            title: "New extraction",
            href: "/doc-extract/new",
            section: "Operate",
            permissions: ["doc-extract:run"],
          },
          {
            title: "Templates",
            href: "/doc-extract/templates",
            section: "Setup",
            permissions: ["doc-extract:templates:manage"],
          },
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
        title: "E-Forms",
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
          { title: "Reports", href: "/e-approval/reports", section: "Operate", permissions: ["e_approval:audit:view", "e_approval:submissions:view"], permissionsMatch: "any" },
        ],
      },
      {
        title: "GIS",
        icon: Map,
        href: "/gis",
        permissions: ["gis:view"],
        module: "gis",
      },
      {
        title: "Help",
        icon: CircleHelp,
        href: "/help",
        permissions: ["e_approval:view"],
        permissionsMatch: "any",
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
        permissions: ["user:manage", "role:manage", "organization:view", "organization:manage"],
        permissionsMatch: "any",
        items: [
          { title: "Users", href: "/users", permissions: ["user:manage"] },
          {
            title: "Organization",
            href: "/users/org",
            permissions: ["organization:view", "organization:manage", "user:manage"],
            permissionsMatch: "any",
          },
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
          // Personal module profile / exports — not Platform Overview.
          "e_approval:view",
          "doc-extract:view",
          "ticketing:view",
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
          { title: "Backups", href: "/admin/backups", section: "Platform", permissions: ["tenant:manage"] },
          { title: "KPI & SLA", href: "/admin/settings/kpi", section: "Platform", permissions: ["tenant:manage"] },
          {
            title: "Assistant knowledge",
            href: "/settings/ai-assistant/knowledge",
            section: "AI Assistant",
            module: "ai_assistant",
            permissions: ["ai_assistant:knowledge:manage"],
          },
          {
            title: "My E-Forms profile",
            href: "/e-approval/profile",
            section: "E-Forms",
            module: "e_approval",
            permissions: ["e_approval:view"],
          },
          {
            title: "My exports",
            href: "/exports",
            section: "Workspace",
            permissionsMatch: "any",
            permissions: ["doc-extract:view", "ticketing:view", "e_approval:view"],
          },
          {
            title: "Module policies",
            href: "/e-approval/settings",
            section: "E-Forms",
            module: "e_approval",
            permissions: ["e_approval:settings:manage"],
          },
          {
            title: "Approval policies",
            href: "/e-approval/approval-policies",
            section: "E-Forms",
            module: "e_approval",
            permissions: ["e_approval:settings:manage"],
          },
          {
            title: "Master data",
            href: "/e-approval/master-data",
            section: "E-Forms",
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
    title: "New E-Forms request",
    description: "Choose a published form and start a submission",
    href: "/e-approval/submissions/new",
    icon: PlusCircle,
    module: "e_approval",
    permissions: ["e_approval:submissions:create"],
    keywords: ["submit", "form", "request", "approval"],
  },
  {
    id: "ea-my-approvals",
    title: "My E-Forms inbox",
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
