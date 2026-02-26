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

<aside class="wpsms-chatbox wpsms-chatbox--ltr <?php echo $chatbox->getButtonPosition() === 'bottom_right' ? 'wpsms-chatbox--right-side' : 'wpsms-chatbox--left-side' ?>" role="complementary" aria-label="<?php esc_attr_e('Chat widget', 'wp-sms'); ?>">
    <?php
    $button_style = $chatbox->getButtonStyle();
    $is_circle    = $button_style === 'icon_only';
    $show_icon    = $button_style !== 'text_only';
    $show_text    = $button_style !== 'icon_only';
    $shape_class  = $is_circle ? 'wpsms-chatbox__button--circle' : 'wpsms-chatbox__button--rounded';
    $text_only_class = $button_style === 'text_only' ? ' wpsms-chatbox__button--text-only' : '';
    ?>
    <button class="wpsms-chatbox__button js-wpsms-chatbox__button <?php echo esc_attr($shape_class . $text_only_class); ?> wpsms-chatbox--bobbles" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>" aria-expanded="false" aria-controls="wpsms-chatbox-panel">
        <?php if ($show_icon) : ?>
        <span class="wpsms-chatbox__button-icon messenger">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 17a2 2 0 0 1-2 2H6.828a2 2 0 0 0-1.414.586l-2.202 2.202A.71.71 0 0 1 2 21.286V5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2z"/></svg>
        </span>
        <?php endif; ?>
        <?php if ($show_text) : ?>
        <span class="wpsms-chatbox__button-title"><?php echo esc_html($chatbox->getButtonText()); ?></span>
        <?php endif; ?>
    </button>

    <div class="wpsms-chatbox__backdrop js-wpsms-chatbox__backdrop" aria-hidden="true"></div>

    <div id="wpsms-chatbox-panel" class="wpsms-chatbox__content <?php echo $chatbox->getAnimationEffect() ? 'wpsms-chatbox__content--' . esc_attr($chatbox->getAnimationEffect()) : '' ?>" role="dialog" aria-label="<?php echo esc_attr($chatbox->getTitle()); ?>">
        <header class="wpsms-chatbox__header" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>">
            <h2>
                <?php echo esc_html($chatbox->getTitle()); ?>
            </h2>
            <button class="wpsms-chatbox__close-button js-wpsms-chatbox__close-button" aria-label="<?php esc_attr_e('Close', 'wp-sms'); ?>">
                <span class="screen-reader-text"><?php esc_html_e('Close', 'wp-sms'); ?></span>
                <svg width="10" height="10" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M1 11L6 6L11 11M11 1L5.99905 6L1 1" stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </header>
        <div class="wpsms-chatbox__elements">
            <div class="wpsms-chatbox__container">
                <div class="wpsms-chatbox__teams">
                    <?php if ($chatbox->fetchTeamMembers()) : ?>
                        <?php foreach ($chatbox->fetchTeamMembers() as $member) : ?>
                            <?php if (empty($member['member_name']) || empty($member['member_role'])) continue; ?>
                            <?php $avatar_url = !empty($member['member_photo']) ? $member['member_photo'] : WP_SMS_URL . 'public/images/avatar.png'; ?>

                            <a href="<?php echo esc_url($member['contact_link'], ['https', 'http', 'tel', 'sms', 'mailto']) ?>" target="_blank" rel="noopener noreferrer" class="wpsms-chatbox__team">
                                <div class="wpsms-chatbox__team-avatar">
                                    <span class="wpsms-chatbox__team-icon messenger" style="<?php echo esc_attr($general_background) ?>">
                                    <img src="<?php echo esc_url($member['contact_link_icon']) ?>" alt="" aria-hidden="true"/>
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
                    <nav class="wpsms-chatbox__articles" aria-label="<?php echo esc_attr($chatbox->getLinkTitle()); ?>">
                        <ul>
                            <li class="wpsms-chatbox__articles-header">
                                <?php echo esc_html($chatbox->getLinkTitle()); ?>
                            </li>

                            <?php foreach ($chatbox->fetchLinks() as $link) : ?>
                                <?php if (empty($link['chatbox_link_title'])) continue; ?>

                                <li class="wpsms-chatbox__article">
                                    <a href="<?php echo esc_url($link['chatbox_link_url']) ?>" title="<?php echo esc_attr($link['chatbox_link_title']) ?>">
                                        <?php echo esc_html($link['chatbox_link_title']); ?>
                                        <span class="wpsms-chatbox__article-chevron">
                                            <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M1 9l4-4-4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <footer class="wpsms-chatbox__info">
            <div class="wpsms-chatbox__info--text">
                <?php echo esc_html($chatbox->getFooterText()); ?>

                <?php if ($chatbox->getFooterLinkUrl() && $chatbox->getFooterLinkTitle()) : ?>
                    <a href="<?php echo esc_url($chatbox->getFooterLinkUrl()); ?>"><?php echo esc_html($chatbox->getFooterLinkTitle()); ?></a>
                <?php endif; ?>
            </div>

            <?php if ($chatbox->isFooterLogoEnabled()) : ?>
                <div class="wpsms-chatbox__copy-right">
                    <?php esc_html_e('Powered By', 'wp-sms') ?>
                    <a href="https://wsms.io/?utm_source=msg-btn&utm_medium=referral" target="_blank" title="<?php esc_html_e('WSMS', 'wp-sms') ?>">WSMS</a>
                </div>
            <?php endif; ?>
        </footer>
        <span class="wpsms-chatbox__arrow"><i></i></span>
    </div>
</aside>