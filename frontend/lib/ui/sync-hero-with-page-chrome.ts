import type { DashboardNormalizedData } from "@/lib/ui/dashboard-widget-data";

/**
 * Keep welcome banner copy aligned with page chrome (single source of truth).
 */
export function syncHeroWithPageChrome(
  data: DashboardNormalizedData,
  chrome: { title: string; description: string },
): DashboardNormalizedData {
  if (!data.hero) return data;
  return {
    ...data,
    hero: {
      ...data.hero,
      title: chrome.title,
      description: chrome.description || undefined,
    },
  };
}
