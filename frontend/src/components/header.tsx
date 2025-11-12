import { Link, useLocation } from '@tanstack/react-router'
import { CircleQuestionMark, Crown, Inbox, MessageSquarePlus, Puzzle, Send, Settings } from 'lucide-react'

import { useLayoutData } from '@/hooks/use-layout-data'

import { Button } from './ui/button'

// Icon mapping for dynamic icon rendering
const iconMap: Record<string, React.ReactNode> = {
  MessageSquarePlus: <MessageSquarePlus />,
  Inbox: <Inbox />,
  Send: <Send />,
  Puzzle: <Puzzle />,
  Crown: <Crown />,
  Settings: <Settings />,
  CircleQuestionMark: <CircleQuestionMark />,
}

export function Header() {
  const linkClasses = '!text-white hover:!bg-white/10 hover:!text-white hover:text !no-underline'
  const location = useLocation()
  const { header: headerItems } = useLayoutData()

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
        {headerItems.map((item) => {
          const isUpgrade = item.icon === 'Crown'

          if (item.isExternal) {
            return (
              <li key={item.title}>
                <Button variant={isUpgrade ? 'default' : 'ghost'} className={isUpgrade ? '' : linkClasses} asChild>
                  <a href={item.url} target="_blank" rel="noopener noreferrer">
                    {iconMap[item.icon]}
                    {item.title}
                  </a>
                </Button>
              </li>
            )
          }

          return (
            <li key={item.title}>
              <Button variant="ghost" className={linkClasses} asChild>
                <a href={item.url}>
                  {iconMap[item.icon]}
                  {item.title}
                </a>
              </Button>
            </li>
          )
        })}
      </ul>
      {!location.pathname.includes('settings') && (
        <Button asChild variant="ghost" className={linkClasses}>
          <Link to="/settings/$name" params={{ name: 'general' }}>
            <Settings />
          </Link>
        </Button>
      )}
    </header>
  )
}
