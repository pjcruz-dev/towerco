/** Shared styles for Input, Select, Textarea, and DatePicker — enterprise form controls. */
export const fieldControlClassName = [
  "w-full min-w-0 rounded-lg border border-input bg-card px-2.5 text-sm text-foreground shadow-xs",
  "transition-[color,box-shadow,border-color] outline-none",
  "placeholder:text-muted-foreground",
  "hover:border-ring/50",
  "focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50",
  "disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-muted/40 disabled:opacity-60 disabled:shadow-none",
  "aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20",
  "dark:bg-card dark:hover:border-ring/40 dark:disabled:bg-muted/50",
  "dark:aria-invalid:border-destructive/50 dark:aria-invalid:ring-destructive/40",
].join(" ");

/** Toolbar / filter bar selects — match Input/Button height (h-9). */
export const filterSelectClassName = `${fieldControlClassName} h-9 min-w-[10rem] pr-8`;

/** Field selects on mobile-heavy flows (rollouts, users filters). */
export const touchFilterSelectClassName = `${fieldControlClassName} h-11 min-w-[10rem] pr-8 text-base sm:h-9 sm:text-sm`;
