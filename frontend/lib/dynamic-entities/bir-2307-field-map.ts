/**
 * Field placement for official BIR Form 2307 (January 2018 ENCS) page 1.
 * Coordinates are top-left origin on the calibration grid (612×934).
 * Print/fill uses the vector PDF (`templatePdfSrc`) for sharp output;
 * `backgroundSrc` PNG is only a screen/print-fallback overlay.
 */

export const BIR_2307_PAGE = {
  width: 612,
  height: 934,
  backgroundSrc: "/forms/bir-2307-jan-2018-page1.png",
  templatePdfSrc: "/forms/bir-2307-jan-2018-encs-v3.pdf",
} as const;

export type Bir2307OverlayField =
  | {
      id: string;
      kind: "text";
      x: number;
      y: number;
      w?: number;
      size?: number;
      align?: "left" | "center" | "right";
      bold?: boolean;
      uppercase?: boolean;
    }
  | {
      id: string;
      kind: "chars";
      x: number;
      y: number;
      pitch: number;
      size?: number;
      gapsAfter?: number[];
      gapExtra?: number;
      /** Absolute left positions per character (overrides pitch/gaps when set). */
      xs?: number[];
    };

/**
 * Calibrated against official page-1 artwork (612×934 raster).
 * Payee TIN boxes: y≈137–150, groups at x 207–244 | 258–295 | 310–347 | 361–433 (last group wider).
 * Payor TIN boxes: y≈252–266, same x groups.
 */
export const BIR_2307_FIELDS: Bir2307OverlayField[] = [
  // 1 — Period From / To
  { id: "period_from", kind: "chars", x: 151, y: 108, pitch: 13.05, size: 10, gapsAfter: [2, 4], gapExtra: 0 },
  { id: "period_to", kind: "chars", x: 398, y: 108, pitch: 13.05, size: 10, gapsAfter: [2, 4], gapExtra: 0 },

  // 2 — Payee TIN (sit inside grey boxes; last segment uses wider cells)
  {
    id: "payee_tin",
    kind: "chars",
    x: 210,
    y: 140,
    pitch: 13,
    size: 10,
    xs: [210, 223, 236, 261, 274, 287, 312, 325, 338, 372, 397, 421],
  },
  // 3 — Payee name
  { id: "payee_name", kind: "text", x: 175, y: 164, w: 410, size: 10, bold: true, uppercase: true },
  // 4 — Address + 4A ZIP
  { id: "payee_address", kind: "text", x: 175, y: 192, w: 330, size: 9 },
  { id: "payee_zip", kind: "chars", x: 530, y: 192, pitch: 13, size: 10 },
  // 5 — Foreign address
  { id: "payee_foreign_address", kind: "text", x: 175, y: 222, w: 410, size: 9 },

  // 6–8 Payor
  {
    id: "payor_tin",
    kind: "chars",
    x: 210,
    y: 255,
    pitch: 13,
    size: 10,
    xs: [210, 223, 236, 261, 274, 287, 312, 325, 338, 372, 397, 421],
  },
  { id: "payor_name", kind: "text", x: 175, y: 278, w: 410, size: 10, bold: true, uppercase: true },
  { id: "payor_address", kind: "text", x: 175, y: 306, w: 330, size: 9 },
  { id: "payor_zip", kind: "chars", x: 530, y: 306, pitch: 13, size: 10 },

  // Part III — first data row
  { id: "income_description", kind: "text", x: 28, y: 366, w: 140, size: 7.5 },
  { id: "atc_code", kind: "text", x: 178, y: 366, w: 36, size: 8, align: "center", bold: true },
  { id: "income_month1", kind: "text", x: 220, y: 366, w: 66, size: 8, align: "right" },
  { id: "income_month2", kind: "text", x: 292, y: 366, w: 68, size: 8, align: "right" },
  { id: "income_month3", kind: "text", x: 366, y: 366, w: 66, size: 8, align: "right" },
  { id: "income_total", kind: "text", x: 438, y: 366, w: 66, size: 8, align: "right", bold: true },
  { id: "tax_withheld", kind: "text", x: 510, y: 366, w: 78, size: 8, align: "right", bold: true },

  // EWT Total row
  { id: "ewt_total_income", kind: "text", x: 438, y: 500, w: 66, size: 8, align: "right", bold: true },
  { id: "ewt_total_tax", kind: "text", x: 510, y: 500, w: 78, size: 8, align: "right", bold: true },

  // Signatures
  { id: "payor_signatory", kind: "text", x: 160, y: 730, w: 290, size: 10, align: "center", bold: true, uppercase: true },
  { id: "payee_signatory", kind: "text", x: 160, y: 808, w: 290, size: 10, align: "center", bold: true, uppercase: true },
];

export type Bir2307Values = Record<string, string>;

export function buildBir2307Values(raw: Record<string, unknown>, title?: string | null): Bir2307Values {
  const pick = (...keys: string[]) => {
    for (const k of keys) {
      const v = raw[k];
      if (v !== undefined && v !== null && String(v).trim() !== "") return String(v).trim();
    }
    return "";
  };
  const money = (...keys: string[]) => {
    const s = pick(...keys);
    if (!s) return "";
    const n = Number(String(s).replace(/[^\d.-]/g, ""));
    if (!Number.isFinite(n) || n === 0) return "";
    return new Intl.NumberFormat("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
  };
  const num = (...keys: string[]) => {
    const s = pick(...keys);
    if (!s) return 0;
    const n = Number(String(s).replace(/[^\d.-]/g, ""));
    return Number.isFinite(n) ? n : 0;
  };

  const m1 = num("income_month1", "month1_amount", "first_month");
  const m2 = num("income_month2", "month2_amount", "second_month");
  const m3 = num("income_month3", "month3_amount", "third_month");
  const incomeTotal = m1 + m2 + m3;
  const rate = num("tax_rate", "rate");
  const taxExplicit = num("tax_withheld", "tax_withheld_quarter", "amount");
  const tax = taxExplicit > 0 ? taxExplicit : rate > 0 ? (incomeTotal * rate) / 100 : 0;

  const fmt = (n: number) =>
    n
      ? new Intl.NumberFormat("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
      : "";

  const payeeAddress = pick("payee_address", "registered_address");
  const payeeForeign = pick("payee_foreign_address", "foreign_address");

  return {
    period_from: toMdYDigits(pick("period_from", "from_date", "date_from")),
    period_to: toMdYDigits(pick("period_to", "to_date", "date_to")),
    payee_tin: digits(pick("payee_tin", "tin")).slice(0, 12),
    payee_name: pick("payee_name", "payee", "registered_name"),
    payee_address: payeeAddress,
    payee_zip: digits(pick("payee_zip", "payee_zip_code", "zip_code")).slice(0, 4),
    payee_foreign_address:
      payeeForeign && payeeForeign.toLowerCase() !== payeeAddress.toLowerCase() ? payeeForeign : "",
    payor_tin: digits(pick("payor_tin", "withholding_agent_tin")).slice(0, 12),
    payor_name: pick("payor_name", "payor", "withholding_agent_name"),
    payor_address: pick("payor_address"),
    payor_zip: digits(pick("payor_zip", "payor_zip_code")).slice(0, 4),
    income_description: pick("income_description", "nature_of_income", "description"),
    atc_code: pick("atc_code", "atc"),
    income_month1: money("income_month1", "month1_amount", "first_month"),
    income_month2: money("income_month2", "month2_amount", "second_month"),
    income_month3: money("income_month3", "month3_amount", "third_month"),
    income_total: fmt(incomeTotal),
    tax_withheld: fmt(tax),
    ewt_total_income: fmt(incomeTotal),
    ewt_total_tax: fmt(tax),
    payor_signatory: pick("payor_signatory", "authorized_representative", "payor_name", "payor"),
    payee_signatory: pick("payee_signatory", "payee_name", "payee"),
    control_number: pick("control_number", "certificate_number", "certificate_no") || (title ?? ""),
  };
}

function digits(s: string): string {
  return s.replace(/\D/g, "");
}

function toMdYDigits(raw: string): string {
  if (!raw) return "";
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(raw)) return raw.replace(/\D/g, "");
  const iso = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (iso) return `${iso[2]}${iso[3]}${iso[1]}`;
  const d = new Date(raw);
  if (!Number.isNaN(d.getTime())) {
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    return `${mm}${dd}${d.getFullYear()}`;
  }
  return digits(raw).slice(0, 8);
}
