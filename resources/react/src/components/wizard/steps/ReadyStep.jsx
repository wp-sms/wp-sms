import React, { useState } from 'react'
import { PartyPopper, Send, Bell, Settings, ArrowRight, CheckCircle, Sparkles, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { __, getGatewayDisplayName as getGatewayName } from '@/lib/utils'
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
      title: __('Send Your First SMS'),
      description: __('Start sending messages right away'),
      primary: true,
    },
    {
      id: 'notifications',
      icon: Bell,
      title: __('Configure Notifications'),
      description: __('Set up automatic SMS alerts'),
      primary: false,
    },
    {
      id: 'gateway',
      icon: Settings,
      title: __('Explore Settings'),
      description: __('Fine-tune your SMS configuration'),
      primary: false,
    },
  ]

  return (
    <div className="wsms-max-w-2xl wsms-mx-auto">
      {/* Header with Success Animation */}
      <div className="wsms-text-center wsms-mb-8">
        <div className="wsms-flex wsms-justify-center wsms-mb-4">
          <div className="wsms-relative">
            <div className="wsms-h-16 wsms-w-16 wsms-rounded-2xl wsms-bg-success/10 wsms-flex wsms-items-center wsms-justify-center">
              <PartyPopper className="wsms-h-8 wsms-w-8 wsms-text-success" />
            </div>
            <div className="wsms-absolute wsms--right-1 wsms--top-1 wsms-h-7 wsms-w-7 wsms-rounded-full wsms-bg-success wsms-flex wsms-items-center wsms-justify-center wsms-shadow-lg wsms-shadow-success/30">
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-white" />
            </div>
          </div>
        </div>
        <h2 className="wsms-text-xl wsms-font-bold wsms-text-foreground wsms-mb-2">
          {__("You're All Set!")}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground">
          {__('Your SMS gateway is configured and ready to use.')}
        </p>
      </div>

      {/* Gateway Summary */}
      {gatewayName && (
        <div className="wsms-flex wsms-justify-center wsms-mb-8">
          <div className="wsms-inline-flex wsms-items-center wsms-gap-2 wsms-px-4 wsms-py-2.5 wsms-rounded-full wsms-bg-success/10 wsms-border wsms-border-success/20">
            <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-success" />
            <span className="wsms-text-[13px] wsms-font-medium wsms-text-success">
              {__('Connected to')} {getGatewayDisplayName()}
            </span>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <div className="wsms-space-y-3 wsms-mb-8">
        <p className="wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide wsms-text-center wsms-mb-4">
          {__('What would you like to do next?')}
        </p>
        {quickActions.map((action) => {
          const Icon = action.icon
          return (
            <button
              key={action.id}
              type="button"
              onClick={() => handleComplete(action.id)}
              disabled={isCompleting}
              className={`wsms-w-full wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-xl wsms-border wsms-transition-all wsms-shadow-sm hover:wsms-shadow-md disabled:wsms-opacity-50 disabled:wsms-cursor-not-allowed ${
                action.primary
                  ? 'wsms-border-primary/30 wsms-bg-primary/5 hover:wsms-bg-primary/10'
                  : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
              }`}
            >
              <div
                className={`wsms-flex wsms-h-11 wsms-w-11 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-rounded-xl ${
                  action.primary ? 'wsms-bg-primary/10' : 'wsms-bg-muted'
                }`}
              >
                <Icon
                  className={`wsms-h-5 wsms-w-5 ${
                    action.primary ? 'wsms-text-primary' : 'wsms-text-muted-foreground'
                  }`}
                />
              </div>
              <div className="wsms-flex-1 wsms-text-left">
                <p
                  className={`wsms-text-[13px] wsms-font-semibold ${
                    action.primary ? 'wsms-text-primary' : 'wsms-text-foreground'
                  }`}
                >
                  {action.title}
                </p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">
                  {action.description}
                </p>
              </div>
              <ArrowRight
                className={`wsms-h-4 wsms-w-4 ${
                  action.primary ? 'wsms-text-primary' : 'wsms-text-muted-foreground'
                }`}
              />
            </button>
          )
        })}
      </div>

      {/* Close Button */}
      <div className="wsms-text-center">
        <Button
          variant="outline"
          onClick={() => handleComplete()}
          size="lg"
          disabled={isCompleting}
        >
          {isCompleting ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
              {__('Completing...')}
            </>
          ) : (
            __('Close Setup Wizard')
          )}
        </Button>
      </div>
    </div>
  )
}
