import { __ } from '@wordpress/i18n'
import React, { useState } from 'react'
import { createPortal } from 'react-dom'
import { Info, Sparkles, LifeBuoy, BadgeCheck, MapPin, Radio, ShieldCheck, ShoppingCart, MessagesSquare, CalendarClock, Plug, Check, Minus, ExternalLink, Send } from 'lucide-react'
import { cn } from '@/lib/utils'
import GreenSeal from '@/components/shared/GreenSeal'

const HIDE_KEY = 'wsms_hide_all_in_one_compare'

const pricingUrl = (campaign) =>
  `https://wsms.io/pricing/?utm_source=wp-sms&utm_medium=plugin&utm_campaign=${campaign}`

// What All-in-One adds. Each row is a separate add-on plugin, so nothing in the free plugin is locked.
const ROWS = [
  { key: 'gateways', icon: Radio, info: __('The free plugin works with 200+ gateways. Pro adds more, 300+ in total.', 'wp-sms'), title: __('Number of gateways', 'wp-sms'), freeText: '200+', aioText: '300+' },
  { key: 'outbox', info: __('See every message you sent, with its delivery status.', 'wp-sms'), icon: Send, title: __('Outbox and delivery log', 'wp-sms'), free: true },
  { key: 'button', info: __('A chat button on your site that lets visitors message you.', 'wp-sms'), icon: Send, title: __('Message button for your site', 'wp-sms'), free: true },
  { key: 'notify', info: __('Get an SMS when something happens in WordPress, like a new post, comment or user.', 'wp-sms'), icon: Send, title: __('SMS notifications for WordPress events', 'wp-sms'), free: true },
  { key: 'send', info: __('Send single and bulk SMS, manage subscribers and groups. Works with 200+ gateways.', 'wp-sms'), icon: Send, title: __('Send SMS, subscribers and groups', 'wp-sms'), free: true },
  { key: 'login', info: __('Pro add-on. Let visitors log in with an SMS code, add 2FA for admins, and verify phone numbers with one-time passcodes.', 'wp-sms'), icon: ShieldCheck, title: __('SMS login, 2FA and one-time passcodes (OTP)', 'wp-sms'), free: false },
  { key: 'schedule', info: __('Pro add-on. Send a message later, or repeat it on a schedule.', 'wp-sms'), icon: CalendarClock, title: __('Scheduled and repeating SMS', 'wp-sms'), free: false },
  { key: 'woo', info: __('WooCommerce Pro add-on. Remind shoppers about abandoned carts and verify phone numbers at checkout.', 'wp-sms'), icon: ShoppingCart, title: __('Abandoned cart SMS and OTP at checkout', 'wp-sms'), free: false },
  { key: 'twoway', info: __('Two-Way add-on. Receive replies in an SMS inbox inside WordPress and forward them to email or phone.', 'wp-sms'), icon: MessagesSquare, title: __('Two-way SMS inbox', 'wp-sms'), free: false },
  { key: 'plugins', info: __('Four add-ons: Booking, Elementor, Fluent (CRM, Forms, Support) and Membership. Send an SMS when something happens in those plugins.', 'wp-sms'), icon: Plug, title: __('Booking, Elementor, Fluent and Membership alerts', 'wp-sms'), free: false },
  { key: 'support', icon: LifeBuoy, info: __('All-in-One licenses get priority support through the support center, with a first reply within 48 hours. Free plugin users can use the contact form.', 'wp-sms'), title: __('Priority support', 'wp-sms'), freeText: __('Contact form', 'wp-sms'), aioText: __('Premium Support', 'wp-sms') },
]

// Emoji for each row, so the table is easy to scan
const EMOJI = { gateways: '📡', send: '💌', login: '🔐', schedule: '⏰', woo: '🛒', twoway: '💬', plugins: '🔌', support: '🎧', refund: '💸' }
const HIGHLIGHT_EMOJI = { login: '🔐', woo: '🛒', twoway: '💬' }

const HIGHLIGHTS = [
  { key: 'login', icon: ShieldCheck, tag: __('Pro', 'wp-sms'), title: __('Secure your logins', 'wp-sms'), text: __('SMS login, 2FA and one-time passcodes (OTP) for WordPress.', 'wp-sms'), tone: 'wsms-bg-emerald-500/10 wsms-text-emerald-700' },
  { key: 'woo', icon: ShoppingCart, tag: __('WooCommerce Pro', 'wp-sms'), title: __('Recover lost sales', 'wp-sms'), text: __('Abandoned cart reminders and OTP verification at checkout.', 'wp-sms'), tone: 'wsms-bg-orange-500/10 wsms-text-orange-700' },
  { key: 'twoway', icon: MessagesSquare, tag: __('Two-Way', 'wp-sms'), title: __('Talk back to customers', 'wp-sms'), text: __('Receive replies in an SMS inbox and forward them to email.', 'wp-sms'), tone: 'wsms-bg-sky-500/10 wsms-text-sky-700' },
]

function useHidden() {
  const [hidden, setHidden] = useState(() => {
    try { return window.localStorage.getItem(HIDE_KEY) === '1' } catch (e) { return false }
  })
  const hide = () => {
    setHidden(true)
    try { window.localStorage.setItem(HIDE_KEY, '1') } catch (e) { /* hiding only lasts for this visit */ }
  }
  return [hidden, hide]
}

/**
 * Free vs All-in-One. Only for sites without All-in-One, on the plugin's own screens.
 * `compact` is the short version for the setup wizard.
 */
export default function AllInOneCompare({ compact = false, showHide = false, preview: forcePreview = false, campaign = 'compare' }) {
  const [hidden, hide] = useHidden()
  const [tip, setTip] = useState(null)
  const showTip = (e, text) => {
    const r = e.currentTarget.getBoundingClientRect()
    // Inside the setup wizard the page sits in a modal, so the tip must live inside that modal to show above it.
    const host = e.currentTarget.closest('[role="dialog"]') || document.getElementById('wpsms-settings-root') || document.body
    const hr = host.getBoundingClientRect()
    const inDialog = host.getAttribute('role') === 'dialog'
    setTip({ text, x: r.left + r.width / 2 - (inDialog ? hr.left : 0), y: r.top - (inDialog ? hr.top : 0), host, fixed: !inDialog })
  }

  // Add &aio-preview=1 to the URL to see it on a site that has All-in-One or add-ons.
  const preview = forcePreview || new URLSearchParams(window.location.search).has('aio-preview')
  if (!preview && window.wpSmsSettings?.license?.isPremium) return null

  return (
    <section
      aria-label={__('Free compared with All-in-One', 'wp-sms')}
      className="wsms-overflow-hidden wsms-rounded-2xl wsms-border wsms-border-border wsms-bg-card"
    >
      <div className={cn('wsms-relative wsms-overflow-hidden', compact ? 'wsms-px-5 wsms-pb-2 wsms-pt-4' : 'wsms-px-6 wsms-pb-3 wsms-pt-6')} style={{ background: 'linear-gradient(180deg, rgba(234,150,90,.14), rgba(234,150,90,0))' }}>
        <div className="wsms-relative wsms-flex wsms-flex-wrap wsms-items-start wsms-justify-between wsms-gap-3">
          <div className="wsms-min-w-0">
            <h3 className={cn('wsms-font-bold wsms-text-foreground', compact ? 'wsms-text-[17px]' : 'wsms-text-[20px]')}>{__('Go further with All-in-One', 'wp-sms')}</h3>
            {!compact && (
              <p className="wsms-mt-1 wsms-text-[13.5px] wsms-text-muted-foreground">
                {__('You have WSMS free today, and it stays fully free. One All-in-One license adds every add-on, priority support and a 14-day money-back guarantee. Prefer one feature? Each add-on can be bought on its own.', 'wp-sms')}
              </p>
            )}
          </div>
          {showHide && <button
            type="button"
            onClick={hide}
            className="wsms-relative wsms-text-[12px] wsms-text-muted-foreground wsms-underline wsms-underline-offset-2 hover:wsms-text-foreground"
          >
            {__('Hide this', 'wp-sms')}
          </button>}
        </div>


        {!compact && (
          <div className="wsms-mt-4 wsms-grid wsms-grid-cols-1 wsms-divide-y wsms-divide-border wsms-overflow-hidden wsms-rounded-xl wsms-border wsms-border-border wsms-bg-background md:wsms-grid-cols-3 md:wsms-divide-x md:wsms-divide-y-0 rtl:md:wsms-divide-x-reverse">
            {HIGHLIGHTS.map((h) => (
              <div key={h.key} className="wsms-flex wsms-items-start wsms-gap-3 wsms-px-4 wsms-py-3.5">
                <span aria-hidden="true" className="wsms-text-[26px] wsms-leading-none">{HIGHLIGHT_EMOJI[h.key]}</span>
                <div className="wsms-min-w-0">
                  <div className="wsms-text-[15px] wsms-font-bold wsms-text-foreground">{h.title}</div>
                  <p className="wsms-mt-0.5 wsms-text-[14px] wsms-leading-snug wsms-text-muted-foreground">{h.text}</p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className={cn('wsms-overflow-x-auto', compact ? 'wsms-px-5 wsms-pt-2' : 'wsms-px-6 wsms-pt-4')}>
        <table className="wsms-w-full wsms-border-collapse wsms-text-start">
          <thead>
            <tr className="wsms-text-[12px] wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
              <th className="wsms-py-2 wsms-text-start wsms-font-semibold">{__('Feature', 'wp-sms')}</th>
              <th className="wsms-w-[190px] wsms-whitespace-nowrap wsms-py-2 wsms-text-center wsms-font-semibold">{__('Free', 'wp-sms')} <span className="wsms-font-bold wsms-normal-case wsms-tracking-normal wsms-text-primary">{__('(you are here)', 'wp-sms')}</span></th>
              <th className="wsms-w-[190px] wsms-rounded-t-lg wsms-bg-primary/10 wsms-py-2 wsms-text-center wsms-font-semibold wsms-text-primary">{__('All-in-One', 'wp-sms')}</th>
            </tr>
          </thead>
          <tbody>
            {(compact ? ROWS.filter((r) => !['send', 'plugins', 'outbox', 'button', 'notify'].includes(r.key)) : ROWS).map((row, idx, arr) => {
              const Icon = row.icon
              return (
                <tr key={row.key} className="wsms-border-t wsms-border-border">
                  <td className={cn('wsms-py-[0.375rem]', 'wsms-pe-3')}>
                    <span className="wsms-flex wsms-items-center wsms-gap-2.5 wsms-text-[13.5px] wsms-text-foreground">
                      <span>{row.title}</span>
                      <span
                        tabIndex={0}
                        role="img"
                        aria-label={row.info}
                        className="wsms-inline-flex wsms-cursor-help wsms-text-muted-foreground hover:wsms-text-foreground focus:wsms-text-foreground"
                        onMouseEnter={(e) => showTip(e, row.info)}
                        onMouseLeave={() => setTip(null)}
                        onFocus={(e) => showTip(e, row.info)}
                        onBlur={() => setTip(null)}
                      >
                        <Info className="wsms-h-4 wsms-w-4" />
                      </span>
                    </span>
                  </td>
                  <td className={cn('wsms-py-[0.375rem]', 'wsms-text-center')}>
                    {row.freeText ? <span className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{row.freeText}</span> : row.free
                      ? <Check className="wsms-mx-auto wsms-h-4 wsms-w-4 wsms-text-emerald-600" aria-label={__('Included', 'wp-sms')} />
                      : <Minus className="wsms-mx-auto wsms-h-4 wsms-w-4 wsms-text-muted-foreground/50" aria-label={__('Not included', 'wp-sms')} />}
                  </td>
                  <td className={cn('wsms-py-[0.375rem]', 'wsms-bg-primary/10 wsms-text-center', idx === arr.length - 1 && 'wsms-rounded-b-lg')}>
                    {row.aioText ? <span className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-font-semibold wsms-whitespace-nowrap wsms-text-emerald-700 dark:wsms-text-emerald-400">{row.key === 'support' && <GreenSeal className="wsms-h-4 wsms-w-4" />}{row.aioText}</span> : <Check className="wsms-mx-auto wsms-h-4 wsms-w-4 wsms-text-emerald-600" aria-label={__('Included', 'wp-sms')} />}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>

      <div className={compact ? 'wsms-mt-2' : 'wsms-mt-4'}>
        <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-justify-between wsms-gap-3 wsms-border-t wsms-border-emerald-200 wsms-bg-emerald-50 wsms-px-5 wsms-py-[19px] dark:wsms-border-emerald-500/30 dark:wsms-bg-emerald-500/10">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <ShieldCheck className="wsms-h-8 wsms-w-8 wsms-shrink-0 wsms-text-emerald-600" strokeWidth={1.75} />
            <div>
              <div className="wsms-text-[15px] wsms-font-bold wsms-text-emerald-800 dark:wsms-text-emerald-300">{__('14-Day Money Back Guarantee', 'wp-sms')}</div>
              <div className="wsms-text-[13px] wsms-text-emerald-700 dark:wsms-text-emerald-400">{__('All 7 add-ons with one license. Full refund if not satisfied.', 'wp-sms')}</div>
            </div>
          </div>
          <a
            href={pricingUrl(campaign)}
            target="_blank"
            rel="noopener noreferrer"
            className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-rounded-lg wsms-bg-primary wsms-px-4 wsms-py-2 wsms-text-[14px] wsms-font-semibold wsms-text-primary-foreground wsms-no-underline hover:wsms-bg-primary/90"
          >
            {__('See All-in-One', 'wp-sms')}
            <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
          </a>
        </div>
      </div>
      {tip && createPortal(
        <div
          role="tooltip"
          className="wsms-pointer-events-none wsms-z-[2147483647] wsms-max-w-[280px] wsms-rounded-lg wsms-bg-foreground wsms-px-3 wsms-py-2 wsms-text-[12.5px] wsms-leading-snug wsms-text-background wsms-shadow-lg"
          style={{ position: tip.fixed ? 'fixed' : 'absolute', left: tip.x, top: tip.y - 8, transform: 'translate(-50%, -100%)' }}
        >
          {tip.text}
        </div>,
        tip.host
      )}
    </section>
  )
}
