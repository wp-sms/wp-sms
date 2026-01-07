<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/** @var \WP_SMS\Services\MessageButton\ChatBoxDecorator $chatbox */
$general_color      = $chatbox->getTextColor() ? sprintf('color: %s!important;', $chatbox->getTextColor()) : '';
$general_background = $chatbox->getColor() ? sprintf('background-color: %s;', $chatbox->getColor()) : '';
$general_fill_color = $chatbox->getTextColor('#FFF');
$footer_color       = $chatbox->getFooterTextColor() ? sprintf('color: %s!important;', $chatbox->getFooterTextColor()) : '';
?>
<style>
    .wpsms-chatbox__header h2{
    <?php echo esc_attr($general_color) ?>
    }
    .wpsms-chatbox__info--text{
    <?php echo esc_attr($footer_color) ?>
    }
</style>

<div class="wpsms-chatbox wpsms-chatbox--ltr  wpsms-chatbox--orange-theme <?php echo $chatbox->getButtonPosition() === 'bottom_right' ? 'wpsms-chatbox--right-side' : 'wpsms-chatbox--left-side' ?>">
    <button class="wpsms-chatbox__button js-wpsms-chatbox__button wpsms-chatbox__button--rounded wpsms-chatbox__button--rounded wpsms-chatbox__button--has-arrow wpsms-chatbox--bobbles" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>">
        <span class="wpsms-chatbox__button-arrow">
           <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" fill="none"><g clip-path="url(#a)"><path stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="2" d="m11 1.5-5 5-5-5"/></g><defs><clipPath id="a"><path fill="<?php echo esc_attr($general_fill_color) ?>" d="M0 .5h12v7H0z"/></clipPath></defs></svg>
        </span>
        <span class="wpsms-chatbox__button-icon messenger">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"><path fill="<?php echo esc_attr($general_fill_color) ?>" d="M6 9a.968.968 0 0 0 .713-.288A.964.964 0 0 0 7 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 6 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 5 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 11 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 10 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 9 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 15 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 14 7a.965.965 0 0 0-.712.288A.973.973 0 0 0 13 8c0 .283.096.521.288.713.192.192.43.288.712.287ZM0 20V2C0 1.45.196.98.588.588A1.93 1.93 0 0 1 2 0h16c.55 0 1.021.196 1.413.588.392.392.588.863.587 1.412v12c0 .55-.196 1.021-.587 1.413A1.92 1.92 0 0 1 18 16H4l-4 4Zm3.15-6H18V2H2v13.125L3.15 14Z"/></svg>
        </span>
        <span class="wpsms-chatbox__button-title">
            <?php echo esc_html($chatbox->getButtonText()); ?>
        </span>
    </button>

    <div class="wpsms-chatbox__content <?php echo $chatbox->getAnimationEffect() ? 'wpsms-chatbox__content--' . esc_attr($chatbox->getAnimationEffect()) : '' ?>">
        <div class="wpsms-chatbox__header" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>">
            <h2>
                <?php echo esc_html($chatbox->getTitle()); ?>
            </h2>
            <button class="wpsms-chatbox__close-button js-wpsms-chatbox__close-button">
                <span class="screen-reader-text"><?php esc_html_e('Close', 'wp-sms'); ?></span>
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 11L6 6L11 11M11 1L5.99905 6L1 1" stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>
        <div class="wpsms-chatbox__elements">
            <div class="wpsms-chatbox__container">
                <div class="wpsms-chatbox__teams">
                    <?php if ($chatbox->fetchTeamMembers()) : ?>
                        <?php foreach ($chatbox->fetchTeamMembers() as $member) : ?>
                            <?php if (empty($member['member_name']) || empty($member['member_role'])) continue; ?>
                            <?php $avatar_url = !empty($member['member_photo']) ? $member['member_photo'] : WP_SMS_URL . 'assets/images/avatar.png'; ?>

                            <a href="<?php echo esc_attr($member['contact_link']) ?>" target="_blank" class="wpsms-chatbox__team">
                                <div class="wpsms-chatbox__team-avatar">
                                    <span class="wpsms-chatbox__team-icon messenger" style="<?php echo esc_attr($general_background) ?>">
                                    <img src="<?php echo esc_attr($member['contact_link_icon']) ?>"/>
                                    </span>
                                    <img class="wpsms-chatbox__team-avatar-img" src="<?php echo esc_url($avatar_url); ?>" loading="lazy" width="56" height="56" alt="<?php echo esc_attr($member['member_name']); ?>"></div>
                                <div class="wpsms-chatbox__team-info">
                                    <ul class="wpsms-chatbox__team-list">
                                        <li class="wpsms-chatbox__team-item">
                                            <?php echo esc_html($member['member_role']) ?>
                                        </li>
                                        <li class="wpsms-chatbox__team-item wpsms-chatbox__team-name">
                                            <?php echo esc_html($member['member_name']) ?>
                                        </li>
                                        <li class="wpsms-chatbox__team-item wpsms-chatbox__team-status">
                                            <span class="online dot"></span>
                                            <span><?php echo esc_html($member['member_availability']) ?></span>
                                        </li>
                                    </ul>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
    
                <?php if ($chatbox->isLinkEnabled()) : ?>
                    <div class="wpsms-chatbox__articles">
                        <ul>
                            <li class="wpsms-chatbox__articles-header">
                                <?php echo esc_html($chatbox->getLinkTitle()); ?>
                            </li>
    
                            <?php foreach ($chatbox->fetchLinks() as $link) : ?>
                                <?php if (empty($link['chatbox_link_title'])) continue; ?>
    
                                <li class="wpsms-chatbox__article">
                                    <a href="<?php echo esc_url($link['chatbox_link_url']) ?>" title="<?php echo esc_attr($link['chatbox_link_title']) ?>">
                                        <?php echo esc_html($link['chatbox_link_title']); ?>
                                        <span>
                                            <?php if (is_rtl()) : ?>
                                                <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1 1L5 5L1 9" stroke="#4F7EF6" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <?php else : ?>
                                                <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 9L1 5L5 1" stroke="#4F7EF6" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="wpsms-chatbox__info">
            <div class="wpsms-chatbox__info--text">
                <?php echo esc_html($chatbox->getFooterText()); ?>

                <?php if ($chatbox->getFooterLinkUrl() && $chatbox->getFooterLinkTitle()) : ?>
                    <a href="<?php echo esc_url($chatbox->getFooterLinkUrl()); ?>"><?php echo esc_html($chatbox->getFooterLinkTitle()); ?></a>
                <?php endif; ?>
            </div>

            <?php if ($chatbox->isFooterLogoEnabled()) : ?>
                <div class="wpsms-chatbox__copy-right">
                    <?php esc_html_e('Powered By', 'wp-sms') ?>
                    <a href="https://wp-sms-pro.com/?utm_source=msg-btn&utm_medium=referral" target="_blank" title="<?php esc_html_e('WSMS', 'wp-sms') ?>">WSMS</a>
                </div>
            <?php endif; ?>
        </div>
        <span class="wpsms-chatbox__arrow"><i></i></span>
    </div>
</div>