/** Shared styles for Input, Select, Textarea, and DatePicker — enterprise form controls. */
export const fieldControlClassName = [
  "w-full min-w-0 rounded-lg border border-input bg-card px-2.5 text-sm text-foreground shadow-xs",
  // Safari needs explicit appearance + color so controls don't blend into the page.
  "[-webkit-appearance:none] appearance-none text-foreground",
  "transition-[color,box-shadow,border-color] outline-none",
  "placeholder:text-muted-foreground",
  "hover:border-ring/50",
  "focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50",
  "disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-60 disabled:shadow-none",
  "aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20",
  "dark:bg-card dark:hover:border-ring/40 dark:disabled:bg-muted/50",
  "dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40",
].join(" ");

/** Toolbar / filter bar selects — match Input/Button height (h-9) and fill their column. */
export const filterSelectClassName = `${fieldControlClassName} h-9 min-w-0 pr-8`;

/**
 * Equal-width filter controls inside a bar. Direct field divs and FilterSelect
 * roots share the row. Mark a child `data-filter-fixed` to keep its own width.
 */
export const filterBarFieldsClassName = [
  "flex flex-wrap items-end gap-2",
  "[&>div:not([data-filter-fixed])]:min-w-0 [&>div:not([data-filter-fixed])]:flex-1 [&>div:not([data-filter-fixed])]:basis-36 [&>div:not([data-filter-fixed])]:space-y-1",
  "[&>[data-slot=filter-field]]:min-w-0 [&>[data-slot=filter-field]]:flex-1 [&>[data-slot=filter-field]]:basis-36",
  "[&_select]:w-full [&_select]:min-w-0",
].join(" ");

/** Bordered filter bar used above lists and reports. */
export const filterBarClassName = `${filterBarFieldsClassName} rounded-xl border border-border bg-card px-3 py-2 shadow-sm`;

/** Field selects on mobile-heavy flows (rollouts, users filters). */
export const touchFilterSelectClassName = `${fieldControlClassName} h-11 min-w-[10rem] pr-8 text-base sm:h-9 sm:text-sm`;
