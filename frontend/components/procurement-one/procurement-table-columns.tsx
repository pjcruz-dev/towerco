"use client";

import Link from "next/link";
import { Building2, Download } from "lucide-react";
import type { ColumnDef } from "@tanstack/react-table";

import { ProcurementApInvoiceStatusBadge } from "@/components/procurement-one/procurement-ap-invoice-status-badge";
import { ProcurementGrnStatusBadge } from "@/components/procurement-one/procurement-grn-status-badge";
import { ProcurementPaymentStatusBadge } from "@/components/procurement-one/procurement-payment-status-badge";
import { ProcurementPoStatusBadge } from "@/components/procurement-one/procurement-po-status-badge";
import { ProcurementPrStatusBadge } from "@/components/procurement-one/procurement-pr-status-badge";
import { DataTableColumnHeader } from "@/components/ui/data-table-column-header";
import { RowActionsMenu } from "@/components/ui/row-actions-menu";
import { usePermission } from "@/hooks/use-permission";
import { procurementPaymentBatchExportUrl } from "@/lib/api/modules/procurement-one-api";
import { permissions } from "@/lib/rbac/permissions";
import type {
  ProcurementApInvoiceListRow,
  ProcurementContractListRow,
  ProcurementGrnListRow,
  ProcurementPaymentBatchListRow,
  ProcurementPaymentRequestListRow,
  ProcurementPoListRow,
  ProcurementPrListRow,
  ProcurementRfqListRow,
  ProcurementVendorListRow,
} from "@/modules/procurement-one/types";

type FinanceModulePaths = {
  contract: (id: string) => string;
  apInvoice: (id: string) => string;
  payment: (id: string) => string;
};

function formatMoney(value: number, currency = "PHP"): string {
  return `${currency} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatMoneyNullable(value: number | null, currency = "PHP"): string {
  if (value === null) return "—";
  return formatMoney(value, currency);
}

export const procurementPrTableColumns: ColumnDef<ProcurementPrListRow>[] = [
  {
    id: "document",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Document" />,
    cell: ({ row }) => (
      <Link href={`/procurement/prs/${row.original.id}`} className="font-medium text-primary hover:underline">
        {row.original.document_no ?? "Draft"}
      </Link>
    ),
  },
  {
    accessorKey: "title",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
  },
  {
    accessorKey: "status",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <ProcurementPrStatusBadge status={row.original.status} label={row.original.status_label} />,
  },
  {
    id: "estimated",
    header: () => <span className="block w-full text-right">Estimated</span>,
    cell: ({ row }) => (
      <span className="block text-right tabular-nums">{formatMoney(row.original.estimated_total, row.original.currency)}</span>
    ),
  },
  {
    id: "requestor",
    header: "Requestor",
    cell: ({ row }) => <span className="text-muted-foreground">{row.original.requestor?.name ?? "—"}</span>,
  },
];

export const procurementVendorTableColumns: ColumnDef<ProcurementVendorListRow>[] = [
  {
    id: "vendor",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Vendor" />,
    cell: ({ row }) => (
      <Link
        href={`/procurement/vendors/${row.original.id}`}
        className="inline-flex items-center gap-2 font-medium text-primary hover:underline"
      >
        <Building2 className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden />
        {row.original.company_name}
      </Link>
    ),
  },
  {
    accessorKey: "vendor_code",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Code" />,
  },
  {
    accessorKey: "category",
    header: "Category",
    cell: ({ row }) => row.original.category ?? "—",
  },
  {
    id: "accreditation",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Accreditation" />,
    cell: ({ row }) => (
      <span className="capitalize">{row.original.accreditation_status_label ?? row.original.accreditation_status}</span>
    ),
  },
  {
    id: "status",
    header: "Status",
    cell: ({ row }) => (row.original.is_active ? "Active" : "Inactive"),
  },
];

export const procurementPoTableColumns: ColumnDef<ProcurementPoListRow>[] = [
  {
    id: "document",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Document" />,
    cell: ({ row }) => (
      <Link href={`/procurement/pos/${row.original.id}`} className="font-medium text-primary hover:underline">
        {row.original.document_no ?? "Draft"}
      </Link>
    ),
  },
  {
    id: "vendor",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Vendor" />,
    cell: ({ row }) => (
      <span className="text-muted-foreground">{row.original.vendor_name ?? row.original.vendor_code ?? "—"}</span>
    ),
  },
  {
    accessorKey: "status",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <ProcurementPoStatusBadge status={row.original.status} label={row.original.status_label} />,
  },
  {
    id: "grand_total",
    header: () => <span className="block w-full text-right">Grand total</span>,
    cell: ({ row }) => (
      <span className="block text-right tabular-nums">{formatMoney(row.original.grand_total, row.original.currency_code)}</span>
    ),
  },
  {
    id: "requestor",
    header: "Requestor",
    cell: ({ row }) => <span className="text-muted-foreground">{row.original.requestor?.name ?? "—"}</span>,
  },
];

export const procurementRfqTableColumns: ColumnDef<ProcurementRfqListRow>[] = [
  {
    id: "rfq",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="RFQ" />,
    cell: ({ row }) => (
      <Link href={`/procurement/rfqs/${row.original.id}`} className="font-medium text-primary hover:underline">
        {row.original.document_no ?? "Draft"}
      </Link>
    ),
  },
  {
    accessorKey: "title",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
  },
  {
    id: "pr",
    header: "PR",
    cell: ({ row }) =>
      row.original.pr_id ? (
        <Link href={`/procurement/prs/${row.original.pr_id}`} className="text-primary hover:underline">
          {row.original.pr_document_no ?? "PR"}
        </Link>
      ) : (
        "—"
      ),
  },
  { accessorKey: "bid_count", header: "Bids" },
  {
    id: "awarded_vendor",
    header: "Awarded vendor",
    cell: ({ row }) => <span className="text-muted-foreground">{row.original.awarded_vendor_name ?? "—"}</span>,
  },
  {
    id: "closes",
    header: "Closes",
    cell: ({ row }) => (
      <span className="text-muted-foreground">
        {row.original.bidding_closes_at ? new Date(row.original.bidding_closes_at).toLocaleDateString() : "—"}
      </span>
    ),
  },
  {
    accessorKey: "status",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <ProcurementPaymentStatusBadge status={row.original.status} label={row.original.status_label} />,
  },
];

export const procurementGrnTableColumns: ColumnDef<ProcurementGrnListRow>[] = [
  {
    id: "grn",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="GRN" />,
    cell: ({ row }) => (
      <Link href={`/procurement/grns/${row.original.id}`} className="font-medium text-primary hover:underline">
        {row.original.document_no ?? "Draft"}
      </Link>
    ),
  },
  {
    id: "po",
    header: "PO",
    cell: ({ row }) => (
      <Link href={`/procurement/pos/${row.original.po_id}`} className="text-primary hover:underline">
        {row.original.po_document_no ?? row.original.po_id}
      </Link>
    ),
  },
  { accessorKey: "po_supplier", header: "Supplier", cell: ({ row }) => row.original.po_supplier ?? "—" },
  {
    accessorKey: "status",
    enableSorting: true,
    header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
    cell: ({ row }) => <ProcurementGrnStatusBadge status={row.original.status} label={row.original.status_label} />,
  },
  {
    id: "received",
    header: "Received",
    cell: ({ row }) => (
      <span className="text-muted-foreground">
        {row.original.received_at ? new Date(row.original.received_at).toLocaleString() : "—"}
      </span>
    ),
  },
];

export function createProcurementContractsTableColumns(
  financePaths: FinanceModulePaths,
): ColumnDef<ProcurementContractListRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Contract" />,
      cell: ({ row }) => (
        <Link href={financePaths.contract(row.original.id)} className="font-medium text-primary hover:underline">
          {row.original.document_no ?? row.original.title}
        </Link>
      ),
    },
    {
      accessorKey: "title",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Title" />,
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.title}</span>,
    },
    {
      id: "vendor",
      header: "Vendor",
      cell: ({ row }) => row.original.vendor?.company_name ?? row.original.vendor?.vendor_code ?? "—",
    },
    {
      id: "ceiling",
      header: "Ceiling",
      cell: ({ row }) => (
        <span className="tabular-nums">{formatMoneyNullable(row.original.spend_ceiling, row.original.currency_code)}</span>
      ),
    },
    {
      id: "committed",
      header: "Committed",
      cell: ({ row }) => (
        <span className="tabular-nums">{formatMoney(row.original.committed_po_amount, row.original.currency_code)}</span>
      ),
    },
    {
      accessorKey: "end_date",
      header: "Ends",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.end_date ?? "—"}</span>,
    },
    {
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => <ProcurementPaymentStatusBadge status={row.original.status} label={row.original.status_label} />,
    },
  ];
}

export function createProcurementApInvoicesTableColumns(
  financePaths: FinanceModulePaths,
): ColumnDef<ProcurementApInvoiceListRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Document" />,
      cell: ({ row }) => (
        <Link href={financePaths.apInvoice(row.original.id)} className="font-medium text-primary hover:underline">
          {row.original.document_no ?? "Draft"}
        </Link>
      ),
    },
    {
      accessorKey: "vendor_invoice_no",
      header: "Vendor invoice",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.vendor_invoice_no ?? "—"}</span>,
    },
    {
      id: "po",
      header: "PO",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.po_document_no ?? "—"}</span>,
    },
    {
      id: "match",
      header: "Match",
      cell: ({ row }) => (
        <span className="text-muted-foreground">
          {row.original.match_mode_label} · {row.original.match_status}
        </span>
      ),
    },
    {
      id: "total",
      header: "Total",
      cell: ({ row }) => row.original.grand_total.toLocaleString(undefined, { minimumFractionDigits: 2 }),
    },
    {
      accessorKey: "due_date",
      header: "Due",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.due_date ?? "—"}</span>,
    },
    {
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => (
        <ProcurementApInvoiceStatusBadge status={row.original.status} label={row.original.status_label} />
      ),
    },
  ];
}

export function createProcurementPaymentRequestsTableColumns(
  financePaths: FinanceModulePaths,
): ColumnDef<ProcurementPaymentRequestListRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Payment" />,
      cell: ({ row }) => (
        <Link href={financePaths.payment(row.original.id)} className="font-medium text-primary hover:underline">
          {row.original.document_no ?? "Draft"}
        </Link>
      ),
    },
    {
      id: "vendor",
      header: "Vendor",
      cell: ({ row }) => row.original.vendor_name ?? row.original.vendor_code ?? "—",
    },
    {
      id: "ap_invoice",
      header: "AP invoice",
      cell: ({ row }) =>
        row.original.ap_invoice_id ? (
          <Link href={financePaths.apInvoice(row.original.ap_invoice_id)} className="text-primary hover:underline">
            {row.original.ap_invoice_document_no ?? row.original.ap_vendor_invoice_no ?? "Invoice"}
          </Link>
        ) : (
          "—"
        ),
    },
    {
      id: "amount",
      header: "Amount",
      cell: ({ row }) =>
        `${row.original.amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${row.original.currency_code}`,
    },
    {
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => <ProcurementPaymentStatusBadge status={row.original.status} label={row.original.status_label} />,
    },
    {
      accessorKey: "scheduled_date",
      header: "Scheduled",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.scheduled_date ?? "—"}</span>,
    },
  ];
}

export function createProcurementPaymentBatchesTableColumns(options: {
  batchExportPending: boolean;
  batchReconcilePending: boolean;
  onMarkExported: (batchId: string) => void;
  onReconcile: (batchId: string) => void;
}): ColumnDef<ProcurementPaymentBatchListRow>[] {
  return [
    {
      id: "document",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Batch" />,
      cell: ({ row }) => <span className="font-medium">{row.original.document_no ?? row.original.id}</span>,
    },
    { accessorKey: "payment_request_count", header: "Requests" },
    {
      id: "total",
      header: "Total",
      cell: ({ row }) =>
        `${row.original.total_amount.toLocaleString(undefined, { minimumFractionDigits: 2 })} ${row.original.currency_code}`,
    },
    {
      accessorKey: "status",
      enableSorting: true,
      header: ({ column }) => <DataTableColumnHeader column={column} title="Status" />,
      cell: ({ row }) => <ProcurementPaymentStatusBadge status={row.original.status} label={row.original.status_label} />,
    },
    {
      accessorKey: "scheduled_date",
      header: "Scheduled",
      cell: ({ row }) => <span className="text-muted-foreground">{row.original.scheduled_date ?? "—"}</span>,
    },
    {
      id: "actions",
      header: () => <span className="block w-full text-right">Actions</span>,
      cell: ({ row }) => (
        <ProcurementPaymentBatchRowActions
          row={row.original}
          batchExportPending={options.batchExportPending}
          batchReconcilePending={options.batchReconcilePending}
          onMarkExported={options.onMarkExported}
          onReconcile={options.onReconcile}
        />
      ),
      meta: { className: "text-right" },
    },
  ];
}

function ProcurementPaymentBatchRowActions({
  row,
  batchExportPending,
  batchReconcilePending,
  onMarkExported,
  onReconcile,
}: {
  row: ProcurementPaymentBatchListRow;
  batchExportPending: boolean;
  batchReconcilePending: boolean;
  onMarkExported: (batchId: string) => void;
  onReconcile: (batchId: string) => void;
}) {
  const canManage = usePermission([permissions.procurementOneDocumentsManage]);
  const exportable = row.status === "scheduled" || row.status === "exported";

  return (
    <RowActionsMenu
      items={[
        {
          key: "csv",
          label: (
            <>
              <Download className="size-3.5 text-muted-foreground" />
              Download CSV
            </>
          ),
          hidden: !exportable,
          href: exportable ? procurementPaymentBatchExportUrl(row.id) : undefined,
        },
        {
          key: "mark-exported",
          label: "Mark exported",
          hidden: !(canManage && row.status === "scheduled"),
          disabled: batchExportPending,
          onSelect: () => onMarkExported(row.id),
        },
        {
          key: "reconcile",
          label: "Reconcile batch",
          hidden: !(canManage && exportable),
          disabled: batchReconcilePending,
          onSelect: () => onReconcile(row.id),
        },
      ]}
    />
  );
}
