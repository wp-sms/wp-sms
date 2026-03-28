import { __ } from '@wordpress/i18n';
import { MessageIcon, ChevronRightIcon, HelpCircleIcon } from '../icons';
import { AvatarImage } from '../AvatarImage';

export function WelcomePage({ config, enabledPages, teamMembers, onNavigate }) {
    const greeting = config.greeting ?? __('Welcome! Choose an option below to get started.', 'wp-sms');
    const ctaLabel = config.cta_label ?? __('Send a message', 'wp-sms');
    const ctaDescription = config.cta_description ?? __('We typically reply within a few hours', 'wp-sms');

    return (
        <div class="wsms-mb-page wsms-mb-page--welcome">
            <p class="wsms-mb-welcome__greeting">{greeting}</p>

            {/* Quick actions */}
            <div class="wsms-mb-welcome__actions">
                {enabledPages.includes('contact_form') && (
                    <button
                        type="button"
                        class="wsms-mb-welcome__cta"
                        onClick={() => onNavigate('contact_form')}
                    >
                        <div class="wsms-mb-welcome__cta-icon">
                            <MessageIcon size={18} />
                        </div>
                        <div class="wsms-mb-welcome__cta-content">
                            <span>{ctaLabel}</span>
                            {ctaDescription && <p class="wsms-mb-welcome__cta-desc">{ctaDescription}</p>}
                        </div>
                        <ChevronRightIcon size={16} class="wsms-mb-welcome__arrow" />
                    </button>
                )}
            </div>

            {/* Team member preview */}
            {enabledPages.includes('team') && teamMembers.length > 0 && (
                <div class="wsms-mb-welcome__team-preview">
                    <button
                        type="button"
                        class="wsms-mb-welcome__team-link"
                        onClick={() => onNavigate('team')}
                    >
                        <div class="wsms-mb-welcome__avatars">
                            {teamMembers.slice(0, 3).map((member, i) => (
                                <div key={i} class="wsms-mb-welcome__avatar">
                                    {member.avatar_url ? (
                                        <AvatarImage src={member.avatar_url} name={member.name} />
                                    ) : (
                                        <span>{(member.name || '?')[0].toUpperCase()}</span>
                                    )}
                                </div>
                            ))}
                        </div>
                        <span class="wsms-mb-welcome__team-text">
                            {__('Meet our team', 'wp-sms')}
                            <ChevronRightIcon size={14} />
                        </span>
                    </button>
                </div>
            )}

            {/* Resources link */}
            {enabledPages.includes('resources') && (
                <button
                    type="button"
                    class="wsms-mb-welcome__resources-link"
                    onClick={() => onNavigate('resources')}
                >
                    <HelpCircleIcon size={16} />
                    <span>{__('Browse help articles', 'wp-sms')}</span>
                </button>
            )}
        </div>
    );
}
