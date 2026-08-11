export type GateApprovalActingFor = {
  id: string;
  name: string;
};

export function GateApprovalActingLabel({
  actingFor,
  className = "",
}: {
  actingFor: GateApprovalActingFor | null | undefined;
  className?: string;
}) {
  if (!actingFor?.name) {
    return null;
  }

  return (
    <p className={`text-[11px] font-medium text-primary ${className}`.trim()}>
      Acting for {actingFor.name}
    </p>
  );
}
