"use client";

import { usePathname } from "next/navigation";

import { financeOneRoutes, FINANCE_ONE_HOME } from "@/lib/navigation/finance-one-routes";

/** Resolves finance URLs based on whether the user is under /finance or legacy /procurement paths. */
export function useFinanceModulePaths() {
  const pathname = usePathname();
  const base = pathname.startsWith("/finance") ? "/finance" : "/procurement";

  return {
    home: pathname.startsWith("/finance") ? FINANCE_ONE_HOME : "/procurement",
    moduleLabel: pathname.startsWith("/finance") ? "Finance-One" : "Procurement-One",
    budget: `${base}/budget`,
    apInvoices: `${base}/ap-invoices`,
    apInvoice: (id: string) => `${base}/ap-invoices/${id}`,
    apInvoiceEdit: (id: string) => `${base}/ap-invoices/${id}/edit`,
    payments: `${base}/payments`,
    payment: (id: string) => `${base}/payments/${id}`,
    contracts: `${base}/contracts`,
    contract: (id: string) => `${base}/contracts/${id}`,
    contractNew: `${base}/contracts/new`,
    reports: `${base}/reports`,
    financeOneRoutes,
  };
}
