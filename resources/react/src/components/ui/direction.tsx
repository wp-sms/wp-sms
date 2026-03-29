import { Direction } from "radix-ui"
import { useIsRtl } from "@/hooks/use-is-rtl"

export function DirectionProvider({ children }: { children: React.ReactNode }) {
  const isRtl = useIsRtl()

  return (
    <Direction.DirectionProvider dir={isRtl ? "rtl" : "ltr"}>
      {children}
    </Direction.DirectionProvider>
  )
}
