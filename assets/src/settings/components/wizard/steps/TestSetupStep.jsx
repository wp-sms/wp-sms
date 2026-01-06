import React, { useState } from 'react'
import { Send, Loader2, CheckCircle, XCircle, Phone, RotateCcw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { wizardApi } from '@/api/wizardApi'
import { __ } from '@/lib/utils'

/**
 * Test Setup step - Send a test SMS to verify configuration
 */
export default function TestSetupStep({
  phoneNumber,
  onTestComplete,
}) {
  const [sending, setSending] = useState(false)
  const [sent, setSent] = useState(false)
  const [error, setError] = useState('')
  const [confirmed, setConfirmed] = useState(null)

  const handleSendTestSms = async () => {
    setSending(true)
    setError('')

    try {
      await wizardApi.sendTestSms(phoneNumber)
      setSent(true)
    } catch (err) {
      setError(err.message || __('Failed to send test SMS'))
    }

    setSending(false)
  }

  const handleConfirmReceived = (received) => {
    setConfirmed(received)
    onTestComplete?.(received)
  }

  const handleTryAgain = () => {
    setSent(false)
    setConfirmed(null)
    setError('')
  }

  return (
    <div className="wsms-max-w-lg wsms-mx-auto">
      {/* Header */}
      <div className="wsms-text-center wsms-mb-6">
        <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
          {__('Test Your Setup')}
        </h2>
        <p className="wsms-text-[12px] wsms-text-muted-foreground">
          {__('Send a test message to verify everything works.')}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Send Test SMS')}
          </CardTitle>
          <CardDescription>{__('A test message will be sent to your phone.')}</CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {/* Phone Number */}
          <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
            <Phone className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
            <div>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Sending to')}</p>
              <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground wsms-font-mono">
                {phoneNumber || __('No number')}
              </p>
            </div>
          </div>

          {/* Initial State - Send Button */}
          {!sent && !confirmed && (
            <div className="wsms-space-y-3">
              <Button
                onClick={handleSendTestSms}
                disabled={sending || !phoneNumber}
                className="wsms-w-full"
              >
                {sending ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                    {__('Sending...')}
                  </>
                ) : (
                  <>
                    <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                    {__('Send Test SMS')}
                  </>
                )}
              </Button>

              {error && (
                <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded-md wsms-bg-destructive/5 wsms-border wsms-border-destructive/20">
                  <XCircle className="wsms-h-4 wsms-w-4 wsms-text-destructive wsms-shrink-0" />
                  <p className="wsms-text-[12px] wsms-text-destructive">{error}</p>
                </div>
              )}
            </div>
          )}

          {/* Sent - Confirmation */}
          {sent && confirmed === null && (
            <div className="wsms-space-y-4">
              <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded-md wsms-bg-primary/5 wsms-border wsms-border-primary/20">
                <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                <p className="wsms-text-[12px] wsms-text-foreground">{__('Test SMS sent! Did you receive it?')}</p>
              </div>
              <div className="wsms-flex wsms-gap-2">
                <Button onClick={() => handleConfirmReceived(true)} className="wsms-flex-1">
                  <CheckCircle className="wsms-h-4 wsms-w-4 wsms-mr-1.5" />
                  {__('Yes')}
                </Button>
                <Button onClick={() => handleConfirmReceived(false)} variant="outline" className="wsms-flex-1">
                  <XCircle className="wsms-h-4 wsms-w-4 wsms-mr-1.5" />
                  {__('No')}
                </Button>
              </div>
            </div>
          )}

          {/* Success */}
          {confirmed === true && (
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-md wsms-bg-success/5 wsms-border wsms-border-success/20">
              <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" />
              <div>
                <p className="wsms-text-[13px] wsms-font-medium wsms-text-success">{__('Perfect!')}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Your gateway is working correctly.')}</p>
              </div>
            </div>
          )}

          {/* Failed */}
          {confirmed === false && (
            <div className="wsms-space-y-3">
              <div className="wsms-p-3 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground wsms-mb-2">{__('Troubleshooting:')}</p>
                <ul className="wsms-text-[11px] wsms-text-muted-foreground wsms-space-y-1 wsms-list-disc wsms-list-inside">
                  <li>{__('Check phone signal')}</li>
                  <li>{__('Verify phone number')}</li>
                  <li>{__('Wait a few minutes')}</li>
                  <li>{__('Check gateway account')}</li>
                </ul>
              </div>
              <div className="wsms-flex wsms-gap-2">
                <Button onClick={handleTryAgain} variant="outline" className="wsms-flex-1">
                  <RotateCcw className="wsms-h-4 wsms-w-4 wsms-mr-1.5" />
                  {__('Try Again')}
                </Button>
                <Button onClick={() => onTestComplete?.(true)} variant="ghost" className="wsms-flex-1">
                  {__('Skip')}
                </Button>
              </div>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
