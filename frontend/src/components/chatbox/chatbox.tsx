import { useCallback, useEffect, useState } from 'react'

import { cn } from '@/lib/utils'
import type { ChatboxSettings } from '@/types/chatbox'

type ChatboxProps = {
  settings: Partial<ChatboxSettings>
}

const CHATBOX_VISIBILITY_KEY = 'wpsms_chatbox_visibility'

export const Chatbox = ({ settings }: ChatboxProps) => {
  const [isVisible, setIsVisible] = useState(() => {
    // Initialize chatbox visibility from localStorage
    const savedState = localStorage.getItem(CHATBOX_VISIBILITY_KEY)
    return savedState === 'true'
  })
  const [isOpen, setIsOpen] = useState(false)
  const [isAnimating, setIsAnimating] = useState(false)

  const {
    chatbox_title = 'Chat with Us!',
    chatbox_button_text = 'Talk to Us',
    chatbox_button_position = 'bottom_right',
    chatbox_color = '#F88E40',
    chatbox_text_color = '#FFFFFF',
    chatbox_footer_text = '',
    chatbox_footer_text_color = '#5C5C5C',
    chatbox_footer_link_title = '',
    chatbox_footer_link_url = '',
    chatbox_animation_effect = '',
    chatbox_disable_logo = false,
    chatbox_links_enabled = false,
    chatbox_links_title = 'Quick Links',
    chatbox_team_members = [],
    chatbox_links = [],
  } = settings

  const getContactLink = (member: any) => {
    const { member_contact_type, member_contact_value } = member
    switch (member_contact_type) {
      case 'whatsapp':
        return `https://wa.me/${member_contact_value}`
      case 'call':
        return `tel:${member_contact_value}`
      case 'sms':
        return `sms:${member_contact_value}`
      case 'email':
        return `mailto:${member_contact_value}`
      case 'facebook':
        return `https://m.me/${member_contact_value}`
      case 'telegram':
        return `https://t.me/${member_contact_value}`
      default:
        return '#'
    }
  }

  const getContactIcon = (type: string) => {
    const icons: Record<string, string> = {
      whatsapp:
        'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuNSAxMi41QzkuODEzNyAxMi41IDEyLjUgOS44MTM3MSAxMi41IDYuNUMxMi41IDMuMTg2MjkgOS44MTM3MSAwLjUgNi41IDAuNUMzLjE4NjI5IDAuNSAwLjUgMy4xODYyOSAwLjUgNi41QzAuNSA3LjY4NzUgMC44NTYyNSA4Ljc5Mzc1IDEuNDc1IDkuNzE4NzVMMC45Mzc1IDEyLjA2MjVMMy4zMzc1IDExLjUzNzVDNC4yMjUgMTIuMTEyNSA1LjMxODc1IDEyLjUgNi41IDEyLjVaIiBmaWxsPSJ3aGl0ZSIvPgo8cGF0aCBkPSJNOS41NjI1IDguMDYyNUM5LjM2MjUgOC42NjI1IDguNTYyNSA5LjI2MjUgOC4wNjI1IDkuMzYyNUM3LjY2MjUgOS40NjI1IDcuMTYyNSA5LjU2MjUgNS44NjI1IDguOTYyNUM0Ljc2MjUgOC40NjI1IDMuODYyNSA3LjY2MjUgMy4yNjI1IDYuNjYyNUMyLjk2MjUgNi4yNjI1IDIuNzYyNSA1Ljc2MjUgMi43NjI1IDUuMjYyNUMyLjc2MjUgNC44NjI1IDIuOTYyNSA0LjQ2MjUgMy4yNjI1IDQuMTYyNUMzLjQ2MjUgMy45NjI1IDMuNjYyNSAzLjc2MjUgMy45NjI1IDMuNzYyNUM0LjA2MjUgMy43NjI1IDQuMTYyNSAzLjc2MjUgNC4yNjI1IDMuNzYyNUM0LjM2MjUgMy43NjI1IDQuNDYyNSAzLjc2MjUgNC41NjI1IDQuMDYyNUM0LjY2MjUgNC4yNjI1IDQuOTYyNSA1LjA2MjUgNS4wNjI1IDUuMTYyNUM1LjE2MjUgNS4yNjI1IDUuMTYyNSA1LjM2MjUgNS4wNjI1IDUuNDYyNUM0Ljk2MjUgNS41NjI1IDQuODYyNSA1LjY2MjUgNC43NjI1IDUuNzYyNUM0LjY2MjUgNS44NjI1IDQuNTYyNSA1Ljk2MjUgNC42NjI1IDYuMDYyNUM0Ljk2MjUgNi41NjI1IDUuMzYyNSA3LjA2MjUgNS44NjI1IDcuNTYyNUM2LjM2MjUgOC4wNjI1IDYuODYyNSA4LjQ2MjUgNy4zNjI1IDguNzYyNUM3LjQ2MjUgOC44NjI1IDcuNTYyNSA4Ljc2MjUgNy42NjI1IDguNjYyNUM3Ljc2MjUgOC41NjI1IDcuODYyNSA4LjQ2MjUgNy45NjI1IDguMzYyNUM4LjA2MjUgOC4yNjI1IDguMTYyNSA4LjI2MjUgOC4yNjI1IDguMzYyNUM4LjM2MjUgOC40NjI1IDkuMTYyNSA4Ljc2MjUgOS4zNjI1IDguODYyNUM5LjQ2MjUgOC45NjI1IDkuNTYyNSA4Ljk2MjUgOS41NjI1IDguMDYyNVoiIGZpbGw9IiNGODhFNDAiLz4KPC9zdmc+Cg==',
      call: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEyLjUgOS41VjExLjVDMTIuNSAxMi4wNTIzIDEyLjA1MjMgMTIuNSAxMS41IDEyLjVIOC41QzMuOTc3MTUgMTIuNSAwLjUgOS4wMjI4NSAwLjUgNC41VjEuNUMwLjUgMC45NDc3MTUgMC45NDc3MTUgMC41IDEuNSAwLjVIMy41QzQuMDUyMjkgMC41IDQuNSAwLjk0NzcxNSA0LjUgMS41VjQuNUM0LjUgNS4wNTIyOSA0LjA1MjI5IDUuNSAzLjUgNS41SDIuNUMyLjUgNy43MDkxNCA0LjI5MDg2IDkuNSA2LjUgOS41VjguNUM2LjUgNy45NDc3MSA2Ljk0NzcxIDcuNSA3LjUgNy41SDEwLjVDMTEuMDUyMyA3LjUgMTEuNSA3Ljk0NzcxIDExLjUgOC41VjExLjVDMTEuNSAxMi4wNTIzIDExLjk0NzcgMTIuNSAxMi41IDEyLjVaIiBmaWxsPSJ3aGl0ZSIvPgo8L3N2Zz4K',
      facebook:
        'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuNSAxMi41QzkuODEzNyAxMi41IDEyLjUgOS44MTM3MSAxMi41IDYuNUMxMi41IDMuMTg2MjkgOS44MTM3MSAwLjUgNi41IDAuNUMzLjE4NjI5IDAuNSAwLjUgMy4xODYyOSAwLjUgNi41QzAuNSA5LjUzMTI1IDIuNjgxMjUgMTIuMDMxMiA1LjU2MjUgMTIuNDM3NVY4LjI1SDQuMTI1VjYuNUg1LjU2MjVWNS4xODc1QzUuNTYyNSAzLjc4MTI1IDYuNTMxMjUgMi44NzUgNy44MTI1IDIuODc1QzguNDM3NSAyLjg3NSA5LjA5Mzc1IDMgOS4wOTM3NSAzVjQuNUg4LjM3NUM3LjY1NjI1IDQuNSA3LjQzNzUgNC45MDYyNSA3LjQzNzUgNS4zNDM3NVY2LjVIOS4wMzEyNUw4Ljc1IDguMjVINy40Mzc1VjEyLjQzNzVDMTAuMzE4OCAxMi4wMzEyIDEyLjUgOS41MzEyNSAxMi41IDYuNVoiIGZpbGw9IndoaXRlIi8+Cjwvc3ZnPgo=',
      telegram:
        'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTYuNSAxMi41QzkuODEzNyAxMi41IDEyLjUgOS44MTM3MSAxMi41IDYuNUMxMi41IDMuMTg2MjkgOS44MTM3MSAwLjUgNi41IDAuNUMzLjE4NjI5IDAuNSAwLjUgMy4xODYyOSAwLjUgNi41QzAuNSA5LjgxMzcxIDMuMTg2MjkgMTIuNSA2LjUgMTIuNVoiIGZpbGw9IndoaXRlIi8+CjxwYXRoIGQ9Ik05LjE4NzUgNC4wNjI1TDguMTI1IDkuMzEyNUM4LjA2MjUgOS42MjUgNy44NzUgOS43MTg3NSA3LjYyNSA5LjU5Mzc1TDUuODc1IDguMzEyNUw1LjA2MjUgOS4wOTM3NUM0Ljk2ODc1IDkuMTg3NSA0Ljg3NSA5LjI4MTI1IDQuNjg3NSA5LjI4MTI1TDQuODEyNSA3LjVMNy45Mzc1IDQuNjI1QzguMDYyNSA0LjUgNy45Mzc1IDQuNDM3NSA3Ljc1IDQuNTYyNUw0LjA2MjUgNy4xMjVMMi4zNDM3NSA2LjU2MjVDMi4wMzEyNSA2LjQ2ODc1IDIuMDMxMjUgNi4yNSAyLjQwNjI1IDYuMDkzNzVMOC43NSAzLjU5Mzc1QzkuMDMxMjUgMy41IDkuMjgxMjUgMy42ODc1IDkuMTg3NSA0LjA2MjVaIiBmaWxsPSIjMDA4OENDIi8+Cjwvc3ZnPgo=',
      sms: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEuNSAxMi41VjEuNUMxLjUgMC45NDc3MTUgMS45NDc3MSAwLjUgMi41IDAuNUgxMC41QzExLjA1MjMgMC41IDExLjUgMC45NDc3MTUgMTEuNSAxLjVWOC41QzExLjUgOS4wNTIyOSAxMS4wNTIzIDkuNSAxMC41IDkuNUgzLjVMMS41IDEyLjVaTTQuNSAzLjVINy41TTQuNSA1LjVIOC41IiBzdHJva2U9IndoaXRlIiBzdHJva2UtbGluZWNhcD0icm91bmQiLz4KPC9zdmc+Cg==',
      email:
        'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTMiIGhlaWdodD0iMTMiIHZpZXdCb3g9IjAgMCAxMyAxMyIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTEuNSAzLjVMMC41IDEuNUMwLjUgMC45NDc3MTUgMC45NDc3MTUgMC41IDEuNSAwLjVIMTEuNUMxMi4wNTIzIDAuNSAxMi41IDAuOTQ3NzE1IDEyLjUgMS41TDExLjUgMy41TTEuNSAzLjVWMTEuNUMxLjUgMTIuMDUyMyAxLjk0NzcxIDEyLjUgMi41IDEyLjVIMTAuNUMxMS4wNTIzIDEyLjUgMTEuNSAxMi4wNTIzIDExLjUgMTEuNVYzLjVNMS41IDMuNUw2LjUgNy41TDExLjUgMy41IiBzdHJva2U9IndoaXRlIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz4KPC9zdmc+Cg==',
    }
    return icons[type] || icons.email
  }

  const toggleChatbox = () => {
    if (isOpen) {
      setIsAnimating(true)
      setTimeout(() => {
        setIsOpen(false)
        setIsAnimating(false)
      }, 300)
    } else {
      setIsOpen(true)
      setIsAnimating(true)
      setTimeout(() => setIsAnimating(false), 300)
    }
  }

  const toggleChatboxVisibility = useCallback(() => {
    setIsVisible((prev) => {
      const newVisibility = !prev
      localStorage.setItem(CHATBOX_VISIBILITY_KEY, newVisibility.toString())
      return newVisibility
    })
  }, [])

  useEffect(() => {
    // Expose toggle function to window for preview functionality
    ;(window as any).toggleWpSmsChatbox = toggleChatboxVisibility
    return () => {
      delete (window as any).toggleWpSmsChatbox
    }
  }, [toggleChatboxVisibility])

  if (!isVisible) {
    return null
  }

  const positionClasses = chatbox_button_position === 'bottom_right' ? 'right-8' : 'left-8'

  const animationClasses = {
    fade: isAnimating ? (isOpen ? 'animate-in fade-in duration-500' : 'animate-out fade-out duration-500') : '',
    slide: isAnimating
      ? isOpen
        ? 'animate-in slide-in-from-bottom-[12%] duration-500'
        : 'animate-out slide-out-to-bottom-[12%] duration-500'
      : '',
  }

  return (
    <div
      className={cn('fixed bottom-20 flex items-center justify-center gap-2 z-[9999] font-sans', positionClasses)}
      style={{ fontSize: '16px' }}
    >
      {/* Chatbox Button */}
      <button
        className={cn(
          'relative flex items-center select-none rounded-full py-3.5 px-6 pr-[35px] text-white border-none cursor-pointer z-[10000]',
          'hover:opacity-90 active:scale-95 transition-all'
        )}
        style={{ backgroundColor: chatbox_color, color: chatbox_text_color }}
        onClick={toggleChatbox}
      >
        {/* Arrow */}
        <span
          className={cn(
            'absolute top-1/2 right-2.5 transition-transform duration-300',
            isOpen ? '-translate-y-1/2' : '-translate-y-1/2 rotate-180'
          )}
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" fill="none">
            <path
              stroke={chatbox_text_color || '#FFF'}
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeMiterlimit="10"
              strokeWidth="2"
              d="m11 1.5-5 5-5-5"
            />
          </svg>
        </span>

        {/* Icon */}
        <span className="w-5 h-5 mr-1.5">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 20 20">
            <path
              fill={chatbox_text_color || '#FFF'}
              d="M6 9a.968.968 0 0 0 .713-.288A.964.964 0 0 0 7 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 6 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 5 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 11 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 10 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 9 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 15 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 14 7a.965.965 0 0 0-.712.288A.973.973 0 0 0 13 8c0 .283.096.521.288.713.192.192.43.288.712.287ZM0 20V2C0 1.45.196.98.588.588A1.93 1.93 0 0 1 2 0h16c.55 0 1.021.196 1.413.588.392.392.588.863.587 1.412v12c0 .55-.196 1.021-.587 1.413A1.92 1.92 0 0 1 18 16H4l-4 4Zm3.15-6H18V2H2v13.125L3.15 14Z"
            />
          </svg>
        </span>

        {/* Title */}
        <span className="text-base font-bold">{chatbox_button_text}</span>
      </button>

      {/* Chatbox Content */}
      <div
        className={cn(
          'fixed w-[382px] max-w-[90%] bg-gray-50 dark:bg-gray-950 rounded-lg border border-gray-200 dark:border-gray-800 transition-opacity duration-300 overflow-hidden',
          chatbox_button_position === 'bottom_right' ? 'right-4' : 'left-4',
          'bottom-36',
          isOpen ? 'opacity-100' : 'opacity-0 pointer-events-none',
          chatbox_animation_effect && animationClasses[chatbox_animation_effect as 'fade' | 'slide']
        )}
        style={{ display: isOpen ? 'block' : 'none' }}
      >
        {/* Header */}
        <div
          className="flex justify-between items-center p-5 rounded-t-lg"
          style={{ backgroundColor: chatbox_color, color: chatbox_text_color }}
        >
          <h2 className="m-0 p-0 text-base font-bold" style={{ color: chatbox_text_color }}>
            {chatbox_title}
          </h2>
          <button
            className="bg-transparent p-0 cursor-pointer border-none hover:opacity-80 transition-opacity"
            onClick={toggleChatbox}
          >
            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
              <path
                d="M1 11L6 6L11 11M11 1L5.99905 6L1 1"
                stroke={chatbox_text_color || '#FFF'}
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>
        </div>

        {/* Scrollable Content */}
        <div className="max-h-[54vh] md:max-h-[65vh] overflow-y-auto overflow-x-hidden scrollbar-thin scrollbar-thumb-gray-400 scrollbar-track-gray-300">
          <div className="p-4 flex flex-col gap-4">
            {/* Team Members */}
            {chatbox_team_members && chatbox_team_members.length > 0 && (
              <div className="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                {chatbox_team_members.map((member, index) => {
                  if (!member.member_name || !member.member_role) return null

                  return (
                    <a
                      key={index}
                      href={getContactLink(member)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className={cn(
                        'p-4 flex gap-2 sm:gap-4 items-center no-underline text-gray-700 dark:text-gray-300',
                        'border-t border-gray-200 dark:border-gray-800 first:border-t-0',
                        'hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer transition-colors'
                      )}
                    >
                      <div className="relative">
                        <span
                          className="absolute w-5 h-5 top-0 right-0 flex items-center justify-center rounded-full"
                          style={{ backgroundColor: chatbox_color }}
                        >
                          <img src={getContactIcon(member.member_contact_type)} alt="" className="w-[13px]" />
                        </span>
                        <img
                          className="rounded-full w-14 h-14 object-cover"
                          src={member.member_photo}
                          loading="lazy"
                          width="56"
                          height="56"
                          alt={member.member_name}
                        />
                      </div>
                      <div>
                        <ul className="p-0 m-0 list-none flex flex-col gap-1">
                          <li className="text-sm leading-[16.41px] break-words font-normal">{member.member_role}</li>
                          <li className="text-base leading-[18.75px] capitalize font-bold">{member.member_name}</li>
                          <li className="flex items-center gap-1 text-xs leading-[14.06px]">
                            <span className="inline-block w-3 h-3 rounded-full border-2 border-teal-100 bg-teal-500" />
                            <span>{member.member_availability}</span>
                          </li>
                        </ul>
                      </div>
                    </a>
                  )
                })}
              </div>
            )}

            {/* Resource Links */}
            {chatbox_links_enabled && chatbox_links && chatbox_links.length > 0 && (
              <div className="border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 rounded-lg overflow-hidden">
                <ul className="p-0 m-0 flex flex-col gap-1 list-none">
                  <li className="p-3 text-gray-700 dark:text-gray-300 text-sm font-bold">{chatbox_links_title}</li>
                  {chatbox_links.map((link, index) => {
                    if (!link.link_title) return null

                    return (
                      <li key={index} className="border-t border-gray-200 dark:border-gray-800">
                        <a
                          href={link.link_url}
                          title={link.link_title}
                          className="p-3 flex justify-between items-center no-underline text-gray-700 dark:text-gray-300 text-sm leading-[16.8px] break-words hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                        >
                          {link.link_title}
                          <span>
                            <svg width="6" height="10" viewBox="0 0 6 10" fill="none">
                              <path
                                d="M5 9L1 5L5 1"
                                stroke="#4F7EF6"
                                strokeMiterlimit="10"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              />
                            </svg>
                          </span>
                        </a>
                      </li>
                    )
                  })}
                </ul>
              </div>
            )}
          </div>
        </div>

        {/* Footer */}
        <div className="border-t border-gray-200 dark:border-gray-800 text-gray-700 dark:text-gray-300 leading-[15px]">
          <div className="p-4 pb-2.5 text-xs" style={{ color: chatbox_footer_text_color }}>
            {chatbox_footer_text}
            {chatbox_footer_link_url && chatbox_footer_link_title && (
              <>
                {' '}
                <a href={chatbox_footer_link_url} className="text-blue-500 hover:text-blue-600">
                  {chatbox_footer_link_title}
                </a>
              </>
            )}
          </div>

          {!chatbox_disable_logo && (
            <div className="pt-0 px-4 pb-4 flex uppercase font-semibold text-[9px] leading-[10.5px] tracking-[-0.3px] gap-1 text-gray-600 dark:text-gray-400">
              Powered By{' '}
              <a
                href="https://wp-sms-pro.com/?utm_source=msg-btn&utm_medium=referral"
                target="_blank"
                rel="noopener noreferrer"
                className="text-gray-600 dark:text-gray-400 no-underline hover:text-orange-500 transition-colors"
              >
                <svg width="56" height="9" viewBox="0 0 56 9" fill="none" className="inline">
                  <path
                    fillRule="evenodd"
                    clipRule="evenodd"
                    d="M6.10673 0.303653C6.18666 0.119846 6.37497 0 6.58384 0H17.3486C17.4959 0 17.6363 0.0599556 17.7342 0.164775C17.8322 0.269594 17.8786 0.409293 17.8615 0.548706L16.8829 8.56492C16.8527 8.81293 16.6321 9 16.37 9H5.01803C4.73281 9 4.5016 8.77977 4.5016 8.50811C4.5016 8.23644 4.73281 8.01621 5.01803 8.01621H15.9101L16.6938 1.59682L11.5973 5.35502C11.3917 5.50663 11.0998 5.48901 10.916 5.31389L6.21867 0.839713C6.07097 0.699032 6.02679 0.487461 6.10673 0.303653ZM15.8379 0.983787H7.83061L11.3247 4.31189L15.8379 0.983787ZM1.7615 2.91541C1.7615 2.64374 1.99271 2.42351 2.27793 2.42351H5.99667C6.28188 2.42351 6.5131 2.64374 6.5131 2.91541C6.5131 3.18707 6.28188 3.4073 5.99667 3.4073H2.27793C1.99271 3.4073 1.7615 3.18707 1.7615 2.91541ZM0 4.5932C0 4.32153 0.231213 4.10131 0.516428 4.10131H7.75815C8.04336 4.10131 8.27457 4.32153 8.27457 4.5932C8.27457 4.86486 8.04336 5.08509 7.75815 5.08509H0.516428C0.231213 5.08509 0 4.86486 0 4.5932ZM2.74011 6.45745C2.74011 6.18578 2.97133 5.96555 3.25654 5.96555H9.1282C9.41342 5.96555 9.64463 6.18578 9.64463 6.45745C9.64463 6.72911 9.41342 6.94934 9.1282 6.94934H3.25654C2.97133 6.94934 2.74011 6.72911 2.74011 6.45745Z"
                    fill="currentColor"
                  />
                  <path
                    d="M25.9672 8.56913H24.3553L24.2947 2.33574H24.2705L21.9193 8.56913H20.3075L20.1621 0.396433H21.58L21.5315 6.82608H21.5679L23.9312 0.396433H25.3976L25.4461 6.82608H25.4824L27.7729 0.396433H29.106L25.9672 8.56913ZM34.399 2.38188C34.399 2.84363 34.3303 3.2515 34.1929 3.60548C34.0636 3.9595 33.8778 4.25963 33.6355 4.50588C33.3931 4.74441 33.1022 4.92911 32.7629 5.05996C32.4235 5.1831 32.0478 5.24465 31.6358 5.24465H30.5936L29.9755 8.56913H28.6181L30.1088 0.396433H32.2902C32.573 0.396433 32.8396 0.434908 33.0901 0.511863C33.3486 0.588822 33.5748 0.7081 33.7688 0.869711C33.9627 1.03132 34.1162 1.2391 34.2293 1.49305C34.3424 1.73933 34.399 2.0356 34.399 2.38188ZM33.0658 2.56657C33.0658 2.24337 32.981 1.98942 32.8114 1.80473C32.6498 1.61233 32.3993 1.51614 32.06 1.51614H31.2601L30.7875 4.17112H31.6479C32.0842 4.17112 32.4276 4.02875 32.678 3.74399C32.9366 3.45926 33.0658 3.06681 33.0658 2.56657ZM40.5479 1.89705C40.249 1.54307 39.8612 1.36607 39.3845 1.36607C39.231 1.36607 39.0734 1.39686 38.9118 1.45842C38.7583 1.51229 38.617 1.59694 38.4877 1.71236C38.3665 1.82009 38.2655 1.95865 38.1847 2.12793C38.1039 2.29726 38.0635 2.49346 38.0635 2.71664C38.0635 3.00906 38.1484 3.24765 38.318 3.43234C38.4877 3.60933 38.7139 3.77866 38.9967 3.94024C39.1744 4.04797 39.3522 4.16726 39.5299 4.29807C39.7157 4.42892 39.8814 4.58283 40.0268 4.75982C40.1723 4.92911 40.2894 5.12536 40.3783 5.34853C40.4671 5.564 40.5116 5.81409 40.5116 6.09886C40.5116 6.5375 40.4348 6.9223 40.2813 7.25321C40.1359 7.58412 39.9339 7.86113 39.6753 8.08431C39.4249 8.30748 39.134 8.47676 38.8028 8.5922C38.4715 8.70764 38.12 8.76538 37.7484 8.76538C37.2717 8.76538 36.8314 8.68457 36.4274 8.52295C36.0315 8.35367 35.7245 8.1382 35.5064 7.87654L36.3669 6.90689C36.5284 7.10699 36.7304 7.26857 36.9728 7.39172C37.2152 7.51486 37.4737 7.57641 37.7484 7.57641C38.1362 7.57641 38.4554 7.46482 38.7058 7.24165C38.9644 7.01848 39.0936 6.69913 39.0936 6.28356C39.0936 5.99113 39.0088 5.74099 38.8391 5.53322C38.6775 5.32546 38.4231 5.1215 38.0757 4.92145C37.8979 4.82137 37.7242 4.70978 37.5545 4.58669C37.3848 4.45584 37.2354 4.30962 37.1061 4.14805C36.9768 3.97872 36.8718 3.79017 36.791 3.58241C36.7183 3.36694 36.6819 3.1168 36.6819 2.83208C36.6819 2.43962 36.7506 2.08174 36.888 1.75854C37.0334 1.43534 37.2273 1.15829 37.4697 0.927426C37.7121 0.696556 37.9989 0.519559 38.3301 0.396433C38.6614 0.265606 39.0209 0.200195 39.4087 0.200195C39.845 0.200195 40.2369 0.269454 40.5843 0.407977C40.9317 0.538799 41.2105 0.723492 41.4205 0.962057L40.5479 1.89705ZM48.5864 8.56913H47.2414L48.4168 2.25493H48.3802L45.4963 8.56913H44.3569L43.8357 2.25493H43.7994L42.6481 8.56913H41.3876L42.9025 0.396433H44.757L45.3023 6.41051H45.3506L48.0776 0.396433H50.1015L48.5864 8.56913ZM54.9274 1.89705C54.6287 1.54307 54.2408 1.36607 53.7642 1.36607C53.6107 1.36607 53.4533 1.39686 53.2915 1.45842C53.138 1.51229 52.9967 1.59694 52.8675 1.71236C52.7462 1.82009 52.6453 1.95865 52.5644 2.12793C52.4835 2.29726 52.4431 2.49346 52.4431 2.71664C52.4431 3.00906 52.5279 3.24765 52.6974 3.43234C52.8675 3.60933 53.0936 3.77866 53.3763 3.94024C53.5542 4.04797 53.732 4.16726 53.9094 4.29807C54.0956 4.42892 54.2613 4.58283 54.4065 4.75982C54.5517 4.92911 54.6691 5.12536 54.7578 5.34853C54.8465 5.564 54.8914 5.81409 54.8914 6.09886C54.8914 6.5375 54.8144 6.9223 54.6609 7.25321C54.5156 7.58412 54.3134 7.86113 54.0551 8.08431C53.8046 8.30748 53.5137 8.47676 53.1823 8.5922C52.8509 8.70764 52.4996 8.76538 52.1282 8.76538C51.6512 8.76538 51.2111 8.68457 50.8071 8.52295C50.4114 8.35367 50.1044 8.1382 49.8861 7.87654L50.7467 6.90689C50.908 7.10699 51.1102 7.26857 51.3524 7.39172C51.5946 7.51486 51.8534 7.57641 52.1282 7.57641C52.5157 7.57641 52.8349 7.46482 53.0854 7.24165C53.3441 7.01848 53.4733 6.69913 53.4733 6.28356C53.4733 5.99113 53.3885 5.74099 53.2189 5.53322C53.0571 5.32546 52.8027 5.1215 52.4552 4.92145C52.2774 4.82137 52.1039 4.70978 51.9343 4.58669C51.7647 4.45584 51.6151 4.30962 51.486 4.14805C51.3563 3.97872 51.2516 3.79017 51.1707 3.58241C51.098 3.36694 51.0615 3.1168 51.0615 2.83208C51.0615 2.43962 51.1302 2.08174 51.2676 1.75854C51.4129 1.43534 51.6068 1.15829 51.8495 0.927426C52.0917 0.696556 52.3787 0.519559 52.7096 0.396433C53.041 0.265606 53.4006 0.200195 53.7886 0.200195C54.2247 0.200195 54.6165 0.269454 54.964 0.407977C55.3114 0.538799 55.5902 0.723492 55.8002 0.962057L54.9274 1.89705Z"
                    fill="currentColor"
                  />
                </svg>
              </a>
            </div>
          )}
        </div>

        {/* Arrow Pointer */}
        <span
          className={cn(
            'fixed bg-transparent inline-block border-l-[10px] border-l-transparent border-b-[10px] border-b-transparent border-t-[10px] border-t-gray-200 dark:border-t-gray-800 border-r-[10px] border-r-transparent',
            'bottom-[77px]',
            chatbox_button_position === 'bottom_right' ? 'right-10' : 'left-10'
          )}
        >
          <i className="inline-block absolute -top-2.5 -left-[9px] w-0 h-0 border-l-[9px] border-l-transparent border-b-[9px] border-b-transparent border-t-[9px] border-t-gray-50 dark:border-t-gray-950 border-r-[9px] border-r-transparent" />
        </span>
      </div>
    </div>
  )
}
