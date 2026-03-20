import { useEffect, useRef } from 'preact/hooks';
import { hasTriggerFired, markTriggerFired, hasUserClosed } from '../utils/storage';

export function useTriggers(config, isWidgetOpen, onOpen) {
    const isOpenRef = useRef(isWidgetOpen);
    isOpenRef.current = isWidgetOpen;

    useEffect(() => {
        if (!config) return;

        function canFire(name) {
            return !isOpenRef.current && !hasUserClosed() && !hasTriggerFired(name);
        }

        function fire(name) {
            markTriggerFired(name);
            onOpen();
        }

        const cleanups = [];

        // Auto-open delay
        const autoDelay = config.auto_open_delay;
        if (autoDelay > 0 && !hasTriggerFired('auto_open')) {
            const timer = setTimeout(() => {
                if (canFire('auto_open')) fire('auto_open');
            }, autoDelay * 1000);
            cleanups.push(() => clearTimeout(timer));
        }

        // Scroll percent
        const scrollPct = config.scroll_percent;
        if (scrollPct > 0 && !hasTriggerFired('scroll')) {
            const onScroll = () => {
                const docHeight = document.documentElement.scrollHeight;
                const winHeight = window.innerHeight;
                if (docHeight - winHeight <= 0) return;
                const pct = (window.scrollY / (docHeight - winHeight)) * 100;
                if (pct >= scrollPct && canFire('scroll')) {
                    fire('scroll');
                    window.removeEventListener('scroll', onScroll);
                }
            };
            window.addEventListener('scroll', onScroll, { passive: true });
            cleanups.push(() => window.removeEventListener('scroll', onScroll));
        }

        // Exit intent (desktop only)
        if (config.exit_intent && !('ontouchstart' in window) && !hasTriggerFired('exit_intent')) {
            const onMouseOut = (e) => {
                if (e.relatedTarget === null && e.clientY <= 0 && canFire('exit_intent')) {
                    fire('exit_intent');
                    document.removeEventListener('mouseout', onMouseOut);
                }
            };
            document.addEventListener('mouseout', onMouseOut);
            cleanups.push(() => document.removeEventListener('mouseout', onMouseOut));
        }

        return () => cleanups.forEach((fn) => fn());
    }, [config, onOpen]);
}
