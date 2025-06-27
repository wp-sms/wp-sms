import type { ReactNode } from "react"
import { HelpCircle, Lock } from "lucide-react"
import { Label } from "../ui/label"
import { Badge } from "../ui/badge"
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from "../ui/tooltip"

interface FieldWrapperProps {
  label: string
  description?: string
  tooltip?: string
  isPro?: boolean
  isRequired?: boolean
  isLocked?: boolean
  children: ReactNode
  htmlFor?: string
}

export function FieldWrapper({
  label,
  description,
  tooltip,
  isPro = false,
  isRequired = false,
  isLocked = false,
  children,
  htmlFor,
}: FieldWrapperProps) {
  return (
    <TooltipProvider>
      <div className={`space-y-2 ${isLocked ? "opacity-60" : ""}`}>
        <div className="flex items-center gap-2">
          <Label
            htmlFor={htmlFor}
            className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
          >
            {label}
            {isRequired && <span className="text-destructive ml-1">*</span>}
          </Label>

          {tooltip && (
            <Tooltip>
              <TooltipTrigger asChild>
                <HelpCircle className="h-4 w-4 text-muted-foreground cursor-help" />
              </TooltipTrigger>
              <TooltipContent>
                <p className="max-w-xs">{tooltip}</p>
              </TooltipContent>
            </Tooltip>
          )}

          {isPro && (
            <Tooltip>
              <TooltipTrigger asChild>
                <Badge variant="secondary" className="text-xs bg-orange-100 text-orange-800 hover:bg-orange-200">
                  <Lock className="mr-1 h-3 w-3" />
                  Pro
                </Badge>
              </TooltipTrigger>
              <TooltipContent>
                <p>This feature requires WP SMS Pro</p>
              </TooltipContent>
            </Tooltip>
          )}
        </div>

        <div className={isLocked ? "pointer-events-none" : ""}>{children}</div>

        {description && <p className="text-sm text-muted-foreground leading-relaxed">{description}</p>}
      </div>
    </TooltipProvider>
  )
}
