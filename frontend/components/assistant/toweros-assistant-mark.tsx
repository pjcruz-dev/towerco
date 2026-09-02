import { cn } from "@/lib/utils";

type Props = {
  className?: string;
  title?: string;
};

/**
 * Compact tower + signal mark for Ask TowerOS / AI Assistant (telecom brand).
 */
export function TowerOsAssistantMark({ className, title = "TowerOS Assistant" }: Props) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={cn("shrink-0", className)}
      aria-hidden={title ? undefined : true}
      role={title ? "img" : undefined}
    >
      {title ? <title>{title}</title> : null}
      {/* Signal arcs */}
      <path
        d="M8.2 6.4c1.9-1.7 5.7-1.7 7.6 0"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        opacity="0.45"
      />
      <path
        d="M9.4 8.2c1.3-1.1 3.9-1.1 5.2 0"
        stroke="currentColor"
        strokeWidth="1.6"
        strokeLinecap="round"
        opacity="0.75"
      />
      {/* Antenna tip */}
      <circle cx="12" cy="4.2" r="1.15" fill="currentColor" />
      <path d="M12 5.4V9.2" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
      {/* Tower lattice */}
      <path
        d="M10.2 9.2h3.6L15.8 20.2H8.2L10.2 9.2Z"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinejoin="round"
      />
      <path d="M10.6 13h2.8M10 16.2h4" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
      <path d="M12 9.2v11" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" opacity="0.85" />
      {/* Base */}
      <path d="M7 20.2h10" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
    </svg>
  );
}
