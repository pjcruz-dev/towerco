"use client";

import { useMutation, useQuery } from "@tanstack/react-query";
import { useEffect, useMemo, useState } from "react";

import { OperationalAlert } from "@/components/feedback/operational-alert";
import { ProcurementRfqLinesSection } from "@/components/procurement-one/procurement-rfq-lines-section";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import {
  allowsMonthlyQuote,
  allowsYearlyQuote,
  formatMoney,
  quoteBasisLabel,
  requiresUnitPrice,
} from "@/lib/procurement/quote-basis";
import { getErrorMessage } from "@/lib/api/error";
import {
  fetchProcurementRfqPublicQuote,
  submitProcurementRfqPublicQuote,
} from "@/lib/api/modules/procurement-rfq-public-api";

type Props = {
  accessToken: string;
};

type LineDraft = {
  rfq_line_id: string;
  quantity: string;
  unit_price: string;
  monthly_unit_price: string;
  yearly_unit_price: string;
  lead_time_days: string;
  notes: string;
};

export function ProcurementRfqPublicQuotePageClient({ accessToken }: Props) {
  const [contactName, setContactName] = useState("");
  const [notes, setNotes] = useState("");
  const [flashMessage, setFlashMessage] = useState<string | null>(null);
  const [attachmentFiles, setAttachmentFiles] = useState<File[]>([]);

  const query = useQuery({
    queryKey: ["procurement", "public", "rfq-quote", accessToken],
    queryFn: () => fetchProcurementRfqPublicQuote(accessToken),
  });

  const initialLines = useMemo((): LineDraft[] => {
    const payload = query.data;
    if (!payload) return [];

    const existing = payload.existing_bid?.lines ?? [];

    return payload.rfq.lines.map((line) => {
      const prior = existing.find((row) => row.rfq_line_id === line.id);

      return {
        rfq_line_id: line.id,
        quantity: String(prior?.quantity ?? line.quantity),
        unit_price: String(prior?.unit_price ?? ""),
        monthly_unit_price: String(prior?.monthly_unit_price ?? ""),
        yearly_unit_price: String(prior?.yearly_unit_price ?? ""),
        lead_time_days: String(prior?.lead_time_days ?? 7),
        notes: prior?.notes ?? "",
      };
    });
  }, [query.data]);

  const [lines, setLines] = useState<LineDraft[]>([]);

  useEffect(() => {
    if (initialLines.length > 0) {
      setLines(initialLines);
    }
  }, [initialLines]);

  useEffect(() => {
    const portalName = query.data?.invitation.portal_contact_name;
    if (portalName && contactName === "") {
      setContactName(portalName);
    }
  }, [query.data?.invitation.portal_contact_name, contactName]);

  const submitMutation = useMutation({
    mutationFn: () =>
      submitProcurementRfqPublicQuote(
        accessToken,
        {
          contact_name: contactName.trim(),
          notes: notes.trim() || undefined,
          lines: lines.map((line) => ({
            rfq_line_id: line.rfq_line_id,
            quantity: Number(line.quantity) || 0,
            unit_price: line.unit_price.trim() !== "" ? Number(line.unit_price) : undefined,
            monthly_unit_price: line.monthly_unit_price.trim() !== "" ? Number(line.monthly_unit_price) : undefined,
            yearly_unit_price: line.yearly_unit_price.trim() !== "" ? Number(line.yearly_unit_price) : undefined,
            lead_time_days: Number(line.lead_time_days) || undefined,
            notes: line.notes.trim() || undefined,
          })),
        },
        attachmentFiles,
      ),
    onSuccess: (result) => {
      setFlashMessage(result.message);
      query.refetch();
    },
  });

  const payload = query.data;
  const rfq = payload?.rfq;
  const canEdit = payload?.can_submit ?? false;
  const isRevision = payload?.can_revise ?? Boolean(payload?.has_existing_bid && canEdit);
  const blockedTitle =
    payload?.rfq.status === "draft" ||
    payload?.submission_blocked_reason?.toLowerCase().includes("not opened")
      ? "Bidding not open yet"
      : "Submissions closed";
  const blockedDescription =
    payload?.submission_blocked_reason ?? "This RFQ is not accepting new quotations at the moment.";

  return (
    <div className="mx-auto w-full max-w-3xl space-y-6">
      <header className="space-y-1">
        <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Vendor quotation</p>
        <h1 className="text-2xl font-semibold text-foreground">{rfq?.title ?? "Request for quotation"}</h1>
        {rfq?.document_no ? <p className="text-sm text-muted-foreground">RFQ {rfq.document_no}</p> : null}
      </header>

      {query.isLoading ? <p className="text-sm text-muted-foreground">Loading quotation form…</p> : null}
      {query.isError ? (
        <OperationalAlert level="error" title="Unable to open quotation link" description={getErrorMessage(query.error)} />
      ) : null}

      {payload && rfq ? (
        <>
          <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
            <dl className="grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs text-muted-foreground">Supplier</dt>
                <dd className="mt-0.5 font-medium">{payload.vendor.company_name ?? payload.vendor.vendor_code}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Currency</dt>
                <dd className="mt-0.5 font-medium">{rfq.currency_code}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Status</dt>
                <dd className="mt-0.5 font-medium">{rfq.status_label}</dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Bidding closes</dt>
                <dd className="mt-0.5 font-medium">
                  {rfq.bidding_closes_at ? new Date(rfq.bidding_closes_at).toLocaleString() : "—"}
                </dd>
              </div>
            </dl>
            {rfq.description ? <p className="mt-4 text-sm text-muted-foreground">{rfq.description}</p> : null}
          </section>

          <ProcurementRfqLinesSection
            lines={rfq.lines}
            currencyCode={rfq.currency_code}
            hideTargetPrices
            linesSource={rfq.lines_source}
          />

          {flashMessage ? (
            <OperationalAlert level="success" title={isRevision ? "Quotation updated" : "Quotation submitted"} description={flashMessage} />
          ) : null}

          {isRevision && canEdit ? (
            <OperationalAlert
              level="info"
              title="Quotation on file"
              description="You can update your prices and quantities until bidding closes."
            />
          ) : null}

          {!canEdit && payload.has_existing_bid ? (
            <OperationalAlert
              level="info"
              title="Quotation locked"
              description="Your quotation is on file and can no longer be changed for this RFQ."
            />
          ) : null}

          {!canEdit && !payload.has_existing_bid ? (
            <OperationalAlert
              level="info"
              title={blockedTitle}
              description={blockedDescription}
            />
          ) : null}

          {canEdit ? (
            <form
              className="space-y-6"
              onSubmit={(event) => {
                event.preventDefault();
                setFlashMessage(null);
                submitMutation.mutate();
              }}
            >
              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">Your details</h2>
                <div className="mt-4 grid gap-4 sm:grid-cols-2">
                  <div className="sm:col-span-2">
                    <Label htmlFor="contact_name">Your name</Label>
                    <Input
                      id="contact_name"
                      value={contactName}
                      onChange={(e) => setContactName(e.target.value)}
                      required
                      className="mt-1"
                      placeholder="Contact person"
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <Label htmlFor="quote_notes">Notes (optional)</Label>
                    <Input
                      id="quote_notes"
                      value={notes}
                      onChange={(e) => setNotes(e.target.value)}
                      className="mt-1"
                      placeholder="Validity, delivery terms, etc."
                    />
                  </div>
                  <div className="sm:col-span-2">
                    <Label htmlFor="quote_attachments">Attachments (optional)</Label>
                    <Input
                      id="quote_attachments"
                      type="file"
                      multiple
                      className="mt-1"
                      onChange={(event) => setAttachmentFiles(Array.from(event.target.files ?? []))}
                    />
                    <p className="mt-1 text-xs text-muted-foreground">PDF or spreadsheet — up to 5 files, 10 MB each.</p>
                  </div>
                </div>
              </section>

              <section className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h2 className="text-base font-medium">{isRevision ? "Update your prices" : "Line items"}</h2>
                <div className="mt-4 space-y-4">
                  {rfq.lines.map((line, index) => {
                    const draft = lines[index];
                    const basis = line.quote_basis ?? "one_time";
                    const qty = Number(draft?.quantity ?? line.quantity) || 0;
                    const monthlyTotal =
                      draft?.monthly_unit_price && Number(draft.monthly_unit_price) > 0
                        ? qty * Number(draft.monthly_unit_price)
                        : null;
                    const yearlyTotal =
                      draft?.yearly_unit_price && Number(draft.yearly_unit_price) > 0
                        ? qty * Number(draft.yearly_unit_price)
                        : null;

                    return (
                    <div key={line.id} className="rounded-lg border border-border p-3">
                      <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                          <p className="text-sm font-medium">{line.description}</p>
                          <p className="text-xs text-muted-foreground">
                            Qty {line.quantity} {line.uom ?? "ea"} · {line.quote_basis_label ?? quoteBasisLabel(basis)}
                          </p>
                        </div>
                        {monthlyTotal != null || yearlyTotal != null ? (
                          <div className="text-right text-xs text-muted-foreground">
                            {monthlyTotal != null ? (
                              <p>Monthly total: {formatMoney(monthlyTotal, rfq.currency_code)}</p>
                            ) : null}
                            {yearlyTotal != null ? (
                              <p>Yearly total: {formatMoney(yearlyTotal, rfq.currency_code)}</p>
                            ) : null}
                            {monthlyTotal != null ? (
                              <p>Annualized (×12): {formatMoney(monthlyTotal * 12, rfq.currency_code)}</p>
                            ) : null}
                          </div>
                        ) : null}
                      </div>
                      <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                          <Label htmlFor={`qty_${line.id}`}>Quantity</Label>
                          <Input
                            id={`qty_${line.id}`}
                            type="number"
                            min={0}
                            step="any"
                            value={draft?.quantity ?? ""}
                            onChange={(e) =>
                              setLines((current) =>
                                current.map((row, rowIndex) =>
                                  rowIndex === index ? { ...row, quantity: e.target.value } : row,
                                ),
                              )
                            }
                            className="mt-1"
                          />
                        </div>
                        {requiresUnitPrice(basis) ? (
                          <div>
                            <Label htmlFor={`price_${line.id}`}>Unit price</Label>
                            <Input
                              id={`price_${line.id}`}
                              type="number"
                              min={0}
                              step="0.01"
                              value={draft?.unit_price ?? ""}
                              onChange={(e) =>
                                setLines((current) =>
                                  current.map((row, rowIndex) =>
                                    rowIndex === index ? { ...row, unit_price: e.target.value } : row,
                                  ),
                                )
                              }
                              className="mt-1"
                              required
                            />
                          </div>
                        ) : null}
                        {allowsMonthlyQuote(basis) ? (
                          <div>
                            <Label htmlFor={`monthly_${line.id}`}>Monthly unit price</Label>
                            <Input
                              id={`monthly_${line.id}`}
                              type="number"
                              min={0}
                              step="0.01"
                              value={draft?.monthly_unit_price ?? ""}
                              onChange={(e) =>
                                setLines((current) =>
                                  current.map((row, rowIndex) =>
                                    rowIndex === index ? { ...row, monthly_unit_price: e.target.value } : row,
                                  ),
                                )
                              }
                              className="mt-1"
                              required={basis === "monthly"}
                            />
                          </div>
                        ) : null}
                        {allowsYearlyQuote(basis) ? (
                          <div>
                            <Label htmlFor={`yearly_${line.id}`}>Yearly unit price</Label>
                            <Input
                              id={`yearly_${line.id}`}
                              type="number"
                              min={0}
                              step="0.01"
                              value={draft?.yearly_unit_price ?? ""}
                              onChange={(e) =>
                                setLines((current) =>
                                  current.map((row, rowIndex) =>
                                    rowIndex === index ? { ...row, yearly_unit_price: e.target.value } : row,
                                  ),
                                )
                              }
                              className="mt-1"
                              required={basis === "yearly"}
                            />
                          </div>
                        ) : null}
                        <div>
                          <Label htmlFor={`lead_${line.id}`}>Lead time (days)</Label>
                          <Input
                            id={`lead_${line.id}`}
                            type="number"
                            min={0}
                            value={draft?.lead_time_days ?? ""}
                            onChange={(e) =>
                              setLines((current) =>
                                current.map((row, rowIndex) =>
                                  rowIndex === index ? { ...row, lead_time_days: e.target.value } : row,
                                ),
                              )
                            }
                            className="mt-1"
                          />
                        </div>
                      </div>
                    </div>
                    );
                  })}
                </div>
              </section>

              {submitMutation.isError ? (
                <OperationalAlert level="error" title="Submission failed" description={getErrorMessage(submitMutation.error)} />
              ) : null}

              <Button type="submit" disabled={submitMutation.isPending || contactName.trim() === ""}>
                {submitMutation.isPending ? "Saving…" : isRevision ? "Update quotation" : "Submit quotation"}
              </Button>
            </form>
          ) : null}
        </>
      ) : null}
    </div>
  );
}
