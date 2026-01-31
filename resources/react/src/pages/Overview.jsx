import React from 'react'
import {
  Radio,
  Phone,
  MessageSquare,
  Bell,
  Puzzle,
  Settings,
  CheckCircle,
  AlertCircle,
  ArrowRight,
  Send,
  Loader2,
  ExternalLink,
  Zap,
  Mail,
  Link2,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { SetupProgress } from '@/components/ui/ux-helpers'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { getWpSettings, cn, getGatewayDisplayName, getGatewayLogo, __ } from '@/lib/utils'
import useGatewayRegistry from '@/hooks/useGatewayRegistry'

function StatusRow({ icon: Icon, title, value, status, onClick }) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-center wsms-gap-3 wsms-px-3 wsms-py-2.5 wsms-text-left wsms-transition-colors wsms-rounded',
        'hover:wsms-bg-accent'
      )}
    >
      <div className={cn(
        'wsms-flex wsms-h-8 wsms-w-8 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-rounded',
        status === 'configured' ? 'wsms-bg-success/10' : 'wsms-bg-muted'
      )}>
        <Icon className={cn(
          'wsms-h-4 wsms-w-4',
          status === 'configured' ? 'wsms-text-success' : 'wsms-text-muted-foreground'
        )} strokeWidth={1.5} />
      </div>
      <div className="wsms-flex-1 wsms-min-w-0">
        <p className="wsms-text-[13px] wsms-font-medium">{title}</p>
        <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-truncate">{value}</p>
      </div>
      {status === 'configured' ? (
        <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-success wsms-shrink-0" />
      ) : (
        <ArrowRight className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
      )}
    </button>
  )
}

function QuickLink({ icon: Icon, title, onClick }) {
  return (
    <button
      onClick={onClick}
      className="wsms-flex wsms-flex-col wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded wsms-border wsms-border-border wsms-bg-card hover:wsms-bg-accent wsms-transition-colors"
    >
      <Icon className="wsms-h-5 wsms-w-5 wsms-text-muted-foreground" strokeWidth={1.5} />
      <span className="wsms-text-[12px] wsms-font-medium">{title}</span>
    </button>
  )
}

export default function Overview() {
  const { setCurrentPage, testGatewayConnection } = useSettings()
  const { toast } = useToast()
  const { gateways } = useGatewayRegistry()

  const [gatewayKey] = useSetting('gateway_name', '')
  const gatewayName = getGatewayDisplayName(gatewayKey, gateways)
  const [adminMobile] = useSetting('admin_mobile_number', '')
  const [messageButton] = useSetting('chatbox_message_button', '')

  const [gatewayStatus, setGatewayStatus] = React.useState({ status: 'unknown', credit: null })
  const [testing, setTesting] = React.useState(false)

  const testConnection = async () => {
    setTesting(true)
    try {
      const result = await testGatewayConnection()
      setGatewayStatus({
        status: result.success ? 'connected' : 'error',
        credit: result.credit,
        message: result.error || result.message,
      })
      if (result.success) {
        toast({
          title: __('Connection Successful'),
          description: result.credit ? `${__('Credit:')} ${result.credit}` : __('Gateway is working correctly'),
          variant: 'success',
        })
      } else {
        toast({
          title: __('Connection Failed'),
          description: result.error || __('Could not connect to gateway'),
          variant: 'destructive',
        })
      }
    } catch (error) {
      setGatewayStatus({
        status: 'error',
        message: error.message,
      })
      toast({
        title: __('Connection Failed'),
        description: error.message || __('Could not connect to gateway'),
        variant: 'destructive',
      })
    }
    setTesting(false)
  }

  const configItems = [
    {
      title: __('SMS Gateway'),
      icon: Radio,
      value: gatewayKey ? gatewayName : __('Not configured'),
      status: gatewayKey ? 'configured' : 'pending',
      page: 'gateway',
    },
    {
      title: __('Admin Mobile'),
      icon: Phone,
      value: adminMobile || __('Not set'),
      status: adminMobile ? 'configured' : 'pending',
      page: 'phone',
    },
    {
      title: __('Message Button'),
      icon: MessageSquare,
      value: messageButton === '1' ? __('Enabled') : __('Disabled'),
      status: messageButton === '1' ? 'configured' : 'pending',
      page: 'message-button',
    },
  ]

  const quickLinks = [
    { title: __('Notifications'), icon: Bell, page: 'notifications' },
    { title: __('Newsletter'), icon: Mail, page: 'newsletter' },
    { title: __('Integrations'), icon: Puzzle, page: 'integrations' },
    { title: __('Advanced'), icon: Settings, page: 'advanced' },
  ]

  const isProActive = window.wpSmsSettings?.addons?.pro

  // Setup steps for new users
  const setupSteps = [
    {
      title: __('Configure SMS Gateway'),
      description: gatewayKey ? `${__('Connected to')} ${gatewayName}` : __('Select your SMS provider'),
      completed: !!gatewayKey,
      onClick: () => setCurrentPage('gateway'),
    },
    {
      title: __('Set Admin Mobile Number'),
      description: adminMobile || __('Add your phone for test messages'),
      completed: !!adminMobile,
      onClick: () => setCurrentPage('phone'),
    },
    {
      title: __('Test Your Connection'),
      description: gatewayStatus.status === 'connected' ? __('Gateway is working') : __('Verify credentials work'),
      completed: gatewayStatus.status === 'connected',
      onClick: testConnection,
    },
  ]

  const isNewUser = !gatewayKey
  const setupComplete = setupSteps.every((s) => s.completed)

  return (
    <div className="wsms-space-y-4 wsms-stagger-children">
      {/* Setup Progress for new users */}
      {isNewUser && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Zap className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Welcome to WSMS!')}
            </CardTitle>
            <CardDescription>
              {__('Complete these steps to start sending SMS messages from your WordPress site.')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <SetupProgress steps={setupSteps} />
          </CardContent>
        </Card>
      )}

      {/* Gateway Status */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Gateway Status')}
          </CardTitle>
          <CardDescription>{__('Current SMS gateway connection')}</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              {(() => {
                const gw = gatewayKey ? gateways.find(g => g.slug === gatewayKey) : null
                const logoUrl = gw ? getGatewayLogo(gw) : ''
                const brandColor = gw?.brand_color
                return (
                  <div
                    className={cn(
                      'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded wsms-overflow-hidden',
                      gatewayKey ? 'wsms-bg-primary wsms-text-primary-foreground' : 'wsms-bg-muted'
                    )}
                    style={brandColor && gatewayKey ? { backgroundColor: `${brandColor}20` } : undefined}
                  >
                    {logoUrl ? (
                      <img
                        src={logoUrl}
                        alt=""
                        className="wsms-h-7 wsms-w-7 wsms-object-contain"
                        onError={(e) => {
                          e.target.style.display = 'none'
                          e.target.parentElement.innerHTML = '<svg class="wsms-h-4 wsms-w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
                        }}
                      />
                    ) : (
                      <Radio className="wsms-h-4 wsms-w-4" strokeWidth={1.5} />
                    )}
                  </div>
                )
              })()}
              <div>
                <p className="wsms-text-[13px] wsms-font-semibold">
                  {gatewayKey ? gatewayName : __('No Gateway Selected')}
                </p>
                {(() => {
                  const gw = gatewayKey ? gateways.find(g => g.slug === gatewayKey) : null
                  return gw?.description ? (
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-line-clamp-1 wsms-mt-0.5">{gw.description}</p>
                  ) : null
                })()}
                <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mt-0.5">
                  {gatewayKey ? (
                    gatewayStatus.status === 'connected' ? (
                      <>
                        <span className="wsms-h-2 wsms-w-2 wsms-rounded-full wsms-bg-success" />
                        <span className="wsms-text-[12px] wsms-text-success">{__('Connected')}</span>
                        {gatewayStatus.credit && (
                          <span className="wsms-text-[12px] wsms-text-muted-foreground">
                            {__('Credit:')} {gatewayStatus.credit}
                          </span>
                        )}
                      </>
                    ) : gatewayStatus.status === 'error' ? (
                      <>
                        <AlertCircle className="wsms-h-3 wsms-w-3 wsms-text-destructive" />
                        <span className="wsms-text-[12px] wsms-text-destructive">{__('Connection failed')}</span>
                      </>
                    ) : (
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">{__('Click to test')}</span>
                    )
                  ) : (
                    <span className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Configure a gateway to send SMS')}
                    </span>
                  )}
                </div>
              </div>
            </div>

            {gatewayKey && (
              <Button
                variant="outline"
                size="sm"
                onClick={testConnection}
                disabled={testing}
              >
                {testing ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" />
                    {__('Testing...')}
                  </>
                ) : (
                  <>
                    <Send className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                    {__('Test')}
                  </>
                )}
              </Button>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Configuration Status */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Settings className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Configuration')}
          </CardTitle>
          <CardDescription>{__('Quick access to main settings')}</CardDescription>
        </CardHeader>
        <CardContent className="wsms-p-0">
          <div className="wsms-divide-y wsms-divide-border">
            {configItems.map((item) => (
              <StatusRow
                key={item.page}
                {...item}
                onClick={() => setCurrentPage(item.page)}
              />
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Quick Links */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Link2 className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Quick Links')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="wsms-grid wsms-grid-cols-4 wsms-gap-2">
            {quickLinks.map((link) => (
              <QuickLink
                key={link.page}
                {...link}
                onClick={() => setCurrentPage(link.page)}
              />
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Gateway count */}
      <Card>
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4">
          <div>
            <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
              {(Array.isArray(gateways) ? gateways.length : 0) || '200'}+ {__('SMS Gateways Available')}
            </p>
            <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-0.5">
              {__('Providers from around the world')}
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={() => setCurrentPage('gateway')}>
            {__('Browse')}
            <ArrowRight className="wsms-h-4 wsms-w-4 wsms-ml-1" />
          </Button>
        </div>
      </Card>

      {/* Pro banner */}
      {!isProActive && (
        <Card className="wsms-border-dashed">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4">
            <div>
              <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-1">
                <span className="wsms-text-[10px] wsms-font-medium wsms-uppercase wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-bg-primary/10 wsms-text-primary">
                  {__('Pro')}
                </span>
                <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{__('Upgrade to WSMS Pro')}</p>
              </div>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                {__('OTP authentication, WooCommerce integration, and more')}
              </p>
            </div>
            <Button variant="outline" size="sm" asChild>
              <a href="https://wp-sms-pro.com/" target="_blank" rel="noopener noreferrer">
                {__('Learn More')}
                <ExternalLink className="wsms-h-3 wsms-w-3 wsms-ml-1" />
              </a>
            </Button>
          </div>
        </Card>
      )}
    </div>
  )
}
