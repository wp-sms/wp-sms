import { useState, useRef, useEffect } from 'preact/hooks';
import { User, Shield, KeyRound, LogOut } from 'lucide-react';
import { getInitials } from '../utils/format';

const NAV_ITEMS = [
    { path: '/profile', icon: User, label: 'Profile' },
    { path: '/security', icon: Shield, label: 'Security' },
    { path: '/change-password', icon: KeyRound, label: 'Password' },
];

function Avatar({ user }) {
    return user.avatar_url
        ? <img className="wsms-ub-avatar wsms-ub-avatar--img" src={user.avatar_url} alt="" />
        : <span className="wsms-ub-avatar wsms-ub-avatar--initials">{getInitials(user)}</span>;
}

export function UserButtonDropdown({ user, baseUrl, logoutUrl }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);

    useEffect(() => {
        if (!open) return;
        function handleClick(e) {
            if (!e.composedPath().includes(ref.current)) setOpen(false);
        }
        document.addEventListener('click', handleClick, true);
        return () => document.removeEventListener('click', handleClick, true);
    }, [open]);

    useEffect(() => {
        if (!open) return;
        function handleKey(e) {
            if (e.key === 'Escape') setOpen(false);
        }
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [open]);

    const displayName = user.display_name || user.username;

    return (
        <div className="wsms-ub" ref={ref}>
            <button
                className="wsms-ub-trigger"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-haspopup="true"
                title={displayName}
            >
                <Avatar user={user} />
            </button>

            {open && (
                <div className="wsms-ub-dropdown" role="menu">
                    <div className="wsms-ub-dropdown__header">
                        <Avatar user={user} />
                        <div className="wsms-ub-dropdown__info">
                            <div className="wsms-ub-dropdown__name">{displayName}</div>
                            <div className="wsms-ub-dropdown__email">{user.email}</div>
                        </div>
                    </div>

                    <div className="wsms-ub-dropdown__sep" />

                    {NAV_ITEMS.map(({ path, icon: Icon, label }) => (
                        <a
                            key={path}
                            href={`${baseUrl}${path}`}
                            className="wsms-ub-dropdown__item"
                            role="menuitem"
                            onClick={() => setOpen(false)}
                        >
                            <Icon />
                            {label}
                        </a>
                    ))}

                    <div className="wsms-ub-dropdown__sep" />

                    <a
                        href={logoutUrl}
                        className="wsms-ub-dropdown__item wsms-ub-dropdown__item--danger"
                        role="menuitem"
                    >
                        <LogOut />
                        Sign out
                    </a>
                </div>
            )}
        </div>
    );
}
