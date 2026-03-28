import { __ } from '@wordpress/i18n';
import { formatDateTime } from '@/lib/format';
import type { MessageLogEntry } from '@/lib/api';
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { StatusBadge, ChannelBadge } from '@/components/messaging/message-badges';
import { formatLabel } from '@/lib/constants';

interface MessageLogDetailPanelProps {
  log: MessageLogEntry | null;
  onClose: () => void;
}

function Field({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <span className="text-xs text-muted-foreground">{label}</span>
      <p className="text-sm">{value ?? '\u2014'}</p>
    </div>
  );
}

export function MessageLogDetailPanel({ log, onClose }: MessageLogDetailPanelProps) {
  return (
    <Drawer open={log !== null} onOpenChange={(o) => !o && onClose()}>
      <DrawerContent className="sm:max-w-md overflow-y-auto">
        {log && (
          <>
            <DrawerHeader className="border-b">
              <div className="flex items-center justify-between">
                <DrawerTitle>{__('Message Details', 'wp-sms')}</DrawerTitle>
                <div className="flex items-center gap-2">
                  <ChannelBadge channel={log.channel} />
                  <StatusBadge status={log.status} />
                </div>
              </div>
            </DrawerHeader>

            <div className="p-4 space-y-5">
              {/* Recipient */}
              <div>
                <span className="text-xs text-muted-foreground">{__('Recipient', 'wp-sms')}</span>
                <p className="text-lg font-mono font-medium">{log.recipient}</p>
              </div>

              {/* Error callout */}
              {log.error && (
                <Alert variant="destructive">
                  <AlertDescription>{log.error}</AlertDescription>
                </Alert>
              )}

              {/* Content section */}
              {(log.subject || log.body_preview) && (
                <>
                  <Separator />
                  <section>
                    <h3 className="text-xs font-medium text-muted-foreground mb-2">{__('Content', 'wp-sms')}</h3>
                    {log.subject && (
                      <p className="text-sm font-semibold mb-1">{log.subject}</p>
                    )}
                    {log.body_preview && (
                      <div className="rounded-md border p-3 text-sm whitespace-pre-wrap">
                        {log.body_preview}
                      </div>
                    )}
                  </section>
                </>
              )}

              {/* Delivery section */}
              <Separator />
              <section>
                <h3 className="text-xs font-medium text-muted-foreground mb-2">{__('Delivery', 'wp-sms')}</h3>
                <div className="grid grid-cols-2 gap-3">
                  <Field label="Type" value={log.type ? formatLabel(log.type) : null} />
                  <Field label="Gateway" value={log.gateway_id} />
                  <Field label="Cost" value={log.cost} />
                  <div>
                    <span className="text-xs text-muted-foreground">{__('Provider ID', 'wp-sms')}</span>
                    <p className="text-sm font-mono truncate" title={log.provider_id ?? undefined}>
                      {log.provider_id ?? '\u2014'}
                    </p>
                  </div>
                  <Field label="Created" value={formatDateTime(log.created_at)} />
                  <Field label="Sent At" value={log.sent_at ? formatDateTime(log.sent_at) : null} />
                  <Field label="Delivered At" value={log.delivered_at ? formatDateTime(log.delivered_at) : null} />
                </div>
              </section>

              {/* Flow source */}
              {log.execution_id && (
                <>
                  <Separator />
                  <section>
                    <h3 className="text-xs font-medium text-muted-foreground mb-2">{__('Source', 'wp-sms')}</h3>
                    <p className="text-sm font-mono">{log.execution_id}</p>
                  </section>
                </>
              )}
            </div>
          </>
        )}
      </DrawerContent>
    </Drawer>
  );
}
