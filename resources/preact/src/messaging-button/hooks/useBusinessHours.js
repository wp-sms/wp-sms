import { useState, useEffect, useMemo } from 'preact/hooks';
import { __ } from '@wordpress/i18n';

export function useBusinessHours(businessHoursConfig) {
    const [isOnline, setIsOnline] = useState(true);

    useEffect(() => {
        if (!businessHoursConfig?.enabled) {
            setIsOnline(true);
            return;
        }

        const tz = businessHoursConfig.timezone || 'UTC';

        // Create the formatter once — reused every 60s instead of being
        // recreated on each interval tick.
        let formatter;
        try {
            formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: tz,
                weekday: 'long',
                hour: 'numeric',
                minute: 'numeric',
                hour12: false,
            });
        } catch {
            setIsOnline(true);
            return;
        }

        const checkHours = () => {
            const parts = formatter.formatToParts(new Date());
            const weekday = parts.find((p) => p.type === 'weekday')?.value?.toLowerCase();
            const hour = parseInt(parts.find((p) => p.type === 'hour')?.value || '0', 10);
            const minute = parseInt(parts.find((p) => p.type === 'minute')?.value || '0', 10);
            const currentTime = { weekday, hour, minute };

            const schedule = businessHoursConfig.schedule || [];
            const todaySchedule = schedule.find(
                (s) => s.day?.toLowerCase() === currentTime.weekday
            );

            if (!todaySchedule || !todaySchedule.open || !todaySchedule.close) {
                setIsOnline((prev) => prev === false ? prev : false);
                return;
            }

            const [openH, openM] = todaySchedule.open.split(':').map(Number);
            const [closeH, closeM] = todaySchedule.close.split(':').map(Number);
            const nowMinutes = currentTime.hour * 60 + currentTime.minute;
            const openMinutes = openH * 60 + openM;
            const closeMinutes = closeH * 60 + closeM;
            const online = nowMinutes >= openMinutes && nowMinutes < closeMinutes;

            setIsOnline((prev) => prev === online ? prev : online);
        };

        checkHours();
        let interval = setInterval(checkHours, 60000);

        const onVisibility = () => {
            if (document.hidden) {
                clearInterval(interval);
                interval = null;
            } else {
                checkHours();
                interval = setInterval(checkHours, 60000);
            }
        };
        document.addEventListener('visibilitychange', onVisibility);

        return () => {
            clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisibility);
        };
    }, [businessHoursConfig]);

    const offlineMessage = useMemo(() => {
        if (isOnline) return '';
        return businessHoursConfig?.offline_message || __('We are currently offline.', 'wp-sms');
    }, [isOnline, businessHoursConfig?.offline_message]);

    return { isOnline, offlineMessage };
}
