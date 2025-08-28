import type { PropsWithChildren } from 'react'

export type SidebarGroupProps = PropsWithChildren<{
  title: string
  showTitle?: boolean
}>

export const SidebarGroup = ({ title, children, showTitle = true }: SidebarGroupProps) => {
  return (
    <section>
      {showTitle && <strong>{title}</strong>}

      <div className="flex flex-col gap-y-1 mt-3">{children}</div>
    </section>
  )
}
