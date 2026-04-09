import { useCallback, useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { api, type FreshAuthGateInfo } from '@/lib/api';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';

// Passkey assertion requires a WebAuthn ceremony that this admin-side modal
// does not implement; it's filtered out of the method picker. Users on the
// Preact auth UI can step up with passkey via the shared REST endpoints.
const UNSUPPORTED_METHODS = new Set(['passkey']);

interface StepUpChallengeProps {
  open: boolean;
  info: FreshAuthGateInfo | null;
  onResolve: (success: boolean) => void;
}

interface ChallengeResponse {
  success: boolean;
  challenge_id?: string;
  method?: string;
  meta?: Record<string, unknown>;
}

interface VerifyResponse {
  success: boolean;
  fresh_auth_at?: number;
  fresh_auth_window_seconds?: number;
}

function methodLabel(method: string): string {
  switch (method) {
    case 'password': return __('Password', 'wp-sms');
    case 'otp_email': return __('Email code', 'wp-sms');
    case 'otp_phone': return __('Phone code', 'wp-sms');
    case 'totp': return __('Authenticator app', 'wp-sms');
    case 'backup_codes': return __('Backup code', 'wp-sms');
    default: return method;
  }
}

function formatAge(seconds: number | null): string | null {
  if (seconds === null || seconds < 0) return null;
  const mins = Math.round(seconds / 60);
  if (mins < 1) return __('less than a minute', 'wp-sms');
  if (mins === 1) return __('1 minute', 'wp-sms');
  if (mins < 60) return sprintf(__('%d minutes', 'wp-sms'), mins);
  const hours = Math.round(mins / 60);
  return sprintf(__('%d hours', 'wp-sms'), hours);
}

/**
 * Step-up re-authentication modal.
 *
 * Opened by the fresh-auth interceptor when a sensitive request gets a
 * 403 fresh_auth_required. The user picks a method, the modal issues a
 * challenge, collects the response, and calls /auth/step-up/verify. On
 * success it resolves its promise with `true`, which causes the
 * interceptor to replay the original request once.
 */
export function StepUpChallenge({ open, info, onResolve }: StepUpChallengeProps) {
  const firstInputRef = useRef<HTMLInputElement | null>(null);
  const [method, setMethod] = useState<string>('');
  const [challengeId, setChallengeId] = useState<string | null>(null);
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  const availableMethods = (info?.step_up_methods ?? []).filter((m) => !UNSUPPORTED_METHODS.has(m));

  // Reset state whenever the modal opens with new info.
  useEffect(() => {
    if (!open) return;
    setMethod(availableMethods[0] ?? '');
    setChallengeId(null);
    setCode('');
    setPassword('');
    setError(null);
    setLoading(false);
    // availableMethods is derived from `info`; using `info` as the dep
    // avoids spurious resets from array identity changes.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, info]);

  useEffect(() => {
    if (open) {
      // Focus trap: focus the first interactive element on open.
      const timer = setTimeout(() => firstInputRef.current?.focus(), 50);
      return () => clearTimeout(timer);
    }
  }, [open, method]);

  const issueChallenge = useCallback(async (chosen: string) => {
    setLoading(true);
    setError(null);
    try {
      const res = await api.post<ChallengeResponse>('auth/step-up/challenge', { method: chosen });
      if (res.success && res.challenge_id) {
        setChallengeId(res.challenge_id);
      } else {
        setError(__('Could not start step-up challenge. Please try a different method.', 'wp-sms'));
      }
    } catch (e) {
      const msg = (e as { message?: string })?.message
        ?? __('Could not start step-up challenge.', 'wp-sms');
      setError(msg);
    } finally {
      setLoading(false);
    }
  }, []);

  const onPickMethod = useCallback(async (chosen: string) => {
    setMethod(chosen);
    setChallengeId(null);
    setCode('');
    setPassword('');
    setError(null);
    // Password method is inline — no challenge dispatched.
    if (chosen !== 'password' && chosen !== '') {
      await issueChallenge(chosen);
    }
  }, [issueChallenge]);

  const onSubmit = useCallback(async (e?: React.FormEvent) => {
    e?.preventDefault();
    if (!method) return;
    setLoading(true);
    setError(null);

    try {
      let cid = challengeId;
      // For password, we create the challenge at submit time so the page
      // doesn't waste one when the user hit Cancel.
      if (method === 'password' && cid === null) {
        const chRes = await api.post<ChallengeResponse>('auth/step-up/challenge', { method });
        if (!chRes.success || !chRes.challenge_id) {
          setError(__('Could not start step-up challenge.', 'wp-sms'));
          return;
        }
        cid = chRes.challenge_id;
      }

      if (!cid) {
        setError(__('No active challenge. Please try again.', 'wp-sms'));
        return;
      }

      const response = method === 'password' ? { password } : { code };

      const verify = await api.post<VerifyResponse>('auth/step-up/verify', {
        challenge_id: cid,
        response,
      });

      if (verify.success) {
        onResolve(true);
      } else {
        setError(__('Verification failed.', 'wp-sms'));
      }
    } catch (err) {
      const apiErr = err as { message?: string };
      setError(apiErr?.message ?? __('Verification failed.', 'wp-sms'));
    } finally {
      setLoading(false);
    }
  }, [challengeId, code, method, password, onResolve]);

  const onCancel = useCallback(() => {
    onResolve(false);
  }, [onResolve]);

  const ageLabel = formatAge(info?.current_freshness_age ?? null);

  return (
    <Dialog open={open} onOpenChange={(next) => { if (!next) onCancel(); }}>
      <DialogContent aria-describedby="wsms-step-up-desc">
        <DialogHeader>
          <DialogTitle>{__('Confirm it\u2019s you', 'wp-sms')}</DialogTitle>
          <DialogDescription id="wsms-step-up-desc">
            {ageLabel
              ? sprintf(__('This action is sensitive. Your session is %s old — please re-verify to continue.', 'wp-sms'), ageLabel)
              : __('This action is sensitive. Please re-verify to continue.', 'wp-sms')}
          </DialogDescription>
        </DialogHeader>

        {error ? (
          <Alert variant="destructive" aria-live="polite">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        ) : null}

        <form onSubmit={onSubmit} className="space-y-4">
          {availableMethods.length > 1 ? (
            <div className="space-y-2">
              <Label>{__('Choose a verification method', 'wp-sms')}</Label>
              <div className="flex flex-wrap gap-2">
                {availableMethods.map((m) => (
                  <Button
                    key={m}
                    type="button"
                    variant={m === method ? 'default' : 'outline'}
                    size="sm"
                    onClick={() => onPickMethod(m)}
                    disabled={loading}
                  >
                    {methodLabel(m)}
                  </Button>
                ))}
              </div>
            </div>
          ) : null}

          {method === 'password' ? (
            <div className="space-y-2">
              <Label htmlFor="wsms-step-up-password">{__('Current password', 'wp-sms')}</Label>
              <Input
                ref={firstInputRef}
                id="wsms-step-up-password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                autoComplete="current-password"
                required
              />
            </div>
          ) : method ? (
            <div className="space-y-2">
              <Label htmlFor="wsms-step-up-code">
                {method === 'backup_codes'
                  ? __('Backup code', 'wp-sms')
                  : __('Verification code', 'wp-sms')}
              </Label>
              <Input
                ref={firstInputRef}
                id="wsms-step-up-code"
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                required
              />
            </div>
          ) : null}

          <DialogFooter>
            <Button type="button" variant="outline" onClick={onCancel} disabled={loading}>
              {__('Cancel', 'wp-sms')}
            </Button>
            <Button type="submit" disabled={loading || !method}>
              {loading ? __('Verifying\u2026', 'wp-sms') : __('Verify', 'wp-sms')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
