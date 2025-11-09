<?php
/**
 * MFA Management Template
 * 
 * Manage multi-factor authentication factors
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wpsms-mfa-section">
    
    <div class="wpsms-section-header">
        <h2><?php _e('Multi-Factor Authentication', 'wp-sms'); ?></h2>
        <p class="wpsms-section-description">
            <?php _e('Add an extra layer of security to your account', 'wp-sms'); ?>
        </p>
    </div>

    <!-- Loading State -->
    <div class="wpsms-loading" id="mfa-loading">
        <div class="wpsms-spinner"></div>
        <p><?php _e('Loading MFA factors...', 'wp-sms'); ?></p>
    </div>

    <!-- MFA Content -->
    <div id="mfa-content" style="display: none;">
        
        <!-- Status Banner -->
        <div class="wpsms-status-banner" id="mfa-status-banner">
            <div class="wpsms-status-icon"></div>
            <div class="wpsms-status-text">
                <strong id="mfa-status-title"></strong>
                <p id="mfa-status-description"></p>
            </div>
        </div>

        <!-- Enrolled Factors -->
        <div class="wpsms-factors-list" id="enrolled-factors-list">
            <h3><?php _e('Enrolled Factors', 'wp-sms'); ?></h3>
            <div id="factors-container"></div>
        </div>

        <!-- Available Factors -->
        <div class="wpsms-factors-add">
            <h3><?php _e('Add New Factor', 'wp-sms'); ?></h3>
            
            <!-- Email MFA -->
            <div class="wpsms-factor-card wpsms-factor-available" id="add-email-mfa-card">
                <div class="wpsms-factor-info">
                    <div class="wpsms-factor-icon">📧</div>
                    <div>
                        <h4><?php _e('Email OTP', 'wp-sms'); ?></h4>
                        <p><?php _e('Receive verification codes via email', 'wp-sms'); ?></p>
                    </div>
                </div>
                <button 
                    type="button" 
                    class="wpsms-btn wpsms-btn-secondary" 
                    id="add-email-mfa-btn">
                    <?php _e('Add', 'wp-sms'); ?>
                </button>
            </div>

            <!-- Phone MFA -->
            <div class="wpsms-factor-card wpsms-factor-available" id="add-phone-mfa-card">
                <div class="wpsms-factor-info">
                    <div class="wpsms-factor-icon">📱</div>
                    <div>
                        <h4><?php _e('Phone OTP', 'wp-sms'); ?></h4>
                        <p><?php _e('Receive verification codes via SMS', 'wp-sms'); ?></p>
                    </div>
                </div>
                <button 
                    type="button" 
                    class="wpsms-btn wpsms-btn-secondary" 
                    id="add-phone-mfa-btn">
                    <?php _e('Add', 'wp-sms'); ?>
                </button>
            </div>

            <!-- TOTP (Coming Soon) -->
            <div class="wpsms-factor-card wpsms-factor-disabled">
                <div class="wpsms-factor-info">
                    <div class="wpsms-factor-icon">🔐</div>
                    <div>
                        <h4><?php _e('Authenticator App (TOTP)', 'wp-sms'); ?></h4>
                        <p><?php _e('Use Google Authenticator, Authy, or similar apps', 'wp-sms'); ?></p>
                    </div>
                </div>
                <span class="wpsms-badge wpsms-badge-coming-soon"><?php _e('Coming Soon', 'wp-sms'); ?></span>
            </div>

            <!-- Biometric (Coming Soon) -->
            <div class="wpsms-factor-card wpsms-factor-disabled">
                <div class="wpsms-factor-info">
                    <div class="wpsms-factor-icon">👆</div>
                    <div>
                        <h4><?php _e('Biometric / Security Key', 'wp-sms'); ?></h4>
                        <p><?php _e('Use fingerprint, Face ID, or hardware security keys', 'wp-sms'); ?></p>
                    </div>
                </div>
                <span class="wpsms-badge wpsms-badge-coming-soon"><?php _e('Coming Soon', 'wp-sms'); ?></span>
            </div>

            <!-- Backup Codes (Coming Soon) -->
            <div class="wpsms-factor-card wpsms-factor-disabled">
                <div class="wpsms-factor-info">
                    <div class="wpsms-factor-icon">🔑</div>
                    <div>
                        <h4><?php _e('Backup Codes', 'wp-sms'); ?></h4>
                        <p><?php _e('Generate one-time backup codes for account recovery', 'wp-sms'); ?></p>
                    </div>
                </div>
                <span class="wpsms-badge wpsms-badge-coming-soon"><?php _e('Coming Soon', 'wp-sms'); ?></span>
            </div>

        </div>
    </div>

    <!-- Error State -->
    <div class="wpsms-error" id="mfa-error" style="display: none;">
        <p><?php _e('Failed to load MFA factors. Please refresh the page.', 'wp-sms'); ?></p>
    </div>

</div>

<!-- Add Email MFA Modal -->
<div class="wpsms-modal" id="email-mfa-modal" style="display: none;">
    <div class="wpsms-modal-overlay"></div>
    <div class="wpsms-modal-content">
        <div class="wpsms-modal-header">
            <h3><?php _e('Add Email MFA', 'wp-sms'); ?></h3>
            <button class="wpsms-modal-close" aria-label="<?php _e('Close', 'wp-sms'); ?>">&times;</button>
        </div>
        <div class="wpsms-modal-body">
            <form id="email-mfa-add-form">
                <div class="wpsms-form-field">
                    <label for="mfa_email"><?php _e('Email Address', 'wp-sms'); ?></label>
                    <input 
                        type="email" 
                        id="mfa_email" 
                        name="mfa_email" 
                        class="wpsms-input" 
                        required
                        autocomplete="email">
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Send Verification Code', 'wp-sms'); ?>
                </button>
            </form>

            <form id="email-mfa-verify-form" style="display: none;">
                <p class="wpsms-info-text" id="email-mfa-verify-message"></p>
                <div class="wpsms-form-field">
                    <label for="email_mfa_code"><?php _e('Verification Code', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="email_mfa_code" 
                        name="email_mfa_code" 
                        class="wpsms-input wpsms-input-code" 
                        required
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code">
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Verify & Add', 'wp-sms'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Add Phone MFA Modal -->
<div class="wpsms-modal" id="phone-mfa-modal" style="display: none;">
    <div class="wpsms-modal-overlay"></div>
    <div class="wpsms-modal-content">
        <div class="wpsms-modal-header">
            <h3><?php _e('Add Phone MFA', 'wp-sms'); ?></h3>
            <button class="wpsms-modal-close" aria-label="<?php _e('Close', 'wp-sms'); ?>">&times;</button>
        </div>
        <div class="wpsms-modal-body">
            <form id="phone-mfa-add-form">
                <div class="wpsms-form-field">
                    <label for="mfa_phone"><?php _e('Phone Number', 'wp-sms'); ?></label>
                    <input 
                        type="tel" 
                        id="mfa_phone" 
                        name="mfa_phone" 
                        class="wpsms-input" 
                        required
                        autocomplete="tel"
                        placeholder="+1234567890">
                    <p class="wpsms-field-hint"><?php _e('Include country code', 'wp-sms'); ?></p>
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Send Verification Code', 'wp-sms'); ?>
                </button>
            </form>

            <form id="phone-mfa-verify-form" style="display: none;">
                <p class="wpsms-info-text" id="phone-mfa-verify-message"></p>
                <div class="wpsms-form-field">
                    <label for="phone_mfa_code"><?php _e('Verification Code', 'wp-sms'); ?></label>
                    <input 
                        type="text" 
                        id="phone_mfa_code" 
                        name="phone_mfa_code" 
                        class="wpsms-input wpsms-input-code" 
                        required
                        maxlength="6"
                        pattern="[0-9]*"
                        inputmode="numeric"
                        autocomplete="one-time-code">
                </div>
                <button type="submit" class="wpsms-btn wpsms-btn-primary">
                    <?php _e('Verify & Add', 'wp-sms'); ?>
                </button>
            </form>
        </div>
    </div>
</div>

