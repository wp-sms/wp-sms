import React from 'react'
import {
  Radio,
  Phone,
  MessageSquare,
  Bell,
  Users,
  Puzzle,
  Settings,
  CheckCircle,
  AlertCircle,
  ArrowRight,
  Send,
  Loader2,
  ExternalLink,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { getWpSettings, cn } from '@/lib/utils'

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
  const { gateways = {} } = getWpSettings()

  const [gatewayName] = useSetting('gateway_name', '')
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
    } catch (error) {
      setGatewayStatus({
        status: 'error',
        message: error.message,
      })
    }
    setTesting(false)
  }

  const configItems = [
    {
      title: 'SMS Gateway',
      icon: Radio,
      value: gatewayName || 'Not configured',
      status: gatewayName ? 'configured' : 'pending',
      page: 'gateway',
    },
    {
      title: 'Admin Mobile',
      icon: Phone,
      value: adminMobile || 'Not set',
      status: adminMobile ? 'configured' : 'pending',
      page: 'phone',
    },
    {
      title: 'Message Button',
      icon: MessageSquare,
      value: messageButton === '1' ? 'Enabled' : 'Disabled',
      status: messageButton === '1' ? 'configured' : 'pending',
      page: 'message-button',
    },
  ]

  const quickLinks = [
    { title: 'Notifications', icon: Bell, page: 'notifications' },
    { title: 'Newsletter', icon: Users, page: 'newsletter' },
    { title: 'Integrations', icon: Puzzle, page: 'integrations' },
    { title: 'Advanced', icon: Settings, page: 'advanced' },
  ]

  const isProActive = window.wpSmsSettings?.addons?.pro

  return (
    <div className="wsms-space-y-4">
      {/* Welcome message for new users */}
      {!gatewayName && (
        <Card className="wsms-border-primary/30 wsms-bg-primary/5">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4">
            <span className="wsms-text-[13px]">
              Welcome to WP SMS! Configure your gateway to start sending messages.
            </span>
            <Button size="sm" onClick={() => setCurrentPage('gateway')}>
              Get Started
            </Button>
          </div>
        </Card>
      )}

      {/* Gateway Status */}
      <Card>
        <CardHeader>
          <CardTitle>Gateway Status</CardTitle>
          <CardDescription>Current SMS gateway connection</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className={cn(
                'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded',
                gatewayName ? 'wsms-bg-primary wsms-text-primary-foreground' : 'wsms-bg-muted'
              )}>
                <Radio className="wsms-h-5 wsms-w-5" strokeWidth={1.5} />
              </div>
              <div>
                <p className="wsms-text-[13px] wsms-font-semibold">
                  {gatewayName || 'No Gateway Selected'}
                </p>
                <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mt-0.5">
                  {gatewayName ? (
                    gatewayStatus.status === 'connected' ? (
                      <>
                        <span className="wsms-h-2 wsms-w-2 wsms-rounded-full wsms-bg-success" />
                        <span className="wsms-text-[12px] wsms-text-success">Connected</span>
                        {gatewayStatus.credit && (
                          <span className="wsms-text-[12px] wsms-text-muted-foreground">
                            Credit: {gatewayStatus.credit}
                          </span>
                        )}
                      </>
                    ) : gatewayStatus.status === 'error' ? (
                      <>
                        <AlertCircle className="wsms-h-3 wsms-w-3 wsms-text-destructive" />
                        <span className="wsms-text-[12px] wsms-text-destructive">Connection failed</span>
                      </>
                    ) : (
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">Click to test</span>
                    )
                  ) : (
                    <span className="wsms-text-[12px] wsms-text-muted-foreground">
                      Configure a gateway to send SMS
                    </span>
                  )}
                </div>
              </div>
            </div>

            {gatewayName && (
              <Button
                variant="outline"
                size="sm"
                onClick={testConnection}
                disabled={testing}
              >
                {testing ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" />
                    Testing...
                  </>
                ) : (
                  <>
                    <Send className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                    Test
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
          <CardTitle>Configuration</CardTitle>
          <CardDescription>Quick access to main settings</CardDescription>
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
          <CardTitle>Quick Links</CardTitle>
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
              {Object.keys(gateways || {}).length}+ SMS Gateways Available
            </p>
            <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-0.5">
              Providers from around the world
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={() => setCurrentPage('gateway')}>
            Browse
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
                  Pro
                </span>
                <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">Upgrade to WP SMS Pro</p>
              </div>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                OTP authentication, WooCommerce integration, and more
              </p>
            </div>
            <Button variant="outline" size="sm" asChild>
              <a href="https://wp-sms-pro.com/" target="_blank" rel="noopener noreferrer">
                Learn More
                <ExternalLink className="wsms-h-3 wsms-w-3 wsms-ml-1" />
              </a>
            </Button>
          </div>
        </Card>
      )}
    </div>
  )
}
