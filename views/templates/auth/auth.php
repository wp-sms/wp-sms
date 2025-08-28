<?php
/**
 * Template Name: WP-SMS Authentication Form
 *
 * This template displays the WP-SMS combined authentication form with tabs.
 * Users can select this template when creating/editing pages.
 */

get_header(); ?>

<div class="wpsms-auth-page wpsms-auth-page--auth">
    <div class="wpsms-auth-page__container">
        <div class="wpsms-auth-page__content">
            <h1 class="wpsms-auth-page__title"><?php esc_html_e('Sign In or Create Account', 'wp-sms'); ?></h1>
            <p class="wpsms-auth-page__description"><?php esc_html_e('Choose to sign in to your existing account or create a new one.', 'wp-sms'); ?></p>
            
            <?php echo do_shortcode('[wpsms_auth_form tabs="true" default_tab="login" methods="password,otp,magic"]'); ?>
        </div>
    </div>
</div>

<?php get_footer(); ?>
