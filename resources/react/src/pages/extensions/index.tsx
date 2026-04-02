import { __ } from '@wordpress/i18n';
import { useEffect, useState } from 'react';
import { api, type ExtensionInfo } from '@/lib/api';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/layout/page-header';
import { PageRenderer } from '@/components/page-renderer';
import { Skeleton } from '@/components/ui/skeleton';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, Puzzle, ArrowLeft, ExternalLink } from 'lucide-react';
import { getErrorMessage } from '@/lib/error-utils';

export function ExtensionsPage() {
  const [extensions, setExtensions] = useState<ExtensionInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeExtensionId, setActiveExtensionId] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    api.get<{ success: boolean; extensions: ExtensionInfo[] }>('extensions')
      .then((res) => {
        if (!cancelled) setExtensions(res.extensions);
      })
      .catch((err: unknown) => {
        if (!cancelled) setError(getErrorMessage(err, __('Failed to load extensions', 'wp-sms')));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-24 w-full" />
        <Skeleton className="h-24 w-full" />
      </div>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader icon={Puzzle} title={__('Extensions', 'wp-sms')} />
        <Alert variant="destructive" className="mt-4">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </>
    );
  }

  // Extension page view
  if (activeExtensionId) {
    const ext = extensions.find(e => e.id === activeExtensionId);
    const backButton = (
      <Button variant="ghost" size="sm" className="mb-4" onClick={() => setActiveExtensionId(null)}>
        <ArrowLeft className="h-4 w-4 mr-1" />
        {__('Back to extensions', 'wp-sms')}
      </Button>
    );

    if (!ext?.page) {
      return (
        <>
          {backButton}
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{__('Extension page not found.', 'wp-sms')}</AlertDescription>
          </Alert>
        </>
      );
    }

    return (
      <>
        {backButton}
        <PageRenderer layout={ext.page.layout} title={ext.page.title} />
      </>
    );
  }

  // Extension list view
  return (
    <>
      <PageHeader icon={Puzzle} title={__('Extensions', 'wp-sms')} />

      {extensions.length === 0 ? (
        <Card className="mt-4">
          <CardContent className="flex flex-col items-center justify-center py-12 text-center">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-muted">
              <Puzzle className="h-6 w-6 text-muted-foreground" />
            </div>
            <h3 className="mt-4 text-sm font-medium">{__('No extensions installed', 'wp-sms')}</h3>
            <p className="mt-1 text-sm text-muted-foreground">
              {__('Extensions add social providers, MFA channels, and other capabilities to your site.', 'wp-sms')}
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {extensions.map((ext) => (
            <Card key={ext.id}>
              <CardContent className="p-4">
                <div className="flex items-start justify-between">
                  <div>
                    <h3 className="text-sm font-medium">{ext.name}</h3>
                    {ext.description && (
                      <p className="mt-1 text-xs text-muted-foreground">{ext.description}</p>
                    )}
                  </div>
                  <div className="flex gap-1.5">
                    {ext.version && (
                      <Badge variant="outline" className="text-[10px]">
                        {ext.version}
                      </Badge>
                    )}
                    {ext.type && (
                      <Badge variant="secondary" className="text-[10px]">
                        {ext.type}
                      </Badge>
                    )}
                  </div>
                </div>
                {ext.requires && (
                  <p className="mt-2 text-[10px] text-muted-foreground">
                    {__('Requires:', 'wp-sms')} {ext.requires}
                  </p>
                )}
                {ext.page && (
                  <Button
                    variant="outline"
                    size="sm"
                    className="mt-3"
                    onClick={() => setActiveExtensionId(ext.id)}
                  >
                    <ExternalLink className="h-3.5 w-3.5 mr-1" />
                    {__('Open', 'wp-sms')}
                  </Button>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}
