/**
 * Compatibility re-export. The single source for status tones now lives in
 * `@/components/ui/status-tone` (DESIGN_SYSTEM.md 17.1). This shim keeps existing
 * `@/lib/ui/status-tone` imports working without duplicating the colour map.
 */
export {
  statusToneClasses,
  statusToneClassName,
  controlledDocumentStatusTone,
  rolloutStatusTone,
  assetStatusTone,
  subscriptionStatusTone,
  accessModeTone,
  planTierTone,
  environmentStatusTone,
  moduleStatusTone,
  ticketingSlaTone,
  type StatusTone,
} from "@/components/ui/status-tone";
