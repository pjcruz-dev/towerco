"use client";

import { useCallback, useEffect, useLayoutEffect, useMemo, useState, type MouseEvent, type PointerEvent } from "react";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { X } from "lucide-react";

import { Button } from "@/components/ui/button";
import { useSidebar } from "@/components/ui/sidebar";
import { dismissLiveTourPrompt } from "@/lib/help/e-approval-tour-prompt-preference";
import {
  E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH,
  eApprovalTourExitPath,
} from "@/lib/help/e-approval-tour-fixtures";
import {
  E_APPROVAL_VISUAL_GUIDE_PATH,
  LIVE_TOUR_CHAPTER_QUERY,
  LIVE_TOUR_QUERY,
  LIVE_TOUR_STEP_QUERY,
  buildTourSearchParams,
  chapterForEApprovalStepId,
  getLiveTourChapterProgress,
  isLiveTourChapterId,
  pathMatchesTourStep,
  resolveLiveTour,
  type LiveTourChapterId,
  type LiveTourStep,
} from "@/lib/help/e-approval-live-tour";
import { markEApprovalTourChapterComplete } from "@/lib/help/e-approval-tour-chapter-progress";
import { permissions } from "@/lib/rbac/permissions";
import { cn } from "@/lib/utils";
import { usePermission } from "@/hooks/use-permission";
import { useIsMobile } from "@/hooks/use-mobile";
import { useAuthStore } from "@/stores/auth-store";

type TargetRect = {
  top: number;
  left: number;
  width: number;
  height: number;
};

const PAD = 10;
/** Tighter pad so the red ring hugs compact controls (sidebar rows, buttons). */
const PAD_TIGHT = 4;
const PANEL_WIDTH = 352;
const PANEL_EST_HEIGHT = 220;
const SIDEBAR_TRIGGER_HELP = "ea-sidebar-trigger";

function isSidebarNavTarget(target: string): boolean {
  return target.startsWith("ea-nav-") || target === SIDEBAR_TRIGGER_HELP;
}

function spotlightPadForTarget(target: string, rect: TargetRect): number {
  if (isSidebarNavTarget(target) || rect.height <= 48) {
    return PAD_TIGHT;
  }
  return PAD;
}

function readTargetRect(target: string): TargetRect | null {
  const el = document.querySelector<HTMLElement>(`[data-help="${CSS.escape(target)}"]`);
  if (!el) {
    return null;
  }
  const rect = el.getBoundingClientRect();
  if (rect.width < 2 && rect.height < 2) {
    return null;
  }
  // Off-screen / closed sheet: ignore until the menu is open.
  if (rect.bottom < 0 || rect.top > window.innerHeight || rect.right < 0 || rect.left > window.innerWidth) {
    return null;
  }
  return {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  };
}

function findSubmissionDetailPath(): string | null {
  const sample = document.querySelector<HTMLAnchorElement>(
    `a[href*="${E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH}"]:not([href*="/print"])`,
  );
  if (sample) {
    try {
      const pathname = new URL(sample.getAttribute("href") ?? "", window.location.origin).pathname;
      if (!pathname.endsWith("/print")) {
        return pathname;
      }
    } catch {
      return E_APPROVAL_TOUR_SAMPLE_DETAIL_PATH;
    }
  }

  const anchors = document.querySelectorAll<HTMLAnchorElement>('a[href*="/e-approval/submissions/"]');
  for (const anchor of anchors) {
    try {
      const url = new URL(anchor.getAttribute("href") ?? "", window.location.origin);
      if (url.pathname.endsWith("/print")) {
        continue;
      }
      if (
        !pathMatchesTourStep(url.pathname, {
          id: "_",
          path: "/e-approval/submissions/",
          pathMatch: "prefix",
          target: "_",
          title: "",
          body: "",
        })
      ) {
        continue;
      }
      return url.pathname;
    } catch {
      // ignore bad hrefs
    }
  }
  return null;
}

function resolveStepHref(
  step: LiveTourStep,
  tourId: string,
  stepIndex: number,
  pathname: string,
  existing?: URLSearchParams,
): string {
  const params = buildTourSearchParams(tourId, stepIndex, step, existing);

  if (pathMatchesTourStep(pathname, step)) {
    return `${pathname}?${params.toString()}`;
  }

  if (step.autoNavFrom && typeof document !== "undefined") {
    const el = document.querySelector<HTMLElement>(`[data-help="${CSS.escape(step.autoNavFrom)}"]`);
    const nav = el?.getAttribute("data-tour-nav");
    if (nav) {
      try {
        const url = new URL(nav, window.location.origin);
        url.searchParams.forEach((value, key) => {
          if (
            key !== LIVE_TOUR_QUERY &&
            key !== LIVE_TOUR_STEP_QUERY &&
            key !== LIVE_TOUR_CHAPTER_QUERY
          ) {
            params.set(key, value);
          }
        });
        return `${url.pathname}?${params.toString()}`;
      } catch {
        // fall through
      }
    }
  }

  if (step.pathMatch === "prefix" && step.path.startsWith("/e-approval/submissions/")) {
    const detail = findSubmissionDetailPath();
    if (detail) {
      return `${detail}?${params.toString()}`;
    }
  }

  const base = step.entryPath ?? (step.pathMatch === "prefix" ? step.path.replace(/\/$/, "") : step.path);
  return `${base}?${params.toString()}`;
}

function clearTourSearch(searchParams: URLSearchParams): URLSearchParams {
  const next = new URLSearchParams(searchParams.toString());
  next.delete(LIVE_TOUR_QUERY);
  next.delete(LIVE_TOUR_STEP_QUERY);
  next.delete(LIVE_TOUR_CHAPTER_QUERY);
  return next;
}

function stepChapterId(step: LiveTourStep): LiveTourChapterId {
  return step.chapter ?? chapterForEApprovalStepId(step.id);
}

function panelPositionForHole(hole: { top: number; left: number; width: number; height: number }): {
  top: number;
  left: number;
} {
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  const gap = 14;
  const panelW = Math.min(PANEL_WIDTH, vw - 32);
  const isNarrow = vw < 768;

  // Phones: dock the coach card to the bottom so the sidebar sheet spotlight stays visible.
  if (isNarrow) {
    return {
      top: Math.max(16, vh - PANEL_EST_HEIGHT - 20),
      left: Math.max(16, (vw - panelW) / 2),
    };
  }

  const spaceBelow = vh - (hole.top + hole.height);
  const spaceAbove = hole.top;

  // Prefer above the target when it sits in the lower part of the viewport (sticky footers / Decide actions).
  const placeAbove = spaceBelow < PANEL_EST_HEIGHT + gap || hole.top > vh * 0.45;

  let top = placeAbove
    ? hole.top - PANEL_EST_HEIGHT - gap
    : hole.top + hole.height + gap;

  // Keep the panel near the spotlight — avoid parking it at the opposite edge of the screen.
  if (placeAbove) {
    top = Math.min(top, hole.top - gap - 48);
    top = Math.max(16, top);
    if (top + PANEL_EST_HEIGHT + gap > hole.top) {
      top = Math.max(16, hole.top - PANEL_EST_HEIGHT - gap);
    }
  } else {
    top = Math.max(16, Math.min(top, vh - PANEL_EST_HEIGHT - 16));
  }
  top = Math.max(16, Math.min(top, vh - PANEL_EST_HEIGHT - 16));

  // Prefer left of target when near the right edge so primary actions stay visible.
  let left = hole.left;
  if (hole.left + panelW > vw - 16) {
    left = Math.max(16, hole.left + hole.width - panelW);
  }
  if (left + panelW > vw - 16) {
    left = Math.max(16, vw - panelW - 16);
  }

  // If still overlapping the hole horizontally and vertically, nudge left or above.
  const overlaps =
    left < hole.left + hole.width &&
    left + panelW > hole.left &&
    top < hole.top + hole.height &&
    top + PANEL_EST_HEIGHT > hole.top;
  if (overlaps) {
    const leftCandidate = hole.left - panelW - gap;
    if (leftCandidate >= 16) {
      left = leftCandidate;
    } else if (spaceAbove >= PANEL_EST_HEIGHT + gap) {
      top = Math.max(16, hole.top - PANEL_EST_HEIGHT - gap);
    }
  }

  return { top, left };
}

function findTourScrollRoot(): HTMLElement | null {
  if (typeof document === "undefined") {
    return null;
  }
  return (
    document.querySelector<HTMLElement>("main.overflow-y-auto") ??
    document.querySelector<HTMLElement>("main.scrollbar-hide") ??
    document.scrollingElement
  );
}

function absorbOutsideClick(event: MouseEvent | PointerEvent) {
  // Block page buttons/links/cards under the dim — do not end the tour.
  event.preventDefault();
  event.stopPropagation();
}

export function LiveProductTour() {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();
  const tourId = searchParams.get(LIVE_TOUR_QUERY);
  const stepParam = searchParams.get(LIVE_TOUR_STEP_QUERY);
  const chapterParam = searchParams.get(LIVE_TOUR_CHAPTER_QUERY);
  const isMobile = useIsMobile();
  const { setOpenMobile, openMobile } = useSidebar();
  const canApprove = usePermission([permissions.eApprovalApprove]);
  const canCreate = usePermission([permissions.eApprovalSubmissionsCreate]);
  const capabilities = useMemo(
    () => ({ canApprove, canCreate }),
    [canApprove, canCreate],
  );

  const tour = useMemo(
    () => resolveLiveTour(tourId, capabilities),
    [capabilities, tourId],
  );
  const activeChapterId = useMemo(
    () => (isLiveTourChapterId(chapterParam) && chapterParam !== "complete" ? chapterParam : null),
    [chapterParam],
  );
  const stepIndex = useMemo(() => {
    if (!tour || stepParam == null) {
      return 0;
    }
    const parsed = Number.parseInt(stepParam, 10);
    if (!Number.isFinite(parsed) || parsed < 0) {
      return 0;
    }
    return Math.min(parsed, tour.steps.length - 1);
  }, [tour, stepParam]);

  const chapterBounds = useMemo(() => {
    if (!tour || !activeChapterId) {
      return null;
    }
    const chapterSteps = tour.steps
      .map((entry, index) => ({ entry, index }))
      .filter(({ entry }) => stepChapterId(entry) === activeChapterId);
    if (chapterSteps.length === 0) {
      return null;
    }
    return {
      startIndex: chapterSteps[0]!.index,
      endIndex: chapterSteps[chapterSteps.length - 1]!.index,
    };
  }, [activeChapterId, tour]);

  const step = tour?.steps[stepIndex] ?? null;
  const chapterProgress = useMemo(
    () => (tour ? getLiveTourChapterProgress(tour.steps, stepIndex) : null),
    [tour, stepIndex],
  );
  const [rect, setRect] = useState<TargetRect | null>(null);
  const [missing, setMissing] = useState(false);
  const [spotlightTarget, setSpotlightTarget] = useState<string | null>(null);

  // Mobile: sidebar links live in a closed Sheet — open it for nav steps, close otherwise.
  useEffect(() => {
    if (!tour || !step) {
      return;
    }
    if (!isMobile) {
      return;
    }
    if (isSidebarNavTarget(step.target)) {
      setOpenMobile(true);
      return;
    }
    setOpenMobile(false);
  }, [isMobile, setOpenMobile, step, tour]);

  const returnToVisualGuide = useCallback(() => {
    if (isMobile) {
      setOpenMobile(false);
    }
    if (tourId) {
      const { user, activeTenantId } = useAuthStore.getState();
      dismissLiveTourPrompt(tourId, user?.id ?? null, activeTenantId);
    }
    router.replace(E_APPROVAL_VISUAL_GUIDE_PATH);
  }, [isMobile, router, setOpenMobile, tourId]);

  const endTour = useCallback(() => {
    if (isMobile) {
      setOpenMobile(false);
    }
    if (activeChapterId) {
      returnToVisualGuide();
      return;
    }
    if (tourId) {
      const { user, activeTenantId } = useAuthStore.getState();
      dismissLiveTourPrompt(tourId, user?.id ?? null, activeTenantId);
    }
    const nextPath = eApprovalTourExitPath(pathname);
    const next = clearTourSearch(searchParams);
    const qs = next.toString();
    router.replace(qs ? `${nextPath}?${qs}` : nextPath);
  }, [
    activeChapterId,
    isMobile,
    pathname,
    returnToVisualGuide,
    router,
    searchParams,
    setOpenMobile,
    tourId,
  ]);

  const finishChapter = useCallback(() => {
    if (!activeChapterId) {
      endTour();
      return;
    }
    const { user, activeTenantId } = useAuthStore.getState();
    markEApprovalTourChapterComplete(activeChapterId, user?.id ?? null, activeTenantId);
    returnToVisualGuide();
  }, [activeChapterId, endTour, returnToVisualGuide]);

  const finishFullTour = useCallback(() => {
    if (!tour) {
      endTour();
      return;
    }
    const { user, activeTenantId } = useAuthStore.getState();
    const seen = new Set<LiveTourChapterId>();
    for (const entry of tour.steps) {
      const chapterId = stepChapterId(entry);
      if (chapterId !== "complete") {
        seen.add(chapterId);
      }
    }
    for (const chapterId of seen) {
      markEApprovalTourChapterComplete(chapterId, user?.id ?? null, activeTenantId);
    }
    endTour();
  }, [endTour, tour]);

  const goToStep = useCallback(
    (index: number) => {
      if (!tour) {
        return;
      }
      if (chapterBounds && index > chapterBounds.endIndex) {
        finishChapter();
        return;
      }
      const minIndex = chapterBounds?.startIndex ?? 0;
      const maxIndex = chapterBounds?.endIndex ?? tour.steps.length - 1;
      const clamped = Math.max(minIndex, Math.min(index, maxIndex));
      const nextStep = tour.steps[clamped];
      if (!nextStep) {
        if (activeChapterId) {
          finishChapter();
        } else {
          endTour();
        }
        return;
      }
      router.push(resolveStepHref(nextStep, tour.id, clamped, pathname, searchParams));
    },
    [
      activeChapterId,
      chapterBounds,
      endTour,
      finishChapter,
      pathname,
      router,
      searchParams,
      tour,
    ],
  );

  const measureTarget = useCallback(() => {
    if (!step) {
      setRect(null);
      setMissing(false);
      setSpotlightTarget(null);
      return;
    }
    if (!pathMatchesTourStep(pathname, step)) {
      setRect(null);
      setMissing(true);
      setSpotlightTarget(step.target);
      return;
    }

    let targetId = step.target;
    let next = readTargetRect(targetId);

    // While the mobile Sheet animates open, spotlight the menu button until the nav item exists.
    if (!next && isMobile && isSidebarNavTarget(step.target)) {
      targetId = SIDEBAR_TRIGGER_HELP;
      next = readTargetRect(SIDEBAR_TRIGGER_HELP);
    }

    setSpotlightTarget(targetId);
    setRect(next);
    setMissing(next == null);
  }, [isMobile, pathname, step]);

  const bringTargetIntoView = useCallback(() => {
    if (!step || !pathMatchesTourStep(pathname, step)) {
      return;
    }
    const targetId =
      isMobile && isSidebarNavTarget(step.target) && readTargetRect(step.target) == null
        ? SIDEBAR_TRIGGER_HELP
        : step.target;
    const el = document.querySelector<HTMLElement>(`[data-help="${CSS.escape(targetId)}"]`);
    if (!el) {
      return;
    }
    const targetRect = el.getBoundingClientRect();
    const isDecideAction =
      step.target === "ea-decide-actions" ||
      step.target === "ea-decide-remarks" ||
      step.target === "ea-decide-signature-consent";
    const tall = targetRect.height > window.innerHeight * 0.65;
    const nearBottom = targetRect.top > window.innerHeight * 0.5;
    el.scrollIntoView({
      // Keep Decide actions mid-viewport so the coach panel can sit just above them.
      block: isDecideAction ? "center" : tall ? "start" : nearBottom ? "end" : "center",
      behavior: "smooth",
      inline: "nearest",
    });
  }, [isMobile, pathname, step]);

  // Empty tenant / no forms / no submissions: skip data-dependent steps after settle.
  useEffect(() => {
    if (!tour || !step?.skipIfMissing || !missing) {
      return;
    }
    const timer = window.setTimeout(() => {
      const targetPresent =
        pathMatchesTourStep(pathname, step) && readTargetRect(step.target) != null;
      if (targetPresent) {
        return;
      }
      const atChapterEnd = chapterBounds != null && stepIndex >= chapterBounds.endIndex;
      if (atChapterEnd) {
        finishChapter();
        return;
      }
      if (stepIndex >= tour.steps.length - 1) {
        if (activeChapterId) {
          finishChapter();
        } else {
          endTour();
        }
        return;
      }
      goToStep(stepIndex + 1);
    }, 900);
    return () => window.clearTimeout(timer);
  }, [
    activeChapterId,
    chapterBounds,
    endTour,
    finishChapter,
    goToStep,
    missing,
    pathname,
    step,
    stepIndex,
    tour,
  ]);

  // Skip Draw/Type/Upload capture when a profile signature already loaded on Decide.
  useEffect(() => {
    if (!tour || !step?.skipIfProfileSignature) {
      return;
    }
    let attempts = 0;
    const timer = window.setInterval(() => {
      attempts += 1;
      const flag = document
        .querySelector<HTMLElement>('[data-help="ea-decide-signature"]')
        ?.getAttribute("data-has-profile-signature");
      if (flag === "true") {
        window.clearInterval(timer);
        const atChapterEnd = chapterBounds != null && stepIndex >= chapterBounds.endIndex;
        if (atChapterEnd) {
          finishChapter();
          return;
        }
        if (stepIndex < tour.steps.length - 1) {
          goToStep(stepIndex + 1);
        }
        return;
      }
      if (flag === "false" || attempts >= 10) {
        window.clearInterval(timer);
      }
    }, 200);
    return () => window.clearInterval(timer);
  }, [chapterBounds, finishChapter, goToStep, step, stepIndex, tour]);

  useLayoutEffect(() => {
    if (!tour || !step) {
      return;
    }

    if (!pathMatchesTourStep(pathname, step)) {
      const onEntry =
        step.entryPath != null &&
        (pathname === step.entryPath || pathname.startsWith(`${step.entryPath}/`));
      if (onEntry && (step.autoNavFrom || step.pathMatch === "prefix")) {
        if (step.autoNavFrom) {
          const el = document.querySelector<HTMLElement>(`[data-help="${CSS.escape(step.autoNavFrom)}"]`);
          const nav = el?.getAttribute("data-tour-nav");
          if (nav) {
            router.replace(resolveStepHref(step, tour.id, stepIndex, pathname, searchParams));
            return;
          }
        }
        if (step.pathMatch === "prefix" && step.path.startsWith("/e-approval/submissions/")) {
          const detail = findSubmissionDetailPath();
          if (detail) {
            router.replace(resolveStepHref(step, tour.id, stepIndex, pathname, searchParams));
            return;
          }
        }
        setRect(null);
        setMissing(true);
        return;
      }

      router.replace(resolveStepHref(step, tour.id, stepIndex, pathname, searchParams));
      return;
    }

    if (step.query) {
      let needsSync = false;
      for (const [key, value] of Object.entries(step.query)) {
        if (searchParams.get(key) !== value) {
          needsSync = true;
          break;
        }
      }
      if (needsSync) {
        const params = buildTourSearchParams(tour.id, stepIndex, step, searchParams);
        router.replace(`${pathname}?${params.toString()}`);
        return;
      }
    }

    // Scroll into view only when landing on / advancing to this step — not on later scroll events.
    bringTargetIntoView();
    measureTarget();
    // Mobile Sheet needs a beat to mount nav targets after setOpenMobile(true).
    const t1 = window.setTimeout(measureTarget, 120);
    const t2 = window.setTimeout(measureTarget, 320);
    const t3 = window.setTimeout(measureTarget, 700);
    return () => {
      window.clearTimeout(t1);
      window.clearTimeout(t2);
      window.clearTimeout(t3);
    };
  }, [
    bringTargetIntoView,
    measureTarget,
    openMobile,
    pathname,
    router,
    searchParams,
    step,
    stepIndex,
    tour,
  ]);

  useEffect(() => {
    if (!tour || !step) {
      return;
    }
    const onResize = () => measureTarget();
    window.addEventListener("resize", onResize);
    window.addEventListener("scroll", onResize, true);
    return () => {
      window.removeEventListener("resize", onResize);
      window.removeEventListener("scroll", onResize, true);
    };
  }, [measureTarget, step, tour]);

  // Full-screen overlay blocks the page; forward wheel/touch so users can still scroll main content.
  useEffect(() => {
    if (!tour || !step) {
      return;
    }

    let lastTouchY: number | null = null;

    const onWheel = (event: WheelEvent) => {
      const target = event.target;
      if (target instanceof Element && target.closest("[data-tour-panel]")) {
        return;
      }
      const root = findTourScrollRoot();
      if (!root) {
        return;
      }
      event.preventDefault();
      root.scrollTop += event.deltaY;
      measureTarget();
    };

    const onTouchStart = (event: TouchEvent) => {
      if (event.target instanceof Element && event.target.closest("[data-tour-panel]")) {
        lastTouchY = null;
        return;
      }
      lastTouchY = event.touches[0]?.clientY ?? null;
    };

    const onTouchMove = (event: TouchEvent) => {
      if (lastTouchY == null) {
        return;
      }
      const root = findTourScrollRoot();
      if (!root) {
        return;
      }
      const y = event.touches[0]?.clientY;
      if (y == null) {
        return;
      }
      const delta = lastTouchY - y;
      lastTouchY = y;
      if (Math.abs(delta) < 1) {
        return;
      }
      event.preventDefault();
      root.scrollTop += delta;
      measureTarget();
    };

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.target instanceof Element && event.target.closest("[data-tour-panel]")) {
        return;
      }
      const root = findTourScrollRoot();
      if (!root) {
        return;
      }
      const page = Math.max(160, Math.floor(root.clientHeight * 0.85));
      if (event.key === "ArrowDown" || event.key === "PageDown" || event.key === " ") {
        event.preventDefault();
        root.scrollTop += event.key === "ArrowDown" ? 64 : page;
        measureTarget();
      } else if (event.key === "ArrowUp" || event.key === "PageUp") {
        event.preventDefault();
        root.scrollTop -= event.key === "ArrowUp" ? 64 : page;
        measureTarget();
      } else if (event.key === "Home") {
        event.preventDefault();
        root.scrollTop = 0;
        measureTarget();
      } else if (event.key === "End") {
        event.preventDefault();
        root.scrollTop = root.scrollHeight;
        measureTarget();
      }
    };

    window.addEventListener("wheel", onWheel, { passive: false, capture: true });
    window.addEventListener("touchstart", onTouchStart, { passive: true, capture: true });
    window.addEventListener("touchmove", onTouchMove, { passive: false, capture: true });
    window.addEventListener("keydown", onKeyDown, true);
    return () => {
      window.removeEventListener("wheel", onWheel, true);
      window.removeEventListener("touchstart", onTouchStart, true);
      window.removeEventListener("touchmove", onTouchMove, true);
      window.removeEventListener("keydown", onKeyDown, true);
    };
  }, [measureTarget, step, tour]);

  if (!tour || !step) {
    return null;
  }

  const isFirst = chapterBounds ? stepIndex <= chapterBounds.startIndex : stepIndex <= 0;
  const isLast = chapterBounds
    ? stepIndex >= chapterBounds.endIndex
    : stepIndex >= tour.steps.length - 1;
  const includeFooterInSpotlight =
    step.target === "ea-print-attachments" ||
    step.target === "ea-print-approval-footer" ||
    step.target === "ea-print-approval-trail";
  const hole = rect
    ? (() => {
        const pad = spotlightPadForTarget(spotlightTarget ?? step.target, rect);
        const top = Math.max(0, rect.top - pad);
        const left = Math.max(0, rect.left - pad);
        const width = rect.width + pad * 2;
        const naturalHeight = rect.height + pad * 2;
        const roomBelow = Math.max(120, window.innerHeight - top - 16);
        // Attachment stamp steps must keep Approval History footer inside the spotlight.
        // Otherwise hug the control exactly so the red pointer matches the target.
        const height = includeFooterInSpotlight ? roomBelow : naturalHeight;
        return { top, left, width, height, pad };
      })()
    : null;

  // Mobile Sheet is z-50. When it is open for a nav step, keep the tour dim under it and draw the ring on top.
  const mobileSheetAboveTour =
    isMobile &&
    openMobile &&
    isSidebarNavTarget(step.target) &&
    spotlightTarget !== SIDEBAR_TRIGGER_HELP &&
    hole != null;

  const panelStyle = hole
    ? includeFooterInSpotlight
      ? {
          top: 16,
          left: Math.min(
            Math.max(16, hole.left),
            window.innerWidth - Math.min(PANEL_WIDTH, window.innerWidth - 32) - 16,
          ),
        }
      : panelPositionForHole(hole)
    : isMobile
      ? {
          top: Math.max(16, window.innerHeight - PANEL_EST_HEIGHT - 20),
          left: 16,
        }
      : { top: 96, left: 24 };

  const waitingForMobileNav =
    isMobile &&
    isSidebarNavTarget(step.target) &&
    (spotlightTarget === SIDEBAR_TRIGGER_HELP || (missing && !hole));

  const stepBody = waitingForMobileNav
    ? "On a phone, navigation is behind the menu (☰). We open it for you — then E-Approval is highlighted in that menu."
    : step.body;

  const stepMissingHint = waitingForMobileNav
    ? "Opening the sidebar menu… If nothing highlights, tap the menu icon in the top-left, then continue."
    : (step.missingHint ??
      "This control is not on screen (permissions, empty state, or still loading). Continue to the next step.");

  const highlightRing = hole ? (
    <div
      className="pointer-events-none fixed z-[85] rounded-lg ring-2 ring-red-500 print:hidden"
      style={{
        top: hole.top,
        left: hole.left,
        width: hole.width,
        height: hole.height,
      }}
      aria-hidden
    />
  ) : null;

  return (
    <>
      {/* Dim / catcher — under the mobile Sheet (z-50) when highlighting sidebar nav. */}
      <div
        className={cn("fixed inset-0 print:hidden", mobileSheetAboveTour ? "z-[40]" : "z-[80]")}
        onClick={absorbOutsideClick}
        onPointerDown={absorbOutsideClick}
        onMouseDown={absorbOutsideClick}
        aria-hidden
      >
        {mobileSheetAboveTour ? (
          <div className="absolute inset-0 bg-slate-950/40" />
        ) : hole ? (
          <div
            className="absolute rounded-lg"
            style={{
              top: hole.top,
              left: hole.left,
              width: hole.width,
              height: hole.height,
              boxShadow: "0 0 0 9999px rgba(2, 6, 23, 0.55)",
            }}
          />
        ) : (
          <div className="absolute inset-0 bg-slate-950/55" />
        )}
      </div>

      {/* Red highlight pointer — every step, desktop and mobile. */}
      {highlightRing}

      <div
        data-tour-panel
        role="dialog"
        aria-modal="true"
        aria-label={tour.title}
        className={cn(
          "fixed z-[90] w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-border bg-card p-4 shadow-lg print:hidden",
        )}
        style={panelStyle}
        onClick={(event) => event.stopPropagation()}
        onPointerDown={(event) => event.stopPropagation()}
      >
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <p className="text-xs font-medium text-muted-foreground">
              {chapterProgress
                ? `${chapterProgress.chapterLabel} · ${chapterProgress.chapterStep} of ${chapterProgress.chapterTotal}`
                : `${tour.title} · ${stepIndex + 1} of ${tour.steps.length}`}
            </p>
            <h2 className="mt-1 text-base font-medium text-foreground">
              {waitingForMobileNav ? "Open the menu" : step.title}
            </h2>
            {chapterProgress &&
            !activeChapterId &&
            chapterProgress.overallTotal > chapterProgress.chapterTotal ? (
              <p className="mt-0.5 text-[11px] text-muted-foreground/80">
                Overall {chapterProgress.overallStep} of {chapterProgress.overallTotal}
              </p>
            ) : null}
          </div>
          <Button
            type="button"
            size="icon-sm"
            variant="ghost"
            onClick={endTour}
            aria-label={activeChapterId ? "Exit chapter" : "Skip tour"}
          >
            <X className="h-4 w-4" />
          </Button>
        </div>
        <p className="mt-2 text-sm text-muted-foreground">{stepBody}</p>
        {missing ? (
          <p className="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-xs text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100">
            {stepMissingHint}
          </p>
        ) : null}
        <div className="mt-4 flex flex-wrap items-center justify-between gap-2">
          <Button type="button" size="sm" variant="ghost" onClick={endTour}>
            {activeChapterId ? "Back to guide" : "Skip tour"}
          </Button>
          <div className="flex gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              disabled={isFirst}
              onClick={() => goToStep(stepIndex - 1)}
            >
              Back
            </Button>
            {isLast ? (
              <Button
                type="button"
                size="sm"
                onClick={activeChapterId ? finishChapter : finishFullTour}
              >
                {activeChapterId ? "Done" : "Finish tour"}
              </Button>
            ) : (
              <Button type="button" size="sm" onClick={() => goToStep(stepIndex + 1)}>
                Next
              </Button>
            )}
          </div>
        </div>
      </div>
    </>
  );
}
