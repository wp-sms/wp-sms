<?php
/**
 * Template Name: WP-SMS Register Form
 *
 * This template displays the WP-SMS registration form.
 * Users can select this template when creating/editing pages.
 */

get_header(); ?>

<div class="wpsms-auth-page wpsms-auth-page--register">
    <div class="wpsms-auth-page__container">
        <div class="wpsms-auth-page__content">
            <h1 class="wpsms-auth-page__title"><?php esc_html_e('Create Account', 'wp-sms'); ?></h1>
            <p class="wpsms-auth-page__description"><?php esc_html_e('Join us by creating a new account. Fill in your details below.', 'wp-sms'); ?></p>
            
            <?php echo do_shortcode('[wpsms_register_form fields="username,email,phone,password"]'); ?>
            
            <div class="wpsms-auth-page__footer">
                <p>
                    <?php esc_html_e('Already have an account?', 'wp-sms'); ?>
                    <a href="<?php echo esc_url(home_url('/login')); ?>"><?php esc_html_e('Sign in here', 'wp-sms'); ?></a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
