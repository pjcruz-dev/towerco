import { chartColorAt, chartFillForKey } from "@/components/dashboard/dashboard-chart-utils";
import type { DashboardNormalizedData } from "@/lib/ui/dashboard-widget-data";
import { withTicketingKpiHrefs } from "@/lib/ticketing/kpi-deep-links";
import type { EApprovalDashboardResponse } from "@/modules/e-approval/types";
import type { EApprovalFormWorkspaceDashboard } from "@/modules/e-approval/form-workspace-types";
import type { DocExtractBatchListRow } from "@/modules/doc-extract/types";
import type { TicketingDashboardResponse } from "@/modules/ticketing/types";
import type { ProjectOneKpi } from "@/modules/project-one/types";

function asKpis(
  rows: Array<{
    key: string;
    label: string;
    value: string | number;
    change?: string | null;
    tone?: string | null;
    href?: string | null;
  }>,
): ProjectOneKpi[] {
  return rows.map((row) => ({
    key: row.key,
    label: row.label,
    value: String(row.value),
    change: row.change ?? undefined,
    tone:
      row.tone === "success" || row.tone === "warning" || row.tone === "danger" || row.tone === "neutral"
        ? row.tone
        : "neutral",
    href: row.href?.trim() || undefined,
  }));
}

function breakdownToSeries(
  rows: Array<{ key?: string; status?: string; label: string; count?: number; value?: number }> | undefined,
): DashboardNormalizedData["series"]["status"] {
  if (!rows?.length) return [];
  return rows.map((row, index) => {
    const key = row.key ?? row.status ?? row.label;
    const value = row.count ?? row.value ?? 0;
    return {
      key,
      label: row.label,
      value,
      fill: chartFillForKey(key, index),
    };
  });
}

export function normalizeTicketingDashboard(
  data: TicketingDashboardResponse | undefined,
): DashboardNormalizedData {
  const kpis = withTicketingKpiHrefs(asKpis(data?.kpis ?? []));
  const queue = kpis
    .filter((kpi) => ["open", "assigned_me", "urgent", "sla_at_risk", "resolved_week"].includes(kpi.key))
    .map((kpi, index) => ({
      key: kpi.key,
      label: kpi.label,
      value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
      fill: chartFillForKey(kpi.key, index),
    }));

  const priority =
    breakdownToSeries(data?.priority_breakdown) ??
    [];

  const peopleMap = new Map<string, { id: string; name: string; value: number }>();
  for (const ticket of data?.recent_tickets ?? []) {
    const assignee = ticket.assignee;
    if (!assignee?.id) continue;
    const current = peopleMap.get(assignee.id) ?? { id: assignee.id, name: assignee.name, value: 0 };
    current.value += 1;
    peopleMap.set(assignee.id, current);
  }

  const activity = (data?.recent_tickets ?? []).slice(0, 8).map((ticket) => ({
    id: ticket.id,
    title: ticket.title,
    subtitle: `${ticket.ticket_number} · ${ticket.status.replace(/_/g, " ")}`,
    at: ticket.updated_at ?? ticket.created_at,
    href: `/ticketing/tickets/${ticket.id}`,
  }));

  return {
    kpis,
    series: {
      queue,
      status: breakdownToSeries(data?.status_breakdown),
      priority: priority.length
        ? priority
        : (() => {
            const counts = new Map<string, number>();
            for (const ticket of data?.recent_tickets ?? []) {
              counts.set(ticket.priority, (counts.get(ticket.priority) ?? 0) + 1);
            }
            return [...counts.entries()].map(([key, value], index) => ({
              key,
              label: key.replace(/_/g, " "),
              value,
              fill: chartColorAt(index),
            }));
          })(),
      department: breakdownToSeries(data?.department_breakdown),
      category: (data?.by_category ?? []).map((row, index) => ({
        key: row.category ?? row.label,
        label: row.label,
        value: row.open + row.in_progress,
        fill: chartColorAt(index),
      })),
    },
    activity,
    people: [...peopleMap.values()].sort((a, b) => b.value - a.value).slice(0, 8),
    shortcuts: [
      { href: "/ticketing/tickets", label: "All tickets", description: "Open the full queue" },
      { href: "/ticketing/tickets/new", label: "New ticket", description: "Report an issue" },
      { href: "/exports", label: "My exports", description: "Download history" },
    ],
    attention: [
      ...(() => {
        const sla = kpis.find((kpi) => kpi.key === "sla_at_risk");
        const urgent = kpis.find((kpi) => kpi.key === "urgent");
        const items: import("@/lib/ui/dashboard-widget-data").DashboardAttentionItem[] = [];
        const slaN = typeof sla?.value === "number" ? sla.value : Number(sla?.value) || 0;
        const urgentN = typeof urgent?.value === "number" ? urgent.value : Number(urgent?.value) || 0;
        if (slaN > 0) {
          items.push({
            id: "tk-sla",
            title: `${slaN} at SLA risk`,
            href: "/ticketing/tickets?sla_status=at_risk",
            tone: "warning",
          });
        }
        if (urgentN > 0) {
          items.push({
            id: "tk-urgent",
            title: `${urgentN} urgent open`,
            href: "/ticketing/tickets?priority=urgent",
            tone: "danger",
          });
        }
        return items;
      })(),
    ],
    exportsTeaser: {
      href: "/exports",
      label: "My exports",
      message: "Ticket list downloads and async jobs",
    },
    hero: {
      title: "Ticketing",
      description: data?.message ?? "Cross-module issue tracking for INFRA SUITE.",
      ctaHref: "/ticketing/tickets/new",
      ctaLabel: "New ticket",
    },
  };
}

export function normalizeEApprovalDashboard(
  data: EApprovalDashboardResponse | undefined,
): DashboardNormalizedData {
  const kpis = asKpis(data?.kpis ?? []);
  const finance = asKpis(data?.finance_kpis ?? []);
  const awaiting = data?.queues?.awaiting_approval ?? [];
  const attention = data?.queues?.my_attention ?? [];

  const activity = [...awaiting, ...attention].slice(0, 10).map((item) => ({
    id: item.id,
    title: item.document_no ?? item.form_name ?? "Submission",
    subtitle: [item.form_name, item.status, item.requestor_name].filter(Boolean).join(" · "),
    at: item.waiting_since,
    href: item.href,
  }));

  return {
    kpis,
    secondaryKpis: finance,
    series: {
      queue: [
        {
          key: "awaiting",
          label: "Awaiting approval",
          value: awaiting.length,
          fill: chartFillForKey("awaiting_my_approval", 0),
        },
        {
          key: "attention",
          label: "Needs attention",
          value: attention.length,
          fill: chartFillForKey("returned", 1),
        },
        ...kpis.slice(0, 4).map((kpi, index) => ({
          key: kpi.key,
          label: kpi.label,
          value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
          fill: chartFillForKey(kpi.key, index),
        })),
      ],
      status: kpis.map((kpi, index) => ({
        key: kpi.key,
        label: kpi.label,
        value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
        fill: chartFillForKey(kpi.key, index),
      })),
    },
    activity,
    audit: (data?.recent_audit ?? []).slice(0, 10).map((row) => ({
      id: row.id,
      title: row.action,
      subtitle: row.user_name ?? row.target_id ?? "",
      at: row.created_at,
    })),
    shortcuts: [
      { href: "/e-approval/submissions", label: "Submissions", description: "Track your requests" },
      { href: "/e-approval/approvals?awaiting_me=1", label: "Approvals", description: "Decide on pending items" },
      { href: "/e-approval/forms", label: "Forms", description: "Templates and workflows" },
      { href: "/e-approval/reports", label: "Reports", description: "Analytics and exports" },
    ],
    attention: [
      ...(awaiting.length
        ? [
            {
              id: "ea-awaiting",
              title: `${awaiting.length} awaiting your approval`,
              href: "/e-approval/approvals?awaiting_me=1",
              tone: "warning" as const,
            },
          ]
        : []),
      ...(attention.length
        ? [
            {
              id: "ea-attention",
              title: `${attention.length} need attention`,
              href: "/e-approval/submissions?status=returned",
              tone: "danger" as const,
            },
          ]
        : []),
    ],
    exportsTeaser: {
      href: "/exports",
      label: "My exports",
      message: "List exports and E-Forms downloads",
    },
    hero: {
      title: "E-Forms",
      description: data?.message ?? "Your inbox for approvals, returns, and open requests.",
      ctaHref: "/e-approval/submissions/new",
      ctaLabel: "New submission",
    },
  };
}

export function normalizeEApprovalWorkspace(
  data: EApprovalFormWorkspaceDashboard | undefined,
): DashboardNormalizedData {
  const kpis = asKpis(data?.kpis ?? []);
  return {
    kpis,
    series: {
      status: (data?.status_breakdown ?? []).map((row, index) => ({
        key: row.status ?? row.label,
        label: row.label,
        value: row.count,
        fill: chartFillForKey(row.status ?? row.label, index),
      })),
      subsidiary: (data?.subsidiary_breakdown ?? []).map((row, index) => ({
        key: row.key ?? row.label,
        label: row.label,
        value: row.count,
        fill: chartColorAt(index),
      })),
    },
    activity: (data?.recent_activity ?? []).map((row) => ({
      id: row.id,
      title: row.document_no,
      subtitle: [row.form_name, row.status, row.requestor_name].filter(Boolean).join(" · "),
      at: row.created_at,
    })),
    audit: (data?.recent_audit ?? []).map((row) => ({
      id: row.id,
      title: row.action,
      subtitle: [row.user_name, row.remarks].filter(Boolean).join(" · "),
      at: row.created_at,
    })),
    shortcuts: [
      ...(data?.viewer?.can_submit && data.viewer.new_request_href
        ? [
            {
              href: data.viewer.new_request_href,
              label: "New request",
              description: "Submit on this form",
            },
          ]
        : []),
      { href: "/e-approval", label: "Overview", description: "All forms inbox" },
      { href: "/e-approval/reports", label: "Reports", description: "Analytics and exports" },
      { href: "/exports", label: "My exports", description: "Download history" },
    ],
    exportsTeaser: {
      href: "/exports",
      label: "My exports",
      message: "Workspace exports appear under My exports",
    },
    hero: data
      ? {
          title: data.workspace?.title ?? data.form?.name ?? "Form workspace",
          description: data.workspace?.description ?? data.form?.description ?? undefined,
          ctaHref: data.viewer?.new_request_href,
          ctaLabel: data.viewer?.can_submit ? "New request" : undefined,
        }
      : undefined,
  };
}

export function normalizeDocExtractBatches(rows: DocExtractBatchListRow[]): DashboardNormalizedData {
  let processing = 0;
  let ready = 0;
  let failed = 0;
  for (const row of rows) {
    if (row.status === "processing" || row.status === "pending") processing += 1;
    else if (row.status === "ready") ready += 1;
    else if (row.status === "failed") failed += 1;
  }
  const kpis: ProjectOneKpi[] = [
    { key: "total", label: "Batches", value: rows.length, tone: "neutral" },
    { key: "processing", label: "Scanning", value: processing, tone: processing ? "warning" : "neutral" },
    { key: "ready", label: "Ready", value: ready, tone: "success" },
    { key: "failed", label: "Failed", value: failed, tone: failed ? "danger" : "neutral" },
  ];
  const status = [
    { key: "processing", label: "Scanning", value: processing, fill: chartFillForKey("processing", 0) },
    { key: "ready", label: "Ready", value: ready, fill: chartFillForKey("ready", 1) },
    { key: "failed", label: "Failed", value: failed, fill: chartFillForKey("failed", 2) },
  ].filter((row) => row.value > 0);

  const modeCounts = new Map<string, number>();
  for (const row of rows) {
    const mode = row.mode?.trim() || "auto";
    modeCounts.set(mode, (modeCounts.get(mode) ?? 0) + 1);
  }

  return {
    kpis,
    series: {
      status,
      queue: status,
      category: [...modeCounts.entries()].map(([key, value], index) => ({
        key,
        label: key,
        value,
        fill: chartColorAt(index),
      })),
    },
    activity: rows.slice(0, 8).map((row) => ({
      id: row.id,
      title: row.template_name ?? "Auto-detect batch",
      subtitle: `${row.status} · ${row.document_count} docs`,
      at: row.updated_at ?? row.created_at,
      href: `/doc-extract/batches/${row.id}`,
    })),
    shortcuts: [
      { href: "/doc-extract/new", label: "New extraction", description: "Upload PDFs or images" },
      { href: "/doc-extract/templates", label: "Templates", description: "Manage extract templates" },
      { href: "/exports", label: "My exports", description: "Download history" },
    ],
    attention: [
      ...(processing
        ? [
            {
              id: "dx-processing",
              title: `${processing} extraction${processing === 1 ? "" : "s"} scanning`,
              subtitle: "Auto-refreshes while processing",
              href: "/doc-extract?status=processing",
              tone: "neutral" as const,
            },
          ]
        : []),
      ...(failed
        ? [
            {
              id: "dx-failed",
              title: `${failed} extraction${failed === 1 ? "" : "s"} failed`,
              subtitle: "Review and retry from the batch",
              href: "/doc-extract?status=failed",
              tone: "danger" as const,
            },
          ]
        : []),
    ],
    exportsTeaser: {
      href: "/exports",
      label: "My exports",
      message: "Batch and list exports appear under My exports",
    },
    hero: {
      title: "DocExtract",
      description: "Upload → consolidate → extract → export.",
      ctaHref: "/doc-extract/new",
      ctaLabel: "New extraction",
    },
  };
}

/** E-Forms Reports analytics → kind-widget data bag (Layout & options data sources). */
export function normalizeEApprovalAnalytics(
  data: import("@/lib/api/modules/e-approval-api").EApprovalAnalyticsResponse | undefined,
): DashboardNormalizedData {
  if (!data) {
    return { kpis: [], series: {} };
  }

  const kpis = asKpis(data.kpis ?? []);

  return {
    kpis,
    series: {
      // Trend (line) — dates as labels
      queue: (data.submissions_over_time ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.value,
        fill: chartColorAt(index),
      })),
      status: (data.by_status ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.value,
        fill: chartFillForKey(row.key, index),
      })),
      category: (data.top_forms ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.value,
        fill: chartColorAt(index),
      })),
      department: (data.aging ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.value,
        fill: chartFillForKey(row.key, index),
      })),
      subsidiary: (data.bottlenecks ?? []).map((row, index) => ({
        key: row.key,
        label: row.label,
        value: row.value,
        fill: chartColorAt(index),
      })),
      priority: kpis.map((kpi, index) => ({
        key: kpi.key,
        label: kpi.label,
        value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
        fill: chartFillForKey(kpi.key, index),
      })),
    },
    people: (data.approver_load ?? []).map((row) => ({
      id: row.key,
      name: row.label,
      value: row.value,
    })),
    activity: (data.rejection_reasons ?? []).map((row) => ({
      id: row.key,
      title: row.label,
      subtitle: `${row.value} rejection${row.value === 1 ? "" : "s"}`,
    })),
    audit: (data.cycle_times ?? []).map((row) => ({
      id: row.key,
      title: row.label,
      subtitle: `${row.value} ${row.unit}`,
    })),
  };
}

export function normalizeWorkspaceDashboard(
  data: import("@/modules/workspace/types").WorkspaceDashboardResponse | undefined,
): DashboardNormalizedData {
  const kpis = asKpis(data?.kpis ?? []);
  const actionSeries = (data?.actions ?? [])
    .filter((action) => action.count > 0)
    .map((action, index) => ({
      key: action.id,
      label: action.label,
      value: action.count,
      fill: chartColorAt(index),
    }));
  const attentionKeys = [
    "unread_notifications",
    "ea_awaiting_my_approval",
    "ea_stale_approvals",
    "rollout_gates_awaiting_me",
    "ticketing_assigned_me",
    "rollout_sla_risk",
  ];
  const attentionSeries = kpis
    .filter((kpi) => attentionKeys.includes(kpi.key))
    .map((kpi, index) => ({
      key: kpi.key,
      label: kpi.label,
      value: typeof kpi.value === "number" ? kpi.value : Number(kpi.value) || 0,
      fill: chartFillForKey(kpi.key, index),
    }))
    .filter((row) => row.value > 0);

  const awaitingTotal = data?.awaiting_me?.total ?? 0;
  const attention: import("@/lib/ui/dashboard-widget-data").DashboardAttentionItem[] = [];
  if (awaitingTotal > 0) {
    attention.push({
      id: "ws-awaiting",
      title: `${awaitingTotal} item${awaitingTotal === 1 ? "" : "s"} awaiting you`,
      subtitle: "Approvals, gates, and tickets across modules",
      href: "/dashboard",
      tone: "warning",
    });
  }

  return {
    kpis,
    series: {
      queue: actionSeries,
      status: attentionSeries,
    },
    activity: (data?.recent_activity ?? []).slice(0, 10).map((item) => ({
      id: item.id,
      title: item.label,
      subtitle: [item.module, item.detail].filter(Boolean).join(" · "),
      at: item.created_at,
      href: item.href ?? undefined,
    })),
    shortcuts: (data?.quick_links ?? []).map((link) => ({
      href: link.href,
      label: link.label,
    })),
    attention,
    exportsTeaser: {
      href: "/exports",
      label: "My exports",
      message: "Download history for lists and reports",
    },
  };
}

