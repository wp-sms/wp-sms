<?php
/**
 * Admin user profile section — WSMS Authentication.
 *
 * @var int    $user_id
 * @var string $email
 * @var bool   $email_verified
 * @var string $phone
 * @var bool   $phone_verified
 * @var array  $mfa_factors
 * @var array  $social_accounts
 * @var string $logs_url
 * @var int    $activity_count
 * @var bool   $can_manage
 * @var array  $lockout          {locked: bool, until: ?string, attempts: int}
 * @var bool   $has_password
 * @var bool   $is_placeholder_email
 * @var string|null $registration_status
 * @var string|null $registration_created
 * @var bool   $phone_enabled
 * @var bool   $email_enabled
 */

defined('ABSPATH') || exit;

use WSms\Service\Admin\UserListManager;

$svgCheck = UserListManager::SVG_CIRCLE_CHECK;
$svgX = UserListManager::SVG_CIRCLE_X;
$svgShield = UserListManager::SVG_SHIELD_CHECK;
$svgLock = UserListManager::SVG_LOCK;
?>

<h2><?php esc_html_e('WSMS Authentication', 'wp-sms'); ?></h2>

<table class="form-table" role="presentation">
    <!-- Email Verification -->
    <tr>
        <th><?php esc_html_e('Email Verification', 'wp-sms'); ?></th>
        <td>
            <code><?php echo esc_html($email); ?></code>
            <?php if ($email_verified): ?>
                <span class="wsms-pill wsms-pill--verified wsms-profile-badge" title="<?php esc_attr_e('Email: verified', 'wp-sms'); ?>">
                    <?php echo $svgCheck; ?> <?php esc_html_e('Verified', 'wp-sms'); ?>
                </span>
            <?php else: ?>
                <span class="wsms-pill wsms-pill--unverified wsms-profile-badge" title="<?php esc_attr_e('Email: not verified', 'wp-sms'); ?>">
                    <?php echo $svgX; ?> <?php esc_html_e('Not Verified', 'wp-sms'); ?>
                </span>
            <?php endif; ?>
            <?php if ($can_manage): ?>
                <div class="wsms-profile-actions">
                    <?php if ($email_verified): ?>
                        <button type="button"
                                class="button button-small wsms-action"
                                data-action="verification"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                data-channel="email"
                                data-verified="false"
                        ><?php esc_html_e('Mark Unverified', 'wp-sms'); ?></button>
                    <?php else: ?>
                        <button type="button"
                                class="button button-small wsms-action"
                                data-action="verification"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                data-channel="email"
                                data-verified="true"
                        ><?php esc_html_e('Mark Verified', 'wp-sms'); ?></button>
                    <?php endif; ?>
                    <?php if (!$email_verified && $email_enabled && !$is_placeholder_email): ?>
                        <button type="button"
                                class="button button-small wsms-action"
                                data-action="send-verification"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                                data-channel="email"
                        ><?php esc_html_e('Send Verification', 'wp-sms'); ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Phone -->
    <?php if ($phone || $phone_enabled || $can_manage): ?>
    <tr>
        <th><?php esc_html_e('Phone', 'wp-sms'); ?></th>
        <td>
            <?php if ($phone): ?>
                <span class="wsms-phone-display">
                    <code><?php echo esc_html($phone); ?></code>
                    <?php if ($phone_verified): ?>
                        <span class="wsms-pill wsms-pill--verified wsms-profile-badge" title="<?php esc_attr_e('Phone: verified', 'wp-sms'); ?>">
                            <?php echo $svgCheck; ?> <?php esc_html_e('Verified', 'wp-sms'); ?>
                        </span>
                    <?php else: ?>
                        <span class="wsms-pill wsms-pill--unverified wsms-profile-badge" title="<?php esc_attr_e('Phone: not verified', 'wp-sms'); ?>">
                            <?php echo $svgX; ?> <?php esc_html_e('Not Verified', 'wp-sms'); ?>
                        </span>
                    <?php endif; ?>
                </span>
                <?php if ($can_manage): ?>
                    <div class="wsms-profile-actions">
                        <?php if ($phone_verified): ?>
                            <button type="button"
                                    class="button button-small wsms-action"
                                    data-action="verification"
                                    data-user-id="<?php echo esc_attr($user_id); ?>"
                                    data-channel="phone"
                                    data-verified="false"
                            ><?php esc_html_e('Mark Unverified', 'wp-sms'); ?></button>
                        <?php else: ?>
                            <button type="button"
                                    class="button button-small wsms-action"
                                    data-action="verification"
                                    data-user-id="<?php echo esc_attr($user_id); ?>"
                                    data-channel="phone"
                                    data-verified="true"
                            ><?php esc_html_e('Mark Verified', 'wp-sms'); ?></button>
                        <?php endif; ?>
                        <?php if (!$phone_verified && $phone_enabled): ?>
                            <button type="button"
                                    class="button button-small wsms-action"
                                    data-action="send-verification"
                                    data-user-id="<?php echo esc_attr($user_id); ?>"
                                    data-channel="phone"
                            ><?php esc_html_e('Send Verification', 'wp-sms'); ?></button>
                        <?php endif; ?>
                        <button type="button"
                                class="button button-small wsms-phone-edit-toggle"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                        ><?php esc_html_e('Edit', 'wp-sms'); ?></button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e('No phone number set', 'wp-sms'); ?></span>
                <?php if ($can_manage): ?>
                    <div class="wsms-profile-actions">
                        <button type="button"
                                class="button button-small wsms-phone-edit-toggle"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                        ><?php esc_html_e('Add Phone', 'wp-sms'); ?></button>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($can_manage): ?>
                <div class="wsms-phone-edit" style="display: none; margin-top: 8px;">
                    <input type="tel" class="wsms-phone-input" pattern="\+[0-9]+" placeholder="+1234567890" value="<?php echo esc_attr($phone); ?>" style="width: 200px;">
                    <button type="button" class="button button-small wsms-action" data-action="save-phone" data-user-id="<?php echo esc_attr($user_id); ?>"><?php esc_html_e('Save', 'wp-sms'); ?></button>
                    <button type="button" class="button button-small wsms-phone-cancel"><?php esc_html_e('Cancel', 'wp-sms'); ?></button>
                    <?php if ($phone): ?>
                        <button type="button" class="button button-small button-link-delete wsms-action" data-action="remove-phone" data-user-id="<?php echo esc_attr($user_id); ?>"><?php esc_html_e('Remove', 'wp-sms'); ?></button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Password -->
    <tr>
        <th><?php esc_html_e('Password', 'wp-sms'); ?></th>
        <td>
            <?php if ($has_password): ?>
                <span class="wsms-pill wsms-pill--verified wsms-profile-badge">
                    <?php echo $svgCheck; ?> <?php esc_html_e('Has password', 'wp-sms'); ?>
                </span>
            <?php else: ?>
                <span class="wsms-pill wsms-pill--neutral wsms-profile-badge">
                    <?php esc_html_e('No password', 'wp-sms'); ?>
                </span>
                <span style="color: #999; font-size: 12px;"><?php esc_html_e('Social login only', 'wp-sms'); ?></span>
            <?php endif; ?>
            <?php if ($can_manage && !$is_placeholder_email): ?>
                <div class="wsms-profile-actions">
                    <button type="button"
                            class="button button-small wsms-action"
                            data-action="password-reset"
                            data-user-id="<?php echo esc_attr($user_id); ?>"
                    ><?php esc_html_e('Send Reset Email', 'wp-sms'); ?></button>
                </div>
            <?php endif; ?>
        </td>
    </tr>

    <!-- MFA Factors -->
    <tr>
        <th><?php esc_html_e('MFA Factors', 'wp-sms'); ?></th>
        <td>
            <?php if (empty($mfa_factors)): ?>
                <span style="color: #999;"><?php esc_html_e('None enrolled', 'wp-sms'); ?></span>
            <?php else: ?>
                <?php foreach ($mfa_factors as $factor): ?>
                    <div class="wsms-mfa-row" data-channel="<?php echo esc_attr($factor['channel_id']); ?>">
                        <span class="wsms-pill wsms-pill--mfa wsms-profile-badge">
                            <?php echo $svgShield; ?> <?php echo esc_html($factor['name']); ?>
                        </span>
                        <?php if ($can_manage): ?>
                            <button type="button"
                                    class="button button-small wsms-action"
                                    data-action="reset-mfa"
                                    data-user-id="<?php echo esc_attr($user_id); ?>"
                                    data-channel="<?php echo esc_attr($factor['channel_id']); ?>"
                            ><?php esc_html_e('Reset', 'wp-sms'); ?></button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Social Accounts -->
    <tr>
        <th><?php esc_html_e('Social Accounts', 'wp-sms'); ?></th>
        <td>
            <?php if (empty($social_accounts)): ?>
                <span style="color: #999;"><?php esc_html_e('None linked', 'wp-sms'); ?></span>
            <?php else: ?>
                <?php foreach ($social_accounts as $account): ?>
                    <div class="wsms-social-row" data-provider="<?php echo esc_attr($account['provider']); ?>">
                        <span class="wsms-pill wsms-pill--social wsms-profile-badge">
                            <?php echo esc_html(ucfirst($account['provider'])); ?>
                        </span>
                        <?php if ($account['email']): ?>
                            <span style="color: #666;"><?php echo esc_html($account['email']); ?></span>
                        <?php endif; ?>
                        <?php if ($can_manage): ?>
                            <button type="button"
                                    class="button button-small wsms-action"
                                    data-action="disconnect-social"
                                    data-user-id="<?php echo esc_attr($user_id); ?>"
                                    data-provider="<?php echo esc_attr($account['provider']); ?>"
                            ><?php esc_html_e('Disconnect', 'wp-sms'); ?></button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Account Lockout -->
    <tr>
        <th><?php esc_html_e('Account Lockout', 'wp-sms'); ?></th>
        <td class="wsms-lockout-cell">
            <?php if ($lockout['locked']): ?>
                <span class="wsms-pill wsms-pill--locked wsms-profile-badge">
                    <?php echo $svgLock; ?>
                    <?php
                    $until = date_i18n('H:i', strtotime($lockout['until']));
                    /* translators: %s: time when lockout expires */
                    printf(esc_html__('Locked until %s', 'wp-sms'), $until);
                    ?>
                </span>
                <span style="color: #666; font-size: 12px;">
                    <?php
                    /* translators: %d: number of failed attempts */
                    printf(esc_html__('%d failed attempts', 'wp-sms'), $lockout['attempts']);
                    ?>
                </span>
                <?php if ($can_manage): ?>
                    <div class="wsms-profile-actions">
                        <button type="button"
                                class="button button-small wsms-action"
                                data-action="unlock"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                        ><?php esc_html_e('Unlock', 'wp-sms'); ?></button>
                    </div>
                <?php endif; ?>
            <?php elseif ($lockout['attempts'] > 0): ?>
                <span style="color: #666;">
                    <?php
                    /* translators: %d: number of failed attempts */
                    printf(esc_html__('%d failed attempts', 'wp-sms'), $lockout['attempts']);
                    ?>
                </span>
                <?php if ($can_manage): ?>
                    <div class="wsms-profile-actions">
                        <button type="button"
                                class="button button-small wsms-action"
                                data-action="unlock"
                                data-user-id="<?php echo esc_attr($user_id); ?>"
                        ><?php esc_html_e('Reset Attempts', 'wp-sms'); ?></button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e('No lockout', 'wp-sms'); ?></span>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Registration Status (only for pending users) -->
    <?php if ($registration_status === 'pending'): ?>
    <tr class="wsms-registration-row">
        <th><?php esc_html_e('Registration', 'wp-sms'); ?></th>
        <td>
            <span class="wsms-pill wsms-pill--pending wsms-profile-badge">
                <?php esc_html_e('Pending', 'wp-sms'); ?>
            </span>
            <?php if ($registration_created): ?>
                <span style="color: #666; font-size: 12px;">
                    <?php echo esc_html($registration_created); ?>
                </span>
            <?php endif; ?>
            <?php if ($can_manage): ?>
                <div class="wsms-profile-actions">
                    <button type="button"
                            class="button button-small wsms-action"
                            data-action="activate"
                            data-user-id="<?php echo esc_attr($user_id); ?>"
                    ><?php esc_html_e('Activate', 'wp-sms'); ?></button>
                </div>
            <?php endif; ?>
        </td>
    </tr>
    <?php endif; ?>

    <!-- Auth Activity Link -->
    <tr>
        <th><?php esc_html_e('Auth Activity', 'wp-sms'); ?></th>
        <td>
            <?php if ($activity_count > 0): ?>
                <a href="<?php echo esc_url($logs_url); ?>">
                    <?php
                    /* translators: %d: number of log events */
                    printf(esc_html__('View %d events &rarr;', 'wp-sms'), $activity_count);
                    ?>
                </a>
            <?php else: ?>
                <span style="color: #999;"><?php esc_html_e('No activity recorded', 'wp-sms'); ?></span>
            <?php endif; ?>
        </td>
    </tr>
</table>
