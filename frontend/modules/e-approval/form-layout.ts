/** Primary max width for E-Approval document forms (compose, preview, builder). */
export const E_APPROVAL_WIDE_FORM_MAX_WIDTH_CLASS = "max-w-7xl";

/** Focus / distraction-free request shell — matches tenant workspace width. */
export const E_APPROVAL_FOCUS_MAX_WIDTH_CLASS = "max-w-[min(100%,1920px)]";

/** Workflow editor needs horizontal room for If/Else and threshold ladders. */
export const E_APPROVAL_WORKFLOW_SHELL_CLASS = `mx-auto w-full ${E_APPROVAL_FOCUS_MAX_WIDTH_CLASS} space-y-4`;

/** Page shell for full-width E-Approval / procurement document compose flows. */
export const E_APPROVAL_FORM_SHELL_CLASS = `mx-auto w-full ${E_APPROVAL_WIDE_FORM_MAX_WIDTH_CLASS} space-y-6`;

/** Inner shell for embedded compose panels (`fullPage` mode). */
export const E_APPROVAL_COMPOSE_SHELL_CLASS = `mx-auto w-full ${E_APPROVAL_WIDE_FORM_MAX_WIDTH_CLASS} space-y-4`;

/** Narrower shell for short field-only forms (e.g. vendor registration). */
export const E_APPROVAL_SIMPLE_FORM_SHELL_CLASS = "mx-auto w-full max-w-4xl space-y-6";
