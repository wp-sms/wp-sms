import { __, sprintf } from '@wordpress/i18n';
import { ArrowRight, Radio, Workflow, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { channelLabel, channelBadgeVariant } from '@/lib/channel';
import type { DashboardSummaryResponse, DashboardGateway } from '@/lib/api';

function GatewayRow({ gateway }: { gateway: DashboardGateway }) {
  return (
    <div className="flex items-center justify-between gap-2 py-1.5">
      <div className="flex items-center gap-2 min-w-0">
        <span className={cn(
          'h-2 w-2 shrink-0 rounded-full',
          gateway.healthy ? 'bg-success' : 'bg-destructive',
        )} />
        <span className="text-sm font-medium truncate">{gateway.name}</span>
      </div>
      <div className="flex items-center gap-1.5 shrink-0">
        {gateway.channels.map(ch => (
          <Badge key={ch} variant={channelBadgeVariant(ch)} className="text-[10px]">
            {channelLabel(ch)}
          </Badge>
        ))}
      </div>
    </div>
  );
}

interface PlatformStatusCardProps {
  data: DashboardSummaryResponse;
  onNavigate: (section: string) => void;
}

export function PlatformStatusCard({ data, onNavigate }: PlatformStatusCardProps) {
  const { gateways, flows } = data;
  const hasGateways = gateways.length > 0;
  const hasFlows = flows.active > 0 || flows.paused > 0 || flows.draft > 0;
  const totalExecutions = flows.executions_completed + flows.executions_failed;

  return (
    <Card className="animate-fade-up">
      <CardHeader>
        <CardTitle className="text-base">{__('Platform Status', 'wp-sms')}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid gap-6 sm:grid-cols-2">
          {/* Gateways */}
          <div>
            <div className="flex items-center gap-1.5 mb-3">
              <Radio className="size-3.5 text-muted-foreground" />
              <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                {__('Gateways', 'wp-sms')}
              </span>
            </div>
            {hasGateways ? (
              <div className="space-y-0.5">
                {gateways.map(gw => (
                  <GatewayRow key={gw.id} gateway={gw} />
                ))}
              </div>
            ) : (
              <div className="py-3 text-center">
                <p className="text-sm text-muted-foreground">{__('No gateways configured.', 'wp-sms')}</p>
                <Button variant="outline" size="sm" className="mt-2" onClick={() => onNavigate('channels/gateways')}>
                  {__('Configure Gateway', 'wp-sms')}
                </Button>
              </div>
            )}
          </div>

          {/* Flows */}
          <div>
            <div className="flex items-center gap-1.5 mb-3">
              <Workflow className="size-3.5 text-muted-foreground" />
              <span className="text-xs font-medium text-muted-foreground uppercase tracking-wider">
                {__('Flows', 'wp-sms')}
              </span>
            </div>
            {hasFlows ? (
              <div className="space-y-2">
                <div className="flex flex-wrap gap-x-3 gap-y-1 text-sm">
                  {flows.active > 0 && (
                    <span>
                      <span className="font-medium">{flows.active}</span>
                      <span className="text-muted-foreground"> {__('active', 'wp-sms')}</span>
                    </span>
                  )}
                  {flows.paused > 0 && (
                    <span>
                      <span className="font-medium">{flows.paused}</span>
                      <span className="text-muted-foreground"> {__('paused', 'wp-sms')}</span>
                    </span>
                  )}
                  {flows.draft > 0 && (
                    <span>
                      <span className="font-medium text-muted-foreground">{flows.draft}</span>
                      <span className="text-muted-foreground"> {__('draft', 'wp-sms')}</span>
                    </span>
                  )}
                </div>
                {totalExecutions > 0 && (
                  <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    <span>{sprintf(__('%d completed', 'wp-sms'), flows.executions_completed)}</span>
                    {flows.executions_failed > 0 && (
                      <Badge variant="destructive" className="text-[10px]">
                        <AlertCircle className="size-2.5" />
                        {sprintf(__('%d failed', 'wp-sms'), flows.executions_failed)}
                      </Badge>
                    )}
                  </div>
                )}
              </div>
            ) : (
              <div className="py-3 text-center">
                <p className="text-sm text-muted-foreground">{__('No flows created yet.', 'wp-sms')}</p>
                <Button variant="outline" size="sm" className="mt-2" onClick={() => onNavigate('automation')}>
                  {__('Create Flow', 'wp-sms')}
                </Button>
              </div>
            )}
          </div>
        </div>
      </CardContent>
      <CardFooter className="border-t pt-4 justify-between">
        {hasGateways && (
          <Button variant="ghost" size="sm" onClick={() => onNavigate('channels/gateways')}>
            {__('Manage Gateways', 'wp-sms')}
            <ArrowRight className="size-3.5" />
          </Button>
        )}
        {hasFlows && (
          <Button variant="ghost" size="sm" className={cn(!hasGateways && 'ms-auto')} onClick={() => onNavigate('automation')}>
            {__('View Flows', 'wp-sms')}
            <ArrowRight className="size-3.5" />
          </Button>
        )}
      </CardFooter>
    </Card>
  );
}

export function PlatformStatusCardSkeleton() {
  return (
    <Card>
      <CardHeader>
        <Skeleton className="h-5 w-32" />
      </CardHeader>
      <CardContent>
        <div className="grid gap-6 sm:grid-cols-2">
          <div className="space-y-3">
            <Skeleton className="h-3 w-20" />
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-5 w-full" />
          </div>
          <div className="space-y-3">
            <Skeleton className="h-3 w-16" />
            <Skeleton className="h-5 w-full" />
            <Skeleton className="h-5 w-3/4" />
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
