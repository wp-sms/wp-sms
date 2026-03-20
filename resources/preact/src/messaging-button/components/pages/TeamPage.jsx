import { MessageIcon, UsersIcon } from '../icons';
import { AvatarImage } from '../AvatarImage';

// Brand icons (WhatsApp, Telegram) are unique to this component and use fill
// rather than stroke, so they stay inline here.
const CHANNEL_ICONS = {
    whatsapp: (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
    ),
    telegram: (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.479.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
    ),
    sms: <MessageIcon size={16} />,
    email: (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
    ),
    phone: (
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
    ),
};

function resolveMessage(template, memberName) {
    if (!template) return '';
    return template
        .replace(/\{page_title\}/g, document.title || '')
        .replace(/\{page_url\}/g, window.location.href || '')
        .replace(/\{member_name\}/g, memberName || '');
}

function getChannelUrl(type, value, message) {
    const base = (() => {
        switch (type) {
            case 'whatsapp': return `https://wa.me/${value.replace(/[^0-9+]/g, '')}`;
            case 'telegram': return `https://t.me/${value.replace('@', '')}`;
            case 'email': return `mailto:${value}`;
            case 'phone': return `tel:${value}`;
            case 'sms': return `sms:${value}`;
            default: return '#';
        }
    })();

    if (!message || type === 'phone') return base;

    const encoded = encodeURIComponent(message);
    switch (type) {
        case 'whatsapp':
        case 'telegram': return `${base}?text=${encoded}`;
        case 'email': return `${base}?subject=${encodeURIComponent('Question from website')}&body=${encoded}`;
        case 'sms': return `${base}?body=${encoded}`;
        default: return base;
    }
}

export function TeamPage({ members, defaultMessage }) {
    if (!members || members.length === 0) {
        return (
            <div class="wsms-mb-page wsms-mb-page--team">
                <div class="wsms-mb-empty">
                    <div class="wsms-mb-empty__icon"><UsersIcon size={22} /></div>
                    <p>No team members configured.</p>
                </div>
            </div>
        );
    }

    return (
        <div class="wsms-mb-page wsms-mb-page--team">
            <div class="wsms-mb-team__list">
                {members.map((member, i) => (
                    <div key={i} class="wsms-mb-team-card">
                        <div class="wsms-mb-team-card__avatar">
                            {member.avatar_url ? (
                                <AvatarImage src={member.avatar_url} name={member.name} />
                            ) : (
                                <span>{(member.name || '?')[0].toUpperCase()}</span>
                            )}
                        </div>
                        <div class="wsms-mb-team-card__info">
                            <h4 class="wsms-mb-team-card__name">{member.name}</h4>
                            {member.role && (
                                <p class="wsms-mb-team-card__role">{member.role}</p>
                            )}
                        </div>
                        <div class="wsms-mb-team-card__channels">
                            {(member.contact_methods ?? []).map((method, j) => (
                                <a
                                    key={j}
                                    href={getChannelUrl(method.type, method.value, resolveMessage(defaultMessage, member.name))}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class={`wsms-mb-channel-btn wsms-mb-channel-btn--${method.type}`}
                                    title={method.type}
                                    aria-label={`Contact ${member.name} via ${method.type}`}
                                >
                                    {CHANNEL_ICONS[method.type] || CHANNEL_ICONS.email}
                                </a>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
