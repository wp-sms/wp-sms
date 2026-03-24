import { cn } from "@/lib/utils"

function Skeleton({ className, ...props }: React.ComponentProps<"div">) {
  return (
    <div
      data-slot="skeleton"
      className={cn("rounded-md bg-gradient-to-r from-accent via-accent/50 to-accent bg-[length:200%_100%] animate-shimmer", className)}
      {...props}
    />
  )
}

export { Skeleton }
