import { Link, useLocation } from '@tanstack/react-router'
import { PanelLeftOpen } from 'lucide-react'

import {
  Breadcrumb,
  BreadcrumbEllipsis,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Button } from '@/components/ui/button'
import { useSidebarStore } from '@/stores/use-sidebar-store'

import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '../ui/dropdown-menu'

type HeaderProps = {
  currentPage: string
}

export const Header = ({ currentPage }: HeaderProps) => {
  const { toggleSidebar } = useSidebarStore()
  const { pathname } = useLocation()

  return (
    <header className="border-b border-b-border p-3 sticky top-8 bg-white z-10">
      <div className="flex items-center gap-x-4">
        <Button onClick={toggleSidebar} size="icon" variant="ghost" className="cursor-pointer">
          <PanelLeftOpen className="text-foreground/85" size={22} />
        </Button>

        <div className="w-[1px] h-5 bg-border rotate-180" />

        <Breadcrumb>
          <BreadcrumbList className="!list-none !ml-1">
            <BreadcrumbItem>
              <BreadcrumbPage>WP SMS</BreadcrumbPage>
            </BreadcrumbItem>

            <BreadcrumbSeparator />

            <BreadcrumbItem>
              <DropdownMenu>
                <DropdownMenuTrigger className="flex items-center gap-1">
                  <BreadcrumbEllipsis className="size-4" />
                  <span className="sr-only">Toggle menu</span>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start">
                  <DropdownMenuItem asChild>
                    <Link to="/settings/$name" params={{ name: 'general' }}>
                      Settings
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild>
                    <Link to="/otp/activity">OTP/2FA</Link>
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </BreadcrumbItem>

            <BreadcrumbSeparator />

            {pathname.includes('/otp') ? (
              <BreadcrumbItem>
                <BreadcrumbPage className="capitalize">OTP/2FA</BreadcrumbPage>
              </BreadcrumbItem>
            ) : pathname.includes('/settings') ? (
              <BreadcrumbItem>
                <BreadcrumbPage className="capitalize">Settings</BreadcrumbPage>
              </BreadcrumbItem>
            ) : null}

            <BreadcrumbSeparator />

            <BreadcrumbItem>
              <BreadcrumbPage className="capitalize">{currentPage.replace(/[_-]/g, ' ')}</BreadcrumbPage>
            </BreadcrumbItem>
          </BreadcrumbList>
        </Breadcrumb>
      </div>
    </header>
  )
}
