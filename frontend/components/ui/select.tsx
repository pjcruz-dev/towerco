import * as React from "react";

import { fieldControlClassName } from "@/lib/ui/field-control";
import { cn } from "@/lib/utils";

/** Native `<select>` for simple admin/filter bars. Prefer `SelectField` for requestor forms. */
function Select({ className, ...props }: React.ComponentProps<"select">) {
  return (
    <select
      data-slot="select"
      className={cn(
        fieldControlClassName,
        "h-9 appearance-none bg-[length:16px_16px] bg-[position:right_0.5rem_center] bg-no-repeat pr-8",
        "bg-[image:url('data:image/svg+xml;charset=utf-8,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2716%27 height=%2716%27 viewBox=%270 0 24 24%27 fill=%27none%27 stroke=%27%2364748b%27 stroke-width=%272%27 stroke-linecap=%27round%27 stroke-linejoin=%27round%27%3E%3Cpath d=%27m6 9 6 6 6-6%27/%3E%3C/svg%3E')]",
        className,
      )}
      {...props}
    />
  );
}

export { Select };
