import type { ReactNode } from "react"
import { ExternalLink } from "lucide-react"
import { Button } from "../ui/button"

interface SettingsPageLayoutProps {
  title: string
  description?: string
  helpUrl?: string
  children: ReactNode
  isProPage?: boolean
  proAddonName?: string
  proLearnMoreUrl?: string
}

export function SettingsPageLayout({
  title,
  description,
  helpUrl,
  children,
  isProPage = false,
  proAddonName,
  proLearnMoreUrl,
}: SettingsPageLayoutProps) {
  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">{title}</h1>
          {description && <p className="text-muted-foreground mt-2">{description}</p>}
        </div>
        {helpUrl && (
          <Button variant="outline" size="sm" asChild>
            <a href={helpUrl} target="_blank" rel="noopener noreferrer">
              Help <ExternalLink className="ml-1 h-3 w-3" />
            </a>
          </Button>
        )}
      </div>

      {/* Pro Notice */}
      {/*{isProPage && proAddonName && <ProNotice addonName={proAddonName} learnMoreUrl={proLearnMoreUrl} />}*/}

      {/* Page Content */}
      <div className="space-y-6">{children}</div>
    </div>
  )
}
