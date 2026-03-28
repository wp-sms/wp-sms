import { useState, useEffect, useRef } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { CloseIcon } from './icons';
import { hasSeenGreeting, markGreetingSeen } from '../utils/storage';

export function GreetingBubble({ config, position, isWidgetOpen, onOpen }) {
    const [visible, setVisible] = useState(false);
    const showTimer = useRef(null);
    const hideTimer = useRef(null);

    useEffect(() => {
        if (!config?.message || hasSeenGreeting(config.message)) return;

        const delay = (config.delay ?? 3) * 1000;

        showTimer.current = setTimeout(() => {
            setVisible(true);

            const duration = config.duration ?? 8;
            if (duration > 0) {
                hideTimer.current = setTimeout(() => setVisible(false), duration * 1000);
            }
        }, delay);

        return () => {
            clearTimeout(showTimer.current);
            clearTimeout(hideTimer.current);
        };
    }, [config?.message, config?.delay, config?.duration]);

    // Mark as seen only when the bubble is actually rendered (not hidden by isWidgetOpen)
    const wasShown = useRef(false);
    useEffect(() => {
        if (visible && !isWidgetOpen && !wasShown.current) {
            wasShown.current = true;
            markGreetingSeen(config.message);
        }
    }, [visible, isWidgetOpen, config?.message]);

    if (!visible || isWidgetOpen) return null;

    const posClass = position === 'bottom-left' ? 'wsms-mb-greeting--left' : 'wsms-mb-greeting--right';

    const handleClick = () => {
        if (config.open_on_click) {
            setVisible(false);
            onOpen?.();
        }
    };

    const handleClose = (e) => {
        e.stopPropagation();
        setVisible(false);
    };

    return (
        <div
            class={`wsms-mb-greeting wsms-mb-greeting--show ${posClass}`}
            role="status"
            onClick={handleClick}
            style={config.open_on_click ? { cursor: 'pointer' } : undefined}
        >
            <span class="wsms-mb-greeting__text">{config.message}</span>
            <button
                type="button"
                class="wsms-mb-greeting__close"
                onClick={handleClose}
                aria-label={__('Dismiss', 'wp-sms')}
            >
                <CloseIcon size={12} />
            </button>
            <div class={`wsms-mb-greeting__tail ${position === 'bottom-left' ? 'wsms-mb-greeting__tail--left' : ''}`} />
        </div>
    );
}
