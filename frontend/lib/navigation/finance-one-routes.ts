/** Finance-One workspace routes (procurement finance features). */
export const FINANCE_ONE_HOME = "/finance" as const;

export const financeOneRoutes = {
  home: FINANCE_ONE_HOME,
  budget: "/finance/budget",
  apInvoices: "/finance/ap-invoices",
  apInvoice: (id: string) => `/finance/ap-invoices/${id}`,
  apInvoiceEdit: (id: string) => `/finance/ap-invoices/${id}/edit`,
  payments: "/finance/payments",
  payment: (id: string) => `/finance/payments/${id}`,
  contracts: "/finance/contracts",
  contract: (id: string) => `/finance/contracts/${id}`,
  contractNew: "/finance/contracts/new",
  reports: "/finance/reports",
} as const;

export const FINANCE_ONE_PROCUREMENT_SEGMENTS = new Set([
  "budget",
  "ap-invoices",
  "payments",
  "contracts",
  "reports",
]);
