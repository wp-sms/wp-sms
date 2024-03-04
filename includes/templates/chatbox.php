<?php
$general_color      = !empty($chatbox_text_color) ? sprintf('color: %s;', $chatbox_text_color) : '';
$general_background = !empty($chatbox_color) ? sprintf('background-color: %s;', $chatbox_color) : '';
$general_fill_color = !empty($chatbox_text_color) ? $chatbox_text_color : '#FFF';
$footer_color       = !empty($footer_text_color) ? sprintf('color: %s;', $footer_text_color) : '';
?>

<div class="wpsms-chatbox wpsms-chatbox--ltr  wpsms-chatbox--orange-theme" style="<?php echo isset($button_position) && $button_position === 'bottom_right' ? 'left: unset; right: 1rem' : '' ?>">
    <button class="wpsms-chatbox__button js-wpsms-chatbox__button wpsms-chatbox__button--rounded wpsms-chatbox__button--rounded wpsms-chatbox__button--has-arrow wpsms-chatbox--bobbles" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>">
        <span class="wpsms-chatbox__button-arrow">
           <svg xmlns="http://www.w3.org/2000/svg" width="12" height="8" fill="none"><g clip-path="url(#a)"><path stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-linecap="round" stroke-linejoin="round" stroke-miterlimit="10" stroke-width="2" d="m11 1.5-5 5-5-5"/></g><defs><clipPath id="a"><path fill="<?php echo esc_attr($general_fill_color) ?>" d="M0 .5h12v7H0z"/></clipPath></defs></svg>
        </span>
        <span class="wpsms-chatbox__button-icon messenger">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none"><path fill="<?php echo esc_attr($general_fill_color) ?>" d="M6 9a.968.968 0 0 0 .713-.288A.964.964 0 0 0 7 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 6 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 5 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 11 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 10 7a.965.965 0 0 0-.712.288A.972.972 0 0 0 9 8c0 .283.096.521.288.713.192.192.43.288.712.287Zm4 0a.968.968 0 0 0 .713-.288A.964.964 0 0 0 15 8a.965.965 0 0 0-.288-.712A.972.972 0 0 0 14 7a.965.965 0 0 0-.712.288A.973.973 0 0 0 13 8c0 .283.096.521.288.713.192.192.43.288.712.287ZM0 20V2C0 1.45.196.98.588.588A1.93 1.93 0 0 1 2 0h16c.55 0 1.021.196 1.413.588.392.392.588.863.587 1.412v12c0 .55-.196 1.021-.587 1.413A1.92 1.92 0 0 1 18 16H4l-4 4Zm3.15-6H18V2H2v13.125L3.15 14Z"/></svg>
        </span>
        <span class="wpsms-chatbox__button-title">
            <?php echo !empty($button_text) ? esc_html($button_text) : __('How Can I Help You?', 'wp-sms') ?>
        </span>
    </button>

    <div class="wpsms-chatbox__content <?php echo !empty($chatbox_animation) ? 'wpsms-chatbox__content--' . esc_attr($chatbox_animation) : '' ?>" style="<?php echo isset($button_position) && $button_position === 'bottom_right' ? 'left: unset; right: 0.5rem' : '' ?>">
        <div class="wpsms-chatbox__header" style="<?php echo esc_attr($general_color) . esc_attr($general_background) ?>">
            <h2 style="<?php echo esc_attr($general_color) ?>">
                <?php echo !empty($title) ? esc_html($title) : __('Meet Our Team', 'wp-sms'); ?>
            </h2>
            <button class="wpsms-chatbox__close-button js-wpsms-chatbox__close-button">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M1 11L6 6L11 11M11 1L5.99905 6L1 1" stroke="<?php echo esc_attr($general_fill_color) ?>" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </button>
        </div>
        <div class="wpsms-chatbox__container">
            <div class="wpsms-chatbox__teams">
                <?php if (!empty($team_members)) : ?>
                    <?php foreach($team_members as $member) : ?>
                        <?php 
                            if (empty($member['member_name']) || empty($member['member_role'])) continue; 

                            if ($member['member_contact_type'] === 'whatsapp') {
                                $contact_link = 'https://wa.me/' . $member['member_contact_value'];
                            } else if ($member['member_contact_type'] === 'telegram') {
                                $contact_link = 'https://t.me/' . $member['member_contact_value'];
                            } else if ($member['member_contact_type'] === 'facebook') {
                                $contact_link = 'https://me.me/' . $member['member_contact_value'];
                            } else if ($member['member_contact_type'] === 'sms') {
                                $contact_link = 'sms:' . $member['member_contact_value'];
                            } else {
                                $contact_link = 'tel:' . $member['member_contact_value'];
                            }
                        ?>
                        
                        <a href="<?php echo esc_attr($contact_link) ?>" target="_blank" class="wpsms-chatbox__team">
                            <div class="wpsms-chatbox__team-avatar">
                                <span class="wpsms-chatbox__team-icon messenger" style="<?php echo esc_attr($general_background) ?>">
                                    <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="12px" height="12px" viewBox="0 0 12 12" version="1.1"><g id="surface1"><path fill="<?php echo esc_attr($general_fill_color) ?>" style="stroke:none;fill-rule:nonzero;fill-opacity:1;" d="M 3.601562 5.398438 C 3.761719 5.402344 3.914062 5.339844 4.027344 5.226562 C 4.140625 5.113281 4.203125 4.960938 4.199219 4.800781 C 4.203125 4.640625 4.140625 4.484375 4.027344 4.371094 C 3.914062 4.257812 3.761719 4.195312 3.601562 4.199219 C 3.441406 4.195312 3.285156 4.257812 3.171875 4.371094 C 3.058594 4.484375 2.996094 4.640625 3 4.800781 C 3 4.96875 3.058594 5.113281 3.171875 5.226562 C 3.289062 5.34375 3.429688 5.402344 3.601562 5.398438 Z M 6 5.398438 C 6.160156 5.402344 6.316406 5.339844 6.429688 5.226562 C 6.542969 5.113281 6.605469 4.960938 6.601562 4.800781 C 6.605469 4.640625 6.542969 4.484375 6.425781 4.371094 C 6.316406 4.257812 6.160156 4.195312 6 4.199219 C 5.839844 4.195312 5.683594 4.257812 5.574219 4.371094 C 5.460938 4.484375 5.398438 4.640625 5.398438 4.800781 C 5.398438 4.96875 5.457031 5.113281 5.574219 5.226562 C 5.6875 5.34375 5.832031 5.402344 6 5.398438 Z M 8.398438 5.398438 C 8.558594 5.402344 8.714844 5.339844 8.828125 5.226562 C 8.941406 5.113281 9.003906 4.960938 9 4.800781 C 9.003906 4.640625 8.941406 4.484375 8.828125 4.371094 C 8.714844 4.257812 8.558594 4.195312 8.398438 4.199219 C 8.238281 4.195312 8.085938 4.257812 7.972656 4.371094 C 7.859375 4.484375 7.796875 4.640625 7.800781 4.800781 C 7.800781 4.96875 7.859375 5.113281 7.972656 5.226562 C 8.089844 5.34375 8.230469 5.402344 8.398438 5.398438 Z M 0 12 L 0 1.199219 C 0 0.871094 0.117188 0.589844 0.351562 0.351562 C 0.574219 0.125 0.882812 -0.00390625 1.199219 0 L 10.800781 0 C 11.128906 0 11.414062 0.117188 11.648438 0.351562 C 11.882812 0.589844 12 0.871094 12 1.199219 L 12 8.398438 C 12 8.730469 11.882812 9.011719 11.648438 9.246094 C 11.425781 9.476562 11.121094 9.605469 10.800781 9.601562 L 2.398438 9.601562 Z M 1.890625 8.398438 L 10.800781 8.398438 L 10.800781 1.199219 L 1.199219 1.199219 L 1.199219 9.074219 Z M 1.890625 8.398438 "/></g></svg>
                                </span>
                                <img class="wpsms-chatbox__team-avatar-img" src="<?php echo !empty($member['member_photo']) ? esc_url($member['member_photo']) : esc_url(WP_SMS_URL . 'assets/images/avatar.png') ?>" loading="lazy" width="56" height="56" alt="<?php echo esc_attr($member['member_name']); ?>"></div>
                            <div class="wpsms-chatbox__team-info">
                                <ul class="wpsms-chatbox__team-list">
                                    <li class="wpsms-chatbox__team-item">
                                        <?php echo esc_html($member['member_role']) ?>
                                    </li>
                                    <li class="wpsms-chatbox__team-item wpsms-chatbox__team-name">
                                        <?php echo esc_html($member['member_name']) ?>
                                    </li>
                                    <li class="wpsms-chatbox__team-item wpsms-chatbox__team-status">
                                        <span class="online"></span>
                                        <?php echo esc_html($member['member_availability']) ?>
                                    </li>
                                </ul>
                            </div>
                        </a>        
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($links_enabled == true) : ?>
                <div class="wpsms-chatbox__articles">
                    <ul>
                        <li class="wpsms-chatbox__articles-header">
                            <?php echo !empty($links_title) ? esc_html($links_title) : __('Related Articles', 'wp-sms') ?>
                        </li>

                        <?php if (!empty($links)) : ?>
                            <?php foreach($links as $link) : ?>
                                <?php if (empty($link['chatbox_link_title'])) continue; ?>
                                
                                <li class="wpsms-chatbox__article">
                                    <a href="<?php echo esc_url($link['chatbox_link_url']) ?>" title="<?php echo esc_attr($link['chatbox_link_title']) ?>">
                                        <?php echo esc_html($link['chatbox_link_title']); ?>
                                        <span><svg xmlns="http://www.w3.org/2000/svg" width="6" height="9" viewBox="0 0 6 9" fill="none"><path d="M1 0.5L5 4.5L1 8.5" stroke="#4F7EF6" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <div class="wpsms-chatbox__info">
            <div style="<?php echo esc_attr($footer_color) ?>">
                <?php echo !empty($footer_text) ? esc_html($footer_text) : __('All rights reserved.', 'wp-sms'); ?>
                
                <?php if (!empty($footer_link_title) && !empty($footer_link_url)) : ?>
                    <a href="<?php echo esc_url($footer_link_url); ?>"><?php echo esc_html($footer_link_title); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <span class="wpsms-chatbox__arrow" style="<?php echo isset($button_position) && $button_position === 'bottom_right' ? 'left: unset; right: 2rem' : '' ?>"><i></i></span>
    </div>
</div>