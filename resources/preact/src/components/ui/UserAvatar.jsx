import { useState, useEffect } from 'preact/hooks';
import { cn } from '@/utils/cn';

function getInitials(user) {
    if (!user) return '?';
    if (user.first_name) {
        return (user.first_name[0] + (user.last_name?.[0] || '')).toUpperCase();
    }
    if (user.display_name) {
        const parts = user.display_name.trim().split(/\s+/);
        return (parts[0][0] + (parts[1]?.[0] || '')).toUpperCase();
    }
    return (user.username?.[0] || '?').toUpperCase();
}

const SIZE_CLASSES = {
    sm: 'size-8 text-xs',
    md: 'size-10 text-sm',
    lg: 'size-14 text-lg',
};

export function UserAvatar({ user, size = 'md', className }) {
    const [imgFailed, setImgFailed] = useState(false);
    const avatarUrl = user?.avatar_url;

    useEffect(() => setImgFailed(false), [avatarUrl]);

    if (avatarUrl && !imgFailed) {
        return (
            <img
                src={avatarUrl}
                alt=""
                className={cn(
                    'shrink-0 rounded-full object-cover',
                    SIZE_CLASSES[size],
                    className,
                )}
                onError={() => setImgFailed(true)}
            />
        );
    }

    return (
        <span
            className={cn(
                'flex shrink-0 items-center justify-center rounded-full bg-primary font-semibold text-primary-foreground',
                SIZE_CLASSES[size],
                className,
            )}
        >
            {getInitials(user)}
        </span>
    );
}
