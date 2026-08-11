import * as React from "react";

import { fieldControlClassName } from "@/lib/ui/field-control";
import { cn } from "@/lib/utils";

function Textarea({ className, ...props }: React.ComponentProps<"textarea">) {
  return (
    <textarea
      data-slot="textarea"
      className={cn(
        fieldControlClassName,
        "min-h-[6.5rem] resize-y py-2 leading-relaxed md:text-sm",
        className,
      )}
      {...props}
    />
  );
}

export { Textarea };
