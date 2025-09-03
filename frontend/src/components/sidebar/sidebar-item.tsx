import { Link, useLocation } from '@tanstack/react-router'
import clsx from 'clsx'
import type { PropsWithChildren } from 'react'

import { RenderIcon } from './render-icon'

export type SidebarItemProps = PropsWithChildren<{
  icon?: string
  title: string
  href?: string
  onClick?: () => void
  showTitle?: boolean
  endContent?: React.ReactNode
}>

export const SidebarItem = ({ icon, title, href, onClick, endContent, showTitle = true }: SidebarItemProps) => {
  const location = useLocation()

  const isActive = location.pathname === `/${href}`

  if (!href) {
    return (
      <div
        onClick={onClick}
        className="flex items-center gap-x-2 justify-between py-2 px-3 hover:bg-gray-200/85 active:bg-gray-300/85 active:scale-95 rounded-sm transition-all cursor-pointer select-none"
      >
        <div className="flex items-center gap-x-2">
          {icon && (
            <RenderIcon
              iconName={icon}
              strokeWidth={2}
              size={19}
              className={clsx('min-w-[19px] min-h-[19px]', isActive ? '!text-primary' : '!text-gray-600')}
            />
          )}

          {showTitle && (
            <span className={clsx(' font-medium', isActive ? 'text-primary' : 'text-gray-700')}>{title}</span>
          )}
        </div>

        {endContent && showTitle && endContent}
      </div>
    )
  }

  return (
    <Link className="contents" to={'/$name'} params={{ name: href }}>
      <div
        className={clsx(
          'flex items-center gap-x-2 justify-between py-2 px-3  active:scale-95 rounded-sm transition-all cursor-pointer select-none',
          isActive ? '' : 'hover:bg-gray-200/85 active:bg-gray-300/85'
        )}
      >
        <div className="flex items-center gap-x-2">
          {icon && (
            <RenderIcon
              iconName={icon}
              strokeWidth={2}
              size={19}
              className={clsx('min-w-[19px] min-h-[19px]', isActive ? '!text-primary' : '!text-gray-600')}
            />
          )}

          {showTitle && (
            <span className={clsx(' font-medium', isActive ? 'text-primary' : 'text-gray-700')}>{title}</span>
          )}
        </div>

        {endContent && endContent}
      </div>
    </Link>
  )
}
