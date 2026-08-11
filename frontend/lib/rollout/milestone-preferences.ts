export const MILESTONE_VIEW_STORAGE_KEY = "toweros.rollout.milestone-view";
export const MILESTONE_ZOOM_STORAGE_KEY = "toweros.rollout.milestone-zoom";

export type MilestoneViewMode = "table" | "grid";
export type MilestoneZoomScale = "week" | "month" | "full";

export const MILESTONE_VIEW_MODES: MilestoneViewMode[] = ["table", "grid"];
export const MILESTONE_ZOOM_SCALES: MilestoneZoomScale[] = ["week", "month", "full"];

/** Default zoom on narrow viewports — avoids ultra-wide full-program tracks on phones. */
export const MILESTONE_MOBILE_DEFAULT_ZOOM: MilestoneZoomScale = "month";
