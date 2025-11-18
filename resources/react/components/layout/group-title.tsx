import { __ } from '@wordpress/i18n'

export type GroupTitleProps = {
  label: string
}

export const GroupTitle = ({ label }: GroupTitleProps) => {
  return (
    <div className="flex flex-col gap-1">
      <h1 className="text-2xl font-bold tracking-tight">{label}</h1>
      <p className="text-base text-muted-foreground">{__('Configure your settings and preferences', 'wp-sms')}</p>
    </div>
  )
}
