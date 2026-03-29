import { useState, useEffect } from 'preact/hooks';
import { cn } from '@/utils/cn';
import { getInitials } from '@/utils/format';

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
                alt={user?.display_name || ''}
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
