import { Link, useLocation } from '@tanstack/react-router'
import clsx from 'clsx'
import { Fingerprint } from 'lucide-react'

import { useSidebarStore } from '@/stores/use-sidebar-store'

import { RenderIcon } from '../../lib/render-icon'

export const OTPSidebar = () => {
  const location = useLocation()
  const { isOpen } = useSidebarStore()

  const menuItems = [
    {
      key: 'otp-activity',
      href: '/otp/activity',
      icon: 'Activity',
      title: 'Activity',
    },
    {
      key: 'otp-logs',
      href: '/otp/logs',
      icon: 'Logs',
      title: 'Logs',
    },
    {
      key: 'otp-authentication-channels',
      href: '/otp/authentication-channels',
      icon: 'IdCard',
      title: 'Authentication Channels',
    },
    {
      key: 'otp-branding',
      href: '/otp/branding',
      icon: 'Puzzle',
      title: 'Branding',
    },
    {
      key: 'otp-settings',
      href: '/otp/settings',
      icon: 'Settings',
      title: 'Settings',
    },
  ]

  return (
    <aside
      className={clsx(
        'bg-white p-4 border-r border-r-border overflow-hidsden !transition-all',
        isOpen ? 'w-72' : 'w-auto'
      )}
    >
      <div className="flex flex-col gap-y-10 sticky top-12 transition-all z-10">
        {isOpen && (
          <section className="flex items-center gap-x-2.5">
            <div className="size-10 rounded-lg bg-primary flex items-center justify-center">
              <Fingerprint className="text-white" size={21} />
            </div>

            <div className="flex flex-col">
              <span className="text-gray-900 font-medium">WP SMS</span>
              <span className="text-gray-500">OTP - 2FA</span>
            </div>
          </section>
        )}

        <section className="flex flex-col gap-y-1">
          {menuItems.map((item) => {
            const isActive = location.pathname === item.href

            return (
              <Link key={item.key} className="contents" to={item.href}>
                <div
                  className={clsx(
                    'flex items-center gap-x-2 justify-between py-2 px-3  active:scale-95 rounded-sm transition-all cursor-pointer select-none',
                    isActive ? '' : 'hover:bg-gray-200/85 active:bg-gray-300/85'
                  )}
                >
                  <div className="flex items-center gap-x-2">
                    <RenderIcon
                      iconName={item.icon}
                      strokeWidth={2}
                      size={19}
                      className={clsx('min-w-[19px] min-h-[19px]', isActive ? '!text-primary' : '!text-gray-600')}
                    />

                    {!!isOpen && (
                      <span className={clsx(' font-medium', isActive ? 'text-primary' : 'text-gray-700')}>
                        {item.title}
                      </span>
                    )}
                  </div>
                </div>
              </Link>
            )
          })}
        </section>
      </div>
    </aside>
  )
}
