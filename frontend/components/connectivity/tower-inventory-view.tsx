"use client";

import { Download, MoreHorizontal, Plus, PowerOff, Search, Signal, Wrench } from "lucide-react";

import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";

const towers = [
  {
    id: "tw-a1b2c3d4",
    name: "Makati Core Hub",
    status: "active" as const,
    location: "Makati, NCR",
    throughput: "12.4 Gbps",
  },
  {
    id: "tw-e5f67890",
    name: "Cebu Coastal Relay",
    status: "maintenance" as const,
    location: "Cebu City, VII",
    throughput: "4.1 Gbps",
  },
  {
    id: "tw-11223344",
    name: "Davao South Edge",
    status: "inactive" as const,
    location: "Davao City, XI",
    throughput: "0 Gbps",
  },
];

export function TowerInventoryView() {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <div className="flex flex-col gap-1">
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">
            Tower Inventory
          </h1>
          <p className="max-w-2xl text-sm leading-relaxed text-muted-foreground">
            Full-spectrum management of telecommunication infrastructure.
          </p>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            className="flex items-center gap-2 rounded-md border border-border bg-card px-4 py-2 text-sm font-medium shadow-sm transition-colors hover:bg-muted/60"
          >
            <Download className="h-3.5 w-3.5" />
            Export CSV
          </button>
          <button
            type="button"
            className="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-opacity hover:opacity-90"
          >
            <Plus className="h-3.5 w-3.5" />
            Provision Site
          </button>
        </div>
      </div>

      <Card className="overflow-hidden rounded-xl border-border bg-card shadow-sm">
        <CardHeader className="border-b border-border bg-muted/30 pb-4">
          <div className="relative max-w-sm flex-1">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
            <input
              type="search"
              placeholder="Filter sites..."
              className="h-10 w-full rounded-lg border border-slate-200 bg-white pl-10 text-sm outline-none transition-all focus:border-blue-600 focus:ring-2 focus:ring-blue-600/20 dark:border-slate-700 dark:bg-slate-950"
            />
          </div>
        </CardHeader>
        <CardContent className="p-0">
          <Table>
            <TableHeader className="bg-slate-50/30 dark:bg-slate-900/30">
              <TableRow className="border-slate-100 hover:bg-transparent dark:border-slate-800">
                <TableHead className="w-12 py-5" />
                <TableHead className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Site Identity
                </TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Status
                </TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Location Matrix
                </TableHead>
                <TableHead className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                  Throughput Metrics
                </TableHead>
                <TableHead className="w-12" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {towers.map((tower) => (
                <TableRow
                  key={tower.id}
                  className="group border-slate-100 transition-colors hover:bg-slate-50/80 dark:border-slate-800 dark:hover:bg-slate-900/50"
                >
                  <TableCell className="text-center font-mono text-[10px] font-medium text-slate-300 dark:text-slate-600">
                    {tower.id.split("-")[1]}
                  </TableCell>
                  <TableCell>
                    <div className="flex flex-col">
                      <span className="text-sm font-medium text-slate-800 transition-colors group-hover:text-blue-600 dark:text-slate-100">
                        {tower.name}
                      </span>
                      <span className="font-mono text-[9px] tracking-tighter text-muted-foreground">
                        UUID: {tower.id.toUpperCase()}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <StatusBadge status={tower.status} />
                  </TableCell>
                  <TableCell className="text-[11px] font-medium text-slate-600 dark:text-slate-300">
                    {tower.location}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-3">
                      <div className="h-1.5 w-32 overflow-hidden rounded-full border border-slate-200/50 bg-slate-100 dark:border-slate-700 dark:bg-slate-800">
                        <div
                          className="h-full bg-blue-600 shadow-[0_0_8px_rgba(37,99,235,0.4)]"
                          style={{ width: `${40 + (tower.id.length % 5) * 12}%` }}
                        />
                      </div>
                      <span className="font-mono text-xs font-medium italic text-slate-700 dark:text-slate-300">
                        {tower.throughput}
                      </span>
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="cursor-pointer rounded p-2 text-muted-foreground transition-colors hover:bg-slate-100 dark:hover:bg-slate-800">
                      <MoreHorizontal className="h-4 w-4" />
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

function StatusBadge({ status }: { status: "active" | "maintenance" | "inactive" }) {
  const configs = {
    active: { icon: Signal, color: "text-emerald-600 bg-emerald-50 border-emerald-100 dark:bg-emerald-950 dark:text-emerald-300", label: "ONLINE" },
    maintenance: { icon: Wrench, color: "text-amber-600 bg-amber-50 border-amber-100 dark:bg-amber-950 dark:text-amber-300", label: "SERVICE" },
    inactive: { icon: PowerOff, color: "text-slate-500 bg-slate-50 border-slate-200 dark:bg-slate-900 dark:text-slate-300", label: "OFFLINE" },
  } as const;

  const config = configs[status];
  const Icon = config.icon;

  return (
    <Badge
      variant="outline"
      className={`flex h-5 items-center gap-1.5 border px-2 py-0.5 font-mono text-[10px] font-medium tracking-wide ${config.color}`}
    >
      <Icon className="h-2.5 w-2.5" />
      {config.label}
    </Badge>
  );
}
