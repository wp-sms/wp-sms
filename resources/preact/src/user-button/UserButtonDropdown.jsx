import { useState, useRef, useEffect } from 'preact/hooks';
import { __ } from '@wordpress/i18n';
import { User, Shield, KeyRound, LogOut } from 'lucide-react';
import { getInitials } from '../utils/format';

const NAV_ITEMS = [
    { path: '/profile', icon: User, label: __('Profile', 'wp-sms') },
    { path: '/security', icon: Shield, label: __('Security', 'wp-sms') },
    { path: '/change-password', icon: KeyRound, label: __('Password', 'wp-sms') },
];

function Avatar({ user }) {
    return user.avatar_url
        ? <img className="wsms-ub-avatar wsms-ub-avatar--img" src={user.avatar_url} alt="" />
        : <span className="wsms-ub-avatar wsms-ub-avatar--initials">{getInitials(user)}</span>;
}

export function UserButtonDropdown({ user, baseUrl, logoutUrl }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    const triggerRef = useRef(null);
    const menuRef = useRef(null);

    // Close on outside click
    useEffect(() => {
        if (!open) return;
        function handleClick(e) {
            if (!e.composedPath().includes(ref.current)) setOpen(false);
        }
        document.addEventListener('click', handleClick, true);
        return () => document.removeEventListener('click', handleClick, true);
    }, [open]);

    // Keyboard navigation for the dropdown menu
    useEffect(() => {
        if (!open || !menuRef.current) return;

        // Focus the first menu item when opened
        const items = menuRef.current.querySelectorAll('[role="menuitem"]');
        items[0]?.focus();

        function handleKey(e) {
            const menuItems = menuRef.current?.querySelectorAll('[role="menuitem"]');
            if (!menuItems?.length) return;
            const currentIndex = Array.from(menuItems).indexOf(document.activeElement);

            switch (e.key) {
                case 'Escape':
                    setOpen(false);
                    triggerRef.current?.focus();
                    break;
                case 'ArrowDown': {
                    e.preventDefault();
                    const next = currentIndex < menuItems.length - 1 ? currentIndex + 1 : 0;
                    menuItems[next]?.focus();
                    break;
                }
                case 'ArrowUp': {
                    e.preventDefault();
                    const prev = currentIndex > 0 ? currentIndex - 1 : menuItems.length - 1;
                    menuItems[prev]?.focus();
                    break;
                }
                case 'Home':
                    e.preventDefault();
                    menuItems[0]?.focus();
                    break;
                case 'End':
                    e.preventDefault();
                    menuItems[menuItems.length - 1]?.focus();
                    break;
                case 'Tab':
                    // Close menu on Tab to let natural focus flow continue
                    setOpen(false);
                    break;
            }
        }
        document.addEventListener('keydown', handleKey);
        return () => document.removeEventListener('keydown', handleKey);
    }, [open]);

    const displayName = user.display_name || user.username;

    return (
        <div className="wsms-ub" ref={ref}>
            <button
                ref={triggerRef}
                className="wsms-ub-trigger"
                onClick={() => setOpen((v) => !v)}
                aria-expanded={open}
                aria-haspopup="true"
                aria-label={displayName}
            >
                <Avatar user={user} />
            </button>

            {open && (
                <div ref={menuRef} className="wsms-ub-dropdown" role="menu">
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
                            tabIndex={-1}
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
                        tabIndex={-1}
                    >
                        <LogOut />
                        {__('Sign out', 'wp-sms')}
                    </a>
                </div>
            )}
        </div>
    );
}
