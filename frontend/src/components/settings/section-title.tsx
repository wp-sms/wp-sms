export type SettingsSectionTitleProps = {
  title: string
  subtitle: string
}

export const SettingsSectionTitle = ({ title, subtitle }: SettingsSectionTitleProps) => {
  return (
    <div className="flex flex-col gap-y-0.5">
      <h3>{title}</h3>
      <p className="text-muted-foreground text-sm">{subtitle}</p>
    </div>
  )
}
