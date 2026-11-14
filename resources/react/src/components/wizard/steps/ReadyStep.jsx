import { __ } from '@wordpress/i18n'
import React, { useState } from 'react'
import { PartyPopper, Send, Bell, Settings, ArrowRight, CheckCircle, Sparkles, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getGatewayDisplayName as getGatewayName } from '@/lib/utils'
import useGatewayRegistry from '@/hooks/useGatewayRegistry'

/**
 * Ready step - Setup complete, show next actions
 */
export default function ReadyStep({
  gatewayName,
  onNavigate,
  onClose,
}) {
  const { gateways } = useGatewayRegistry()
  const [isCompleting, setIsCompleting] = useState(false)

  // Handle complete - wraps onClose with loading state
  const handleComplete = async (navigateTo = null) => {
    setIsCompleting(true)
    await onClose(navigateTo)
    // Note: page will reload, so no need to reset state
  }

  const getGatewayDisplayName = () => getGatewayName(gatewayName, gateways)

  const quickActions = [
    {
      id: 'send-sms',
      icon: Send,
      title: __('Send Your First SMS', 'wp-sms'),
      description: __('Start sending messages right away', 'wp-sms'),
      primary: true,
    },
    {
      id: 'notifications',
      icon: Bell,
      title: __('Configure Notifications', 'wp-sms'),
      description: __('Set up automatic SMS alerts', 'wp-sms'),
      primary: false,
    },
    {
      id: 'gateway',
      icon: Settings,
      title: __('Explore Settings', 'wp-sms'),
      description: __('Fine-tune your SMS configuration', 'wp-sms'),
      primary: false,
    },
  ]

  const helpLinks = window.wpSmsSettings?.brandingUrls || {}

  return (
    <div className="wsms-mx-auto wsms-max-w-4xl wsms-py-1">
      {/* Success hero */}
      <div className="wsms-text-center">
        <div className="wsms-relative wsms-mx-auto wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-2xl wsms-bg-success/10">
          <PartyPopper className="wsms-h-8 wsms-w-8 wsms-text-success" />
          <span className="wsms-absolute wsms--end-2 wsms--top-2 wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-success wsms-shadow-lg wsms-shadow-success/30">
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-white" />
          </span>
        </div>
        <h2 className="wsms-mt-3 wsms-text-[24px] wsms-font-extrabold wsms-tracking-tight wsms-text-foreground">{__("You're All Set!", 'wp-sms')}</h2>
        <p className="wsms-mt-1.5 wsms-text-[15px] wsms-text-muted-foreground">{__('Your SMS gateway is configured and ready to use.', 'wp-sms')}</p>
      </div>

      {/* Next steps */}
      <div className="wsms-mt-6 wsms-grid wsms-grid-cols-1 wsms-gap-3 md:wsms-grid-cols-3">
        {quickActions.map((action) => {
          const Icon = action.icon
          return (
            <button
              key={action.id}
              type="button"
              onClick={() => handleComplete(action.id)}
              disabled={isCompleting}
              className={`wsms-group wsms-flex wsms-flex-col wsms-items-start wsms-gap-3 wsms-rounded-2xl wsms-border wsms-p-4 wsms-text-start wsms-shadow-sm wsms-transition-all hover:wsms--translate-y-0.5 hover:wsms-shadow-md disabled:wsms-opacity-50 disabled:wsms-cursor-not-allowed ${
                action.primary
                  ? 'wsms-border-primary/30 wsms-bg-primary/5 hover:wsms-bg-primary/10'
                  : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
              }`}
            >
              <span className={`wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-xl ${action.primary ? 'wsms-bg-primary/10' : 'wsms-bg-muted'}`}>
                <Icon className={`wsms-h-6 wsms-w-6 ${action.primary ? 'wsms-text-primary' : 'wsms-text-muted-foreground'}`} />
              </span>
              <span className="wsms-flex-1">
                <span className={`wsms-block wsms-text-[16px] wsms-font-semibold ${action.primary ? 'wsms-text-primary' : 'wsms-text-foreground'}`}>{action.title}</span>
                <span className="wsms-mt-1 wsms-block wsms-text-[13px] wsms-leading-relaxed wsms-text-muted-foreground">{action.description}</span>
              </span>
              <span className={`wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-font-semibold ${action.primary ? 'wsms-text-primary' : 'wsms-text-foreground'}`}>
                {__('Open', 'wp-sms')}
                <ArrowRight className="wsms-h-4 wsms-w-4 rtl:wsms-scale-x-[-1]" />
              </span>
            </button>
          )
        })}
      </div>

      {/* Help and close */}
      <div className="wsms-mt-5 wsms-flex wsms-flex-wrap wsms-items-center wsms-justify-between wsms-gap-3 wsms-border-t wsms-border-border wsms-pt-4">
        <p className="wsms-text-[13px] wsms-text-muted-foreground">
          {__('Need a hand?', 'wp-sms')}{' '}
          <a href={helpLinks.docs || 'https://wsms.io/docs/'} target="_blank" rel="noopener noreferrer" className="wsms-font-medium wsms-text-primary hover:wsms-underline">{__('Read the documentation', 'wp-sms')}</a>
          {' '}{__('or', 'wp-sms')}{' '}
          <a href={helpLinks.support || 'https://wsms.io/support/'} target="_blank" rel="noopener noreferrer" className="wsms-font-medium wsms-text-primary hover:wsms-underline">{__('contact support', 'wp-sms')}</a>.
        </p>
        <Button variant="outline" onClick={() => handleComplete()} disabled={isCompleting}>
          {isCompleting ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-2 wsms-animate-spin" />
              {__('Completing...', 'wp-sms')}
            </>
          ) : (
            __('Close Setup Wizard', 'wp-sms')
          )}
        </Button>
      </div>
    </div>
  )
}
