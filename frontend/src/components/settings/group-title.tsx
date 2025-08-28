import { RenderIcon } from '@/components/sidebar/render-icon'

export type SettingsGroupTitleProps = {
  label: string
  icon: string
}

export const SettingsGroupTitle: React.FC<SettingsGroupTitleProps> = ({ label, icon }) => {
  return (
    <div className="flex items-center gap-x-2 border border-border p-4 rounded-lg">
      <div className="bg-primary/15 size-10 rounded-lg flex items-center justify-center">
        <RenderIcon iconName={icon} size={20} />
      </div>

      <div className="flex flex-col">
        <h1 className="text-xl">{label}</h1>
        <p className="text-muted-foreground text-sm">Configure your settings and preferences</p>
      </div>
    </div>
  )
}
