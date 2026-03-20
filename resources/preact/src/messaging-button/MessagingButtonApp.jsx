import { widgetOpen, currentPage } from './main-messaging-button';
import { FloatingButton } from './components/FloatingButton';
import { WidgetPanel } from './components/WidgetPanel';
import { useBusinessHours } from './hooks/useBusinessHours';
import { useState, useEffect } from 'preact/hooks';

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

    const handleToggle = () => {
        widgetOpen.value = !widgetOpen.value;
    };

    const handleNavigate = (page) => {
        currentPage.value = page;
    };

    const handleClose = () => {
        widgetOpen.value = false;
    };

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
