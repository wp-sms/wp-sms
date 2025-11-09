<?php
/**
 * Profile Tab Template
 * 
 * User profile management with email/phone verification
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wpsms-profile-section">
    
    <div class="wpsms-section-header">
        <h2><?php _e('Profile Information', 'wp-sms'); ?></h2>
        <p class="wpsms-section-description">
            <?php _e('Manage your personal information and contact details', 'wp-sms'); ?>
        </p>
    </div>

    <!-- Loading State -->
    <div class="wpsms-loading" id="profile-loading">
        <div class="wpsms-spinner"></div>
        <p><?php _e('Loading profile...', 'wp-sms'); ?></p>
    </div>

    <!-- Profile Form -->
    <form id="wpsms-profile-form" class="wpsms-form" style="display: none;">
        
        <!-- Personal Information -->
        <div class="wpsms-form-section">
            <h3><?php _e('Personal Information', 'wp-sms'); ?></h3>
            
            <div class="wpsms-form-row">
                <div class="wpsms-form-field">
                    <label for="first_name"><?php _e('First Name', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="first_name" 
                        name="first_name" 
                        class="wpsms-input"
                        autocomplete="given-name">
                </div>

                <div class="wpsms-form-field">
                    <label for="last_name"><?php _e('Last Name', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        class="wpsms-input"
                        autocomplete="family-name">
                </div>
            </div>

            <div class="wpsms-form-field">
                <label for="display_name"><?php _e('Display Name', 'wp-sms'); ?></label>
                <input 
                    type="text" 
                    id="display_name" 
                    name="display_name" 
                    class="wpsms-input"
                    autocomplete="name">
            </div>

            <div class="wpsms-form-field">
                <label for="username"><?php _e('Username', 'wp-sms'); ?></label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    class="wpsms-input" 
                    disabled
                    autocomplete="username">
                <p class="wpsms-field-hint"><?php _e('Username cannot be changed', 'wp-sms'); ?></p>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="wpsms-form-section">
            <h3><?php _e('Contact Information', 'wp-sms'); ?></h3>
            
            <!-- Email -->
            <div class="wpsms-form-field">
                <label for="email"><?php _e('Email Address', 'wp-sms'); ?></label>
                <div class="wpsms-input-group">
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="wpsms-input" 
                        readonly
                        autocomplete="email">
                    <span class="wpsms-verified-badge" id="email-verified-badge"></span>
                </div>
                <button 
                    type="button" 
                    class="wpsms-btn wpsms-btn-secondary wpsms-btn-sm" 
                    id="change-email-btn">
                    <?php _e('Change Email', 'wp-sms'); ?>
                </button>
            </div>

            <!-- Phone -->
            <div class="wpsms-form-field">
                <label for="phone"><?php _e('Phone Number', 'wp-sms'); ?></label>
                <div class="wpsms-input-group">
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="wpsms-input" 
                        readonly
                        autocomplete="tel"
                        placeholder="<?php _e('Not set', 'wp-sms'); ?>">
                    <span class="wpsms-verified-badge" id="phone-verified-badge"></span>
                </div>
                <button 
                    type="button" 
                    class="wpsms-btn wpsms-btn-secondary wpsms-btn-sm" 
                    id="change-phone-btn">
                    <?php _e('Change Phone', 'wp-sms'); ?>
                </button>
            </div>
        </div>

        <!-- Save Button -->
        <div class="wpsms-form-actions">
            <button type="submit" class="wpsms-btn wpsms-btn-primary">
                <?php _e('Save Changes', 'wp-sms'); ?>
            </button>
        </div>

    </form>

    <!-- Error State -->
    <div class="wpsms-error" id="profile-error" style="display: none;">
        <p><?php _e('Failed to load profile. Please refresh the page.', 'wp-sms'); ?></p>
    </div>

</div>

<!-- Email Change Modal -->
<div class="wpsms-modal" id="email-change-modal" style="display: none;">
    <div class="wpsms-modal-overlay"></div>
    <div class="wpsms-modal-content">
        <div class="wpsms-modal-header">
            <h3><?php _e('Change Email Address', 'wp-sms'); ?></h3>
            <button class="wpsms-modal-close" aria-label="<?php _e('Close', 'wp-sms'); ?>">&times;</button>
        </div>
        <div class="wpsms-modal-body">
            <form id="email-change-form">
                <div class="wpsms-form-field">
                    <label for="new_email"><?php _e('New Email Address', 'wp-sms'); ?></label>
                    <input 
                        type="email" 
                        id="new_email" 
                        name="new_email" 
                        class="wpsms-input" 
                        required
                        autocomplete="email">
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Send Verification Code', 'wp-sms'); ?>
                </button>
            </form>

            <form id="email-verify-form" style="display: none;">
                <p class="wpsms-info-text" id="email-verify-message"></p>
                <div class="wpsms-form-field">
                    <label for="email_code"><?php _e('Verification Code', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="email_code" 
                        name="email_code" 
                        class="wpsms-input wpsms-input-code" 
                        required
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code">
                </div>
                <div class="wpsms-form-actions">
                    <button type="submit" class="wpsms-btn wpsms-btn-primary">
                        <?php _e('Verify', 'wp-sms'); ?>
                    </button>
                    <button type="button" class="wpsms-btn wpsms-btn-secondary" id="email-resend-btn">
                        <?php _e('Resend Code', 'wp-sms'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Phone Change Modal -->
<div class="wpsms-modal" id="phone-change-modal" style="display: none;">
    <div class="wpsms-modal-overlay"></div>
    <div class="wpsms-modal-content">
        <div class="wpsms-modal-header">
            <h3><?php _e('Change Phone Number', 'wp-sms'); ?></h3>
            <button class="wpsms-modal-close" aria-label="<?php _e('Close', 'wp-sms'); ?>">&times;</button>
        </div>
        <div class="wpsms-modal-body">
            <form id="phone-change-form">
                <div class="wpsms-form-field">
                    <label for="new_phone"><?php _e('New Phone Number', 'wp-sms'); ?></label>
                    <input 
                        type="tel" 
                        id="new_phone" 
                        name="new_phone" 
                        class="wpsms-input" 
                        required
                        autocomplete="tel"
                        placeholder="+1234567890">
                    <p class="wpsms-field-hint"><?php _e('Include country code (e.g., +1234567890)', 'wp-sms'); ?></p>
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Send Verification Code', 'wp-sms'); ?>
                </button>
            </form>

            <form id="phone-verify-form" style="display: none;">
                <p class="wpsms-info-text" id="phone-verify-message"></p>
                <div class="wpsms-form-field">
                    <label for="phone_code"><?php _e('Verification Code', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="phone_code" 
                        name="phone_code" 
                        class="wpsms-input wpsms-input-code" 
                        required
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code">
                </div>
                <div class="wpsms-form-actions">
                    <button type="submit" class="wpsms-btn wpsms-btn-primary">
                        <?php _e('Verify', 'wp-sms'); ?>
                    </button>
                    <button type="button" class="wpsms-btn wpsms-btn-secondary" id="phone-resend-btn">
                        <?php _e('Resend Code', 'wp-sms'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

