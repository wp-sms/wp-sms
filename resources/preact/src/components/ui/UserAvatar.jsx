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
    sm: 'wsms-auth-avatar--sm',
    md: 'wsms-auth-avatar--md',
    lg: 'wsms-auth-avatar--lg',
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
                className={cn('wsms-auth-avatar wsms-auth-avatar--img', SIZE_CLASSES[size], className)}
                onError={() => setImgFailed(true)}
            />
        );
    }

    return (
        <span className={cn('wsms-auth-avatar wsms-auth-avatar--initials', SIZE_CLASSES[size], className)}>
            {getInitials(user)}
        </span>
    );
}
