import React from 'react'
import { MessageCircle, Inbox, Reply, Zap, Diamond } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useSettings } from '@/context/SettingsContext'

export default function TwoWay() {
  const { isAddonActive } = useSettings()
  const hasTwoWay = isAddonActive('two-way')

  if (!hasTwoWay) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Two-Way Messaging
              <Badge variant="warning">
                <Diamond className="wsms-mr-1 wsms-h-3 wsms-w-3" />
                Add-on Required
              </Badge>
            </CardTitle>
            <CardDescription>
              Enable two-way SMS communication with your users
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-8 wsms-text-center">
              <MessageCircle className="wsms-mx-auto wsms-h-12 wsms-w-12 wsms-text-muted-foreground" />
              <h3 className="wsms-mt-4 wsms-text-lg wsms-font-semibold">
                Two-Way Messaging Add-on Required
              </h3>
              <p className="wsms-mt-2 wsms-text-sm wsms-text-muted-foreground">
                The Two-Way Messaging add-on enables receiving SMS messages, keyword triggers, and auto-replies.
              </p>
              <div className="wsms-mt-6 wsms-flex wsms-justify-center wsms-gap-4">
                <Button>
                  Get Two-Way Add-on
                </Button>
                <Button variant="outline">
                  Learn More
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6">
      {/* Inbox Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Inbox className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Inbox Settings
          </CardTitle>
          <CardDescription>
            Configure how incoming messages are handled
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <p className="wsms-text-sm wsms-text-muted-foreground">
            Two-way messaging settings will appear here when the add-on is active.
          </p>
        </CardContent>
      </Card>

      {/* Keyword Triggers */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Zap className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Keyword Triggers
          </CardTitle>
          <CardDescription>
            Set up automatic responses based on keywords
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="wsms-text-sm wsms-text-muted-foreground">
            Configure keyword triggers in the dedicated section.
          </p>
        </CardContent>
      </Card>

      {/* Auto-Replies */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Reply className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Auto-Replies
          </CardTitle>
          <CardDescription>
            Configure automatic reply messages
          </CardDescription>
        </CardHeader>
        <CardContent>
          <p className="wsms-text-sm wsms-text-muted-foreground">
            Set up auto-reply messages in the dedicated section.
          </p>
        </CardContent>
      </Card>
    </div>
  )
}
