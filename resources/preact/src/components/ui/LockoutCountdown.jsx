import { useState, useEffect, useRef } from 'preact/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { Lock } from 'lucide-react';

const CIRCUMFERENCE = 2 * Math.PI * 30;
const RING_STYLE = { transform: 'rotate(-90deg)', transformOrigin: 'center', transition: 'stroke-dashoffset 1s linear' };

export function LockoutCountdown({ seconds: initialSeconds, onExpire }) {
    const [remaining, setRemaining] = useState(initialSeconds);
    const onExpireRef = useRef(onExpire);
    const totalRef = useRef(initialSeconds);
    const lastAnnouncedRef = useRef(initialSeconds);
    const [announcement, setAnnouncement] = useState('');
    onExpireRef.current = onExpire;

    useEffect(() => {
        if (remaining <= 0) {
            setAnnouncement(__('You can try again now', 'wp-sms'));
            onExpireRef.current?.();
            return;
        }
        const timer = setTimeout(() => setRemaining((s) => s - 1), 1000);
        return () => clearTimeout(timer);
    }, [remaining]);

    // Announce at meaningful intervals: every 30s, plus once at 10s remaining
    useEffect(() => {
        if (remaining <= 0) return;
        const elapsed = lastAnnouncedRef.current - remaining;
        if (elapsed >= 30 || (remaining <= 10 && lastAnnouncedRef.current > 10)) {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            setAnnouncement(sprintf(__('%d:%02d remaining', 'wp-sms'), mins, secs));
            lastAnnouncedRef.current = remaining;
        }
    }, [remaining]);

    const mins = Math.floor(remaining / 60);
    const secs = remaining % 60;
    const progress = remaining / totalRef.current;

    return (
        <div className="wsms-auth-lockout">
            <Lock className="wsms-auth-lockout__icon" />
            <div className="wsms-auth-lockout__title">{__('Account temporarily locked', 'wp-sms')}</div>
            <p className="wsms-auth-text-sm wsms-auth-text-muted">{__('Too many failed attempts', 'wp-sms')}</p>
            <div className="wsms-auth-lockout__ring" role="timer" aria-label={sprintf(__('%d:%02d remaining', 'wp-sms'), mins, secs)}>
                <svg width="72" height="72" viewBox="0 0 72 72" aria-hidden="true">
                    <circle cx="36" cy="36" r="30" fill="none" stroke="var(--border)" strokeWidth="4" />
                    <circle
                        cx="36" cy="36" r="30"
                        fill="none"
                        stroke="var(--destructive)"
                        strokeWidth="4"
                        strokeLinecap="round"
                        strokeDasharray={CIRCUMFERENCE}
                        strokeDashoffset={CIRCUMFERENCE * (1 - progress)}
                        style={RING_STYLE}
                    />
                </svg>
                <span className="wsms-auth-lockout__time" aria-hidden="true">
                    {mins}:{String(secs).padStart(2, '0')}
                </span>
            </div>
            <p className="wsms-auth-text-xs wsms-auth-text-muted">{__('You can try again when the timer expires', 'wp-sms')}</p>
            <span aria-live="polite" className="sr-only">{announcement}</span>
        </div>
    );
}
