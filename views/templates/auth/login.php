<?php
/**
 * Template Name: WP-SMS Login Form
 *
 * This template displays the WP-SMS login form.
 * Users can select this template when creating/editing pages.
 */

get_header(); ?>

<div class="wpsms-auth-page wpsms-auth-page--login">
    <div class="wpsms-auth-page__container">
        <div class="wpsms-auth-page__content">
            <h1 class="wpsms-auth-page__title"><?php esc_html_e('Sign In', 'wp-sms'); ?></h1>
            <p class="wpsms-auth-page__description"><?php esc_html_e('Sign in to your account using any of the methods below.', 'wp-sms'); ?></p>
            
            <?php echo do_shortcode('[wpsms_login_form redirect="/" methods="password,otp,magic"]'); ?>
            
            <div class="wpsms-auth-page__footer">
                <p>
                    <?php esc_html_e("Don't have an account?", 'wp-sms'); ?>
                    <a href="<?php echo esc_url(home_url('/register')); ?>"><?php esc_html_e('Sign up here', 'wp-sms'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
