import { useState, useEffect, useRef } from 'preact/hooks';
import { WelcomePage } from './pages/WelcomePage';
import { ContactFormPage } from './pages/ContactFormPage';
import { TeamPage } from './pages/TeamPage';
import { ResourcesPage } from './pages/ResourcesPage';
import { CloseIcon, HomeIcon, MessageIcon, UsersIcon, HelpCircleIcon } from './icons';

const NAV_TAB_CONFIG = [
    { page: 'welcome', label: 'Home', Icon: HomeIcon },
    { page: 'contact_form', label: 'Message', Icon: MessageIcon },
    { page: 'team', label: 'Team', Icon: UsersIcon },
    { page: 'resources', label: 'Help', Icon: HelpCircleIcon },
];

export function WidgetPanel({ isOpen, currentPage, config, isOnline, offlineMessage, onNavigate, onClose }) {
    const [animating, setAnimating] = useState(false);
    const [visible, setVisible] = useState(false);
    const panelRef = useRef(null);
    const position = config.button?.position ?? 'bottom-right';

    const closeButtonRef = useRef(null);

    useEffect(() => {
        if (isOpen) {
            setVisible(true);
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    setAnimating(true);
                    closeButtonRef.current?.focus();
                });
            });
        } else {
            setAnimating(false);
            const timer = setTimeout(() => setVisible(false), 300);
            return () => clearTimeout(timer);
        }
    }, [isOpen]);

    // Focus trap
    useEffect(() => {
        if (!isOpen) return;

        const handleKeyDown = (e) => {
            if (e.key === 'Escape') {
                onClose();
                return;
            }

            if (e.key !== 'Tab' || !panelRef.current) return;

            const focusable = panelRef.current.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last?.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first?.focus();
            }
        };

        document.addEventListener('keydown', handleKeyDown);
        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, onClose]);

    if (!visible) return null;

    const positionClass = position === 'bottom-left'
        ? 'wsms-mb-panel--left'
        : 'wsms-mb-panel--right';

    const widgetTitle = config.widget?.title ?? 'Hi there!';
    const widgetSubtitle = config.widget?.subtitle ?? 'How can we help?';
    const primaryColor = config.button?.primary_color ?? '#2563eb';
    const titleId = 'wsms-mb-panel-title';

    const enabledPages = [];
    const pages = config.pages ?? {};
    if (pages.welcome?.enabled !== false) enabledPages.push('welcome');
    if (pages.contact_form?.enabled !== false) enabledPages.push('contact_form');
    if (pages.team?.enabled !== false) enabledPages.push('team');
    if (pages.resources?.enabled) enabledPages.push('resources');

    const renderPageContent = () => {
        if (enabledPages.length === 0) {
            return (
                <div class="wsms-mb-page" style={{ textAlign: 'center', color: 'var(--mb-text-secondary)', fontSize: '13px', padding: '40px 20px' }}>
                    No pages are currently enabled.
                </div>
            );
        }

        switch (currentPage) {
            case 'welcome':
                return <WelcomePage config={pages.welcome ?? {}} enabledPages={enabledPages} teamMembers={config.team_members ?? []} onNavigate={onNavigate} />;
            case 'contact_form':
                return <ContactFormPage config={pages.contact_form ?? {}} gdpr={config.gdpr ?? {}} onClose={onClose} />;
            case 'team':
                return <TeamPage members={config.team_members ?? []} />;
            case 'resources':
                return <ResourcesPage config={pages.resources ?? {}} />;
            default:
                return null;
        }
    };

    return (
        <div
            ref={panelRef}
            class={`wsms-mb-panel ${positionClass} ${animating ? 'wsms-mb-panel--open' : ''}`}
            role="dialog"
            aria-labelledby={titleId}
            style={{ '--panel-accent': primaryColor }}
        >
            {/* Header */}
            <div class="wsms-mb-panel__header">
                <div class="wsms-mb-panel__header-content">
                    <h2 id={titleId} class="wsms-mb-panel__title">{widgetTitle}</h2>
                    <p class="wsms-mb-panel__subtitle">{widgetSubtitle}</p>
                    <div class="wsms-mb-panel__status">
                        <span class={`wsms-mb-panel__status-dot ${isOnline ? 'wsms-mb-panel__status-dot--online' : 'wsms-mb-panel__status-dot--offline'}`} />
                        <span>{isOnline ? 'Online now' : (offlineMessage || 'Away')}</span>
                    </div>
                </div>
                <button
                    ref={closeButtonRef}
                    type="button"
                    class="wsms-mb-panel__close"
                    onClick={onClose}
                    aria-label="Close"
                >
                    <CloseIcon size={20} />
                </button>
            </div>

            {/* Page Content */}
            <div class="wsms-mb-panel__body">
                {renderPageContent()}
            </div>

            {/* Navigation tabs */}
            {enabledPages.length > 1 && (
                <div class="wsms-mb-panel__nav" role="tablist">
                    {NAV_TAB_CONFIG
                        .filter(({ page }) => enabledPages.includes(page))
                        .map(({ page, label, Icon }) => (
                            <button
                                key={page}
                                type="button"
                                role="tab"
                                class={`wsms-mb-nav-tab ${currentPage === page ? 'wsms-mb-nav-tab--active' : ''}`}
                                onClick={() => onNavigate(page)}
                                aria-label={label}
                                aria-selected={currentPage === page}
                            >
                                <Icon size={18} />
                                <span class="wsms-mb-nav-tab__label">{label}</span>
                            </button>
                        ))
                    }
                </div>
            )}
        </div>
    );
}
