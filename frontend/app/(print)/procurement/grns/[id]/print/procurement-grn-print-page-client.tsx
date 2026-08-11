"use client";

import Link from "next/link";
import { useCallback } from "react";
import { useQuery } from "@tanstack/react-query";

import { ProcurementGrnPrintView } from "@/components/procurement-one/procurement-grn-print-view";
import { Button } from "@/components/ui/button";
import { fetchProcurementGrnPrint } from "@/lib/api/modules/procurement-one-api";

type Props = { grnId: string };

export function ProcurementGrnPrintPageClient({ grnId }: Props) {
  const { data, isError, isLoading, refetch, isFetching } = useQuery({
    queryKey: ["procurement-one", "grn", grnId, "print"],
    queryFn: () => fetchProcurementGrnPrint(grnId),
    retry: 1,
  });

  const handlePrint = useCallback(() => {
    if (typeof window !== "undefined") {
      window.print();
    }
  }, []);

  return (
    <>
      <div className="eapproval-print-toolbar sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-2 print:hidden">
        <Link href={`/procurement/grns/${grnId}`} className="text-sm text-primary hover:underline">
          ← Back to goods receipt
        </Link>
        <div className="flex gap-2">
          <Button type="button" size="sm" variant="outline" onClick={() => refetch()} disabled={isFetching}>
            Refresh
          </Button>
          <Button type="button" size="sm" onClick={handlePrint} disabled={!data}>
            Print / Save as PDF
          </Button>
        </div>
      </div>

      {isLoading ? (
        <p className="flex min-h-[40vh] items-center justify-center text-sm text-slate-500">Loading…</p>
      ) : null}
      {isError ? (
        <p className="flex min-h-[40vh] items-center justify-center text-sm text-destructive">
          Could not load print data. Only posted goods receipts can be printed.
        </p>
      ) : null}
      {data ? <ProcurementGrnPrintView data={data} /> : null}
    </>
  );
}
