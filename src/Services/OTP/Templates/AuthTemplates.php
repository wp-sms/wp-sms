<?php

namespace WP_SMS\Services\OTP\Templates;

/**
 * Authentication Page Templates
 *
 * Registers page templates for login, register, and combined auth forms.
 */
class AuthTemplates
{
    /**
     * Initialize templates
     */
    public function __construct()
    {
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        add_action('init', [$this, 'registerTemplates']);
        add_filter('theme_page_templates', [$this, 'addTemplates']);
        add_filter('template_include', [$this, 'loadTemplate']);
    }

    /**
     * Register custom page templates
     */
    public function registerTemplates(): void
    {
        // Templates are registered via theme_page_templates filter
    }

    /**
     * Add templates to the page template dropdown
     */
    public function addTemplates(array $templates): array
    {
        $templates['wp-sms-login.php'] = __('WP-SMS Login Form', 'wp-sms');
        $templates['wp-sms-register.php'] = __('WP-SMS Register Form', 'wp-sms');
        $templates['wp-sms-auth.php'] = __('WP-SMS Authentication Form', 'wp-sms');
        
        return $templates;
    }

    /**
     * Load the appropriate template file
     */
    public function loadTemplate(string $template): string
    {
        if (is_page()) {
            $page_template = get_post_meta(get_the_ID(), '_wp_page_template', true);
            
            switch ($page_template) {
                case 'wp-sms-login.php':
                    return $this->getTemplatePath('login');
                case 'wp-sms-register.php':
                    return $this->getTemplatePath('register');
                case 'wp-sms-auth.php':
                    return $this->getTemplatePath('auth');
            }
        }
        
        return $template;
    }

    /**
     * Get the template file path
     */
    protected function getTemplatePath(string $template): string
    {
        $template_file = WP_SMS_DIR . 'views/templates/auth/' . $template . '.php';
        
        if (file_exists($template_file)) {
            return $template_file;
        }
        
        // Fallback to default template
        return get_index_template();
    }
}
