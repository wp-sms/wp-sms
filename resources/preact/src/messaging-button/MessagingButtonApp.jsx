import { widgetOpen, currentPage } from './main-messaging-button';
import { FloatingButton } from './components/FloatingButton';
import { WidgetPanel } from './components/WidgetPanel';
import { GreetingBubble } from './components/GreetingBubble';
import { useBusinessHours } from './hooks/useBusinessHours';
import { useTriggers } from './hooks/useTriggers';
import { markUserClosed } from './utils/storage';
import { useState, useEffect, useCallback } from 'preact/hooks';

const config = window.wsmsMessagingButtonConfig?.config ?? {};

function useIsMobile(breakpoint = 480) {
    const [isMobile, setIsMobile] = useState(
        typeof window !== 'undefined' && window.innerWidth <= breakpoint
    );
    useEffect(() => {
        const mql = window.matchMedia(`(max-width: ${breakpoint}px)`);
        const handler = (e) => setIsMobile(e.matches);
        mql.addEventListener('change', handler);
        return () => mql.removeEventListener('change', handler);
    }, [breakpoint]);
    return isMobile;
}

export function MessagingButtonApp() {
    const { isOnline, offlineMessage } = useBusinessHours(config.business_hours);
    const isMobile = useIsMobile();

    const handleClose = useCallback(() => {
        widgetOpen.value = false;
        markUserClosed();
    }, []);

    const openWidget = useCallback(() => {
        widgetOpen.value = true;
    }, []);

    const handleToggle = useCallback(() => {
        if (widgetOpen.value) {
            handleClose();
        } else {
            widgetOpen.value = true;
        }
    }, [handleClose]);

    const handleNavigate = useCallback((page) => {
        currentPage.value = page;
    }, []);

    useTriggers(config.triggers, widgetOpen.value, openWidget);

    const greetingBubble = config.greeting_bubble;
    const buttonPosition = config.button?.position ?? 'bottom-right';

    const hideFab = isMobile && widgetOpen.value;

    return (
        <>
            <WidgetPanel
                isOpen={widgetOpen.value}
                currentPage={currentPage.value}
                config={config}
                isOnline={isOnline}
                offlineMessage={offlineMessage}
                onNavigate={handleNavigate}
                onClose={handleClose}
            />
            {greetingBubble?.enabled && !hideFab && (
                <GreetingBubble
                    config={greetingBubble}
                    position={buttonPosition}
                    isWidgetOpen={widgetOpen.value}
                    onOpen={openWidget}
                />
            )}
            {!hideFab && (
                <FloatingButton
                    isOpen={widgetOpen.value}
                    config={config.button ?? {}}
                    onToggle={handleToggle}
                />
            )}
        </>
    );
}
