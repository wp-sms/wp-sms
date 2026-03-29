import * as React from "react"

import { cn } from "@/lib/utils"

const LTR_TYPES = new Set(["email", "url", "tel", "password", "number"]);

function Input({ className, type, dir, ...props }: React.ComponentProps<"input">) {
  const resolvedDir = dir ?? (LTR_TYPES.has(type) ? "ltr" : undefined);

  return (
    <input
      type={type}
      dir={resolvedDir}
      data-slot="input"
      className={cn(
        "h-9 w-full min-w-0 rounded-md border-2 border-input bg-card px-3 py-1 text-base transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
        "focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/12",
        "aria-invalid:border-destructive aria-invalid:ring-destructive/20",
        className
      )}
      {...props}
    />
  )
}

export { Input }
