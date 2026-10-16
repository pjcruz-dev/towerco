import { StatusBadge } from "@/components/ui/status-badge";
import { assetStatusTone } from "@/components/ui/status-tone";

function labelize(value: string): string {
  return value.replaceAll("_", " ");
}

export function AssetStatusBadge({ status }: { status: string }) {
  return (
    <StatusBadge tone={assetStatusTone(status)} className="capitalize">
      {labelize(status)}
    </StatusBadge>
  );
}
