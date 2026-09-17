"use client";

import { Download, FileImage, FileText, Printer } from "lucide-react";
import { useMemo, useState } from "react";

import { AdminOrgTreeView } from "@/components/admin/admin-org-tree-view";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogBody,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import {
  emptyOrgChartFilters,
  filterOrgChartIndex,
  type OrgChartIndex,
} from "@/lib/admin/org-chart";
import {
  downloadOrgChartAsPdf,
  downloadOrgChartAsPng,
  printOrgChartElement,
} from "@/lib/admin/org-chart-export";
import { useNotificationStore } from "@/stores/notification-store";

type ExportScope = "all" | "department";

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  index: OrgChartIndex;
  departments: string[];
  focusedId: string | null;
  showLicense: boolean;
};

export function AdminOrgChartExportDialog({
  open,
  onOpenChange,
  index,
  departments,
  focusedId,
  showLicense,
}: Props) {
  const notify = useNotificationStore((state) => state.push);
  const [scope, setScope] = useState<ExportScope>("all");
  const [department, setDepartment] = useState("");
  const [busy, setBusy] = useState<"print" | "png" | "pdf" | null>(null);

  const exportIndex = useMemo(() => {
    if (scope !== "department" || !department) {
      return index;
    }
    return filterOrgChartIndex(index, { ...emptyOrgChartFilters(), department });
  }, [department, index, scope]);

  const title =
    scope === "department" && department
      ? `Organization chart · ${department}`
      : "Organization chart · All organization";

  const runExport = async (mode: "print" | "png" | "pdf") => {
    if (scope === "department" && !department) {
      notify({ level: "error", title: "Choose a department" });
      return;
    }
    if (exportIndex.nodes.length === 0) {
      notify({ level: "error", title: "Nothing to export", message: "No people match this scope." });
      return;
    }

    const host = document.getElementById("org-chart-export-surface");
    if (!host) {
      notify({ level: "error", title: "Export surface missing" });
      return;
    }

    setBusy(mode);
    try {
      // Allow layout + avatar images to settle before capture.
      await new Promise((resolve) => requestAnimationFrame(() => resolve(undefined)));
      await new Promise((resolve) => setTimeout(resolve, 180));

      const stamp = new Date().toISOString().slice(0, 10);
      const slug =
        scope === "department" && department
          ? department.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") || "department"
          : "all-organization";

      if (mode === "print") {
        await printOrgChartElement(host, { title });
      } else if (mode === "png") {
        await downloadOrgChartAsPng(host, {
          filename: `org-chart-${slug}-${stamp}`,
          title,
        });
      } else {
        await downloadOrgChartAsPdf(host, {
          filename: `org-chart-${slug}-${stamp}`,
          title,
        });
      }

      notify({
        level: "success",
        title: mode === "print" ? "Print dialog opened" : "Download started",
        message: mode === "print" ? "Use the browser print dialog to finish." : undefined,
      });
      if (mode !== "print") {
        onOpenChange(false);
      }
    } catch (error) {
      notify({
        level: "error",
        title: "Could not export org chart",
        message: error instanceof Error ? error.message : "Try again.",
      });
    } finally {
      setBusy(null);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Print or download org chart</DialogTitle>
        </DialogHeader>
        <DialogBody className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="org-export-scope">Scope</Label>
            <Select
              id="org-export-scope"
              value={scope}
              onChange={(event) => setScope(event.target.value as ExportScope)}
              className="h-9"
            >
              <option value="all">Whole organization</option>
              <option value="department">Specific department</option>
            </Select>
          </div>
          {scope === "department" ? (
            <div className="space-y-2">
              <Label htmlFor="org-export-department">Department</Label>
              <Select
                id="org-export-department"
                value={department}
                onChange={(event) => setDepartment(event.target.value)}
                className="h-9"
              >
                <option value="">Select department…</option>
                {departments.map((dept) => (
                  <option key={dept} value={dept}>
                    {dept}
                  </option>
                ))}
              </Select>
            </div>
          ) : null}
          <p className="text-xs text-muted-foreground">
            Export captures the full expanded chart for the selected scope (all reporting lines in view).
          </p>

          {/* Off-screen render target for capture — must stay in DOM while dialog is open. */}
          <div
            aria-hidden
            className="pointer-events-none fixed left-[-10000px] top-0 z-[-1] w-max max-w-none bg-white p-6 text-foreground"
          >
            <div id="org-chart-export-surface" className="bg-white">
              <p className="mb-4 text-sm font-semibold text-foreground">{title}</p>
              <AdminOrgTreeView
                index={exportIndex}
                focusedId={focusedId}
                onSelect={() => undefined}
                showRoles={false}
                showLicense={showLicense}
                exportMode
              />
            </div>
          </div>
        </DialogBody>
        <DialogFooter className="flex flex-wrap gap-2 sm:justify-end">
          <Button type="button" variant="outline" disabled={Boolean(busy)} onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" variant="outline" disabled={Boolean(busy)} onClick={() => void runExport("print")}>
            {busy === "print" ? <Spinner className="size-3.5" /> : <Printer className="size-3.5" />}
            Print
          </Button>
          <Button type="button" variant="outline" disabled={Boolean(busy)} onClick={() => void runExport("png")}>
            {busy === "png" ? <Spinner className="size-3.5" /> : <FileImage className="size-3.5" />}
            PNG
          </Button>
          <Button type="button" disabled={Boolean(busy)} onClick={() => void runExport("pdf")}>
            {busy === "pdf" ? <Spinner className="size-3.5" /> : <FileText className="size-3.5" />}
            PDF
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export function AdminOrgChartExportTrigger({
  disabled,
  onClick,
}: {
  disabled?: boolean;
  onClick: () => void;
}) {
  return (
    <Button type="button" size="sm" variant="outline" disabled={disabled} onClick={onClick}>
      <Download className="size-3.5" />
      Print / Download
    </Button>
  );
}
