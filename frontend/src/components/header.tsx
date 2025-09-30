import { Link } from '@tanstack/react-router'
import { CircleQuestionMark, Crown, Inbox, MessageSquarePlus, Puzzle, Send, Settings } from 'lucide-react'

import { Button } from './ui/button'

const navItems = [
  {
    title: 'Send SMS',
    href: '/wp-admin/admin.php?page=wp-sms',
    icon: <MessageSquarePlus />,
    description: 'Compose and send SMS messages',
  },
  {
    title: 'Inbox',
    href: '/wp-admin/admin.php?page=wp-sms-inbox',
    icon: <Inbox />,
    description: 'View received messages',
  },
  {
    title: 'Outbox',
    href: '/wp-admin/admin.php?page=wp-sms-outbox',
    icon: <Send />,
    description: 'View sent messages',
  },
  {
    title: 'Integrations',
    href: '/wp-admin/admin.php?page=wp-sms-integrations',
    icon: <Puzzle />,
    description: 'Manage third-party integrations',
  },
]

export function Header() {
  const linkClasses = '!text-white hover:!bg-white/10 hover:!text-white hover:text !no-underline'

  return (
    <header className="bg-header p-4 flex gap-2">
      <div className="flex gap-1 items-center text-white font-medium italic text-xl">
        <svg width="38" height="20" viewBox="0 0 38 20" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path
            d="M11.2374 18.3689H34.5319L36.54 1.09888M36.54 1.09888H14.4505L24.0895 10.7379L36.54 1.09888ZM5.61465 6.32004H13.2456M2 9.93469H16.8602M19.6716 13.951H7.62279"
            stroke="white"
            strokeWidth="2.11945"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
        WP SMS
      </div>
      <ul className="!ms-auto flex gap-1">
        {navItems.map((item) => (
          <li key={item.title}>
            <Button variant="ghost" className={linkClasses} asChild>
              <a href={item.href}>
                {item.icon}
                {item.title}
              </a>
            </Button>
          </li>
        ))}
        <li></li>
      </ul>
      <Button variant="default" asChild>
        <a href="https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=header" target="_blank">
          <Crown />
          Upgrade
        </a>
      </Button>
      <Button asChild variant="ghost" className={linkClasses}>
        <Link to="/settings/$name" params={{ name: 'general' }}>
          <Settings />
        </Link>
      </Button>
      <Button variant="ghost" className={linkClasses}>
        <CircleQuestionMark />
      </Button>
    </header>
  )
}
