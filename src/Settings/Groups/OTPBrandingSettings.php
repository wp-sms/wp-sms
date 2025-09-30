<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class OTPBrandingSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'otp-branding';
    }

    public function getLabel(): string
    {
        return __('OTP Branding', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::PALETTE;
    }

    public function getLayout(): string
    {
        return 'sections-as-tabs';
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id'       => 'colors',
                'title'    => __('Colors', 'wp-sms'),
                'subtitle' => __('Customize the color scheme for OTP forms and widgets', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_primary_button_color',
                        'label'       => __('Primary Button Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for primary buttons', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_primary_button_label_color',
                        'label'       => __('Primary Button Label Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Text color for primary button labels', 'wp-sms'),
                        'default'     => '#ffffff',
                    ]),
                    new Field([
                        'key'         => 'otp_secondary_button_border_color',
                        'label'       => __('Secondary Button Border Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Border color for secondary buttons', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_secondary_button_label_color',
                        'label'       => __('Secondary Button Label Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Text color for secondary button labels', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_focus_color',
                        'label'       => __('Focus Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for focused form elements', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_hover_color',
                        'label'       => __('Hover Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for hover states', 'wp-sms'),
                        'default'     => '#005a87',
                    ]),
                    new Field([
                        'key'         => 'otp_link_color',
                        'label'       => __('Link Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for links and clickable elements', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_body_color',
                        'label'       => __('Body Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Main text color for body content', 'wp-sms'),
                        'default'     => '#333333',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_bg_color',
                        'label'       => __('Widget Background Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for OTP widgets', 'wp-sms'),
                        'default'     => '#ffffff',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_border_color',
                        'label'       => __('Widget Border Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Border color for OTP widgets', 'wp-sms'),
                        'default'     => '#e1e1e1',
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'fonts',
                'title'    => __('Fonts', 'wp-sms'),
                'subtitle' => __('Configure typography settings for OTP forms', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_font_family',
                        'label'       => __('Font Family', 'wp-sms'),
                        'type'        => 'advancedselect',
                        'description' => __('Choose a font family for OTP forms. You can upload a custom font or use a web font.', 'wp-sms'),
                        'options'     => [
                            'default' => __('Default (System Font)', 'wp-sms'),
                            'google'  => __('Google Fonts', 'wp-sms'),
                            'custom'  => __('Custom Font', 'wp-sms'),
                        ],
                        'default'     => 'default',
                    ]),
                    new Field([
                        'key'         => 'otp_google_font',
                        'label'       => __('Google Font', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Select a Google Font', 'wp-sms'),
                        'options'     => $this->getGoogleFonts(),
                        'show_if'     => ['otp_font_family' => 'google'],
                    ]),
                    new Field([
                        'key'         => 'otp_custom_font_url',
                        'label'       => __('Custom Font URL', 'wp-sms'),
                        'type'        => 'url',
                        'description' => __('Enter the URL of your custom font (CSS file)', 'wp-sms'),
                        'show_if'     => ['otp_font_family' => 'custom'],
                    ]),
                    new Field([
                        'key'         => 'otp_custom_font_name',
                        'label'       => __('Custom Font Name', 'wp-sms'),
                        'type'        => 'text',
                        'description' => __('Enter the font family name (e.g., "My Custom Font")', 'wp-sms'),
                        'show_if'     => ['otp_font_family' => 'custom'],
                    ]),
                    new Field([
                        'key'         => 'otp_base_font_size',
                        'label'       => __('Base Font Size', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Base font size in pixels', 'wp-sms'),
                        'default'     => 16,
                        'min'         => 12,
                        'max'         => 24,
                        'step'        => 1,
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'border',
                'title'    => __('Border & Style', 'wp-sms'),
                'subtitle' => __('Customize borders, radius, and visual effects', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_button_input_style',
                        'label'       => __('Buttons/Input Styles', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Choose the style for buttons and input fields', 'wp-sms'),
                        'options'     => [
                            'rounded'    => __('Rounded', 'wp-sms'),
                            'square'     => __('Square', 'wp-sms'),
                            'pill'       => __('Pill', 'wp-sms'),
                            'custom'     => __('Custom', 'wp-sms'),
                        ],
                        'default'     => 'rounded',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_border_radius',
                        'label'       => __('Widget Border Radius', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Border radius for widgets in pixels', 'wp-sms'),
                        'default'     => 8,
                        'min'         => 0,
                        'max'         => 50,
                        'step'        => 1,
                    ]),
                    new Field([
                        'key'         => 'otp_widget_border_weight',
                        'label'       => __('Widget Border Weight', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Border thickness for widgets in pixels', 'wp-sms'),
                        'default'     => 1,
                        'min'         => 0,
                        'max'         => 10,
                        'step'        => 1,
                    ]),
                    new Field([
                        'key'         => 'otp_show_widget_shadow',
                        'label'       => __('Show Widget Shadow', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Add a subtle shadow to OTP widgets', 'wp-sms'),
                        'default'     => true,
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'widgets',
                'title'    => __('Widgets', 'wp-sms'),
                'subtitle' => __('Configure widget appearance and branding', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_widget_logo',
                        'label'       => __('Widget Logo', 'wp-sms'),
                        'type'        => 'media',
                        'description' => __('Upload or select a logo to display in OTP widgets', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_widget_title',
                        'label'       => __('Widget Title', 'wp-sms'),
                        'type'        => 'text',
                        'description' => __('Title displayed in OTP widgets', 'wp-sms'),
                        'default'     => __('Verify Your Phone Number', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_widget_subtitle',
                        'label'       => __('Widget Subtitle', 'wp-sms'),
                        'type'        => 'textarea',
                        'description' => __('Subtitle or description text for OTP widgets', 'wp-sms'),
                        'default'     => __('Enter the verification code sent to your phone', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_widget_footer_text',
                        'label'       => __('Widget Footer Text', 'wp-sms'),
                        'type'        => 'textarea',
                        'description' => __('Footer text displayed in OTP widgets', 'wp-sms'),
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'links',
                'title'    => __('Links', 'wp-sms'),
                'subtitle' => __('Configure legal and policy links', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_terms_conditions_url',
                        'label'       => __('Terms and Conditions URL', 'wp-sms'),
                        'type'        => 'url',
                        'description' => __('Link to your terms and conditions page', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_privacy_policy_url',
                        'label'       => __('Privacy Policy URL', 'wp-sms'),
                        'type'        => 'url',
                        'description' => __('Link to your privacy policy page', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_show_legal_links',
                        'label'       => __('Show Legal Links', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Display terms and privacy policy links in OTP forms', 'wp-sms'),
                        'default'     => true,
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'branding',
                'title'    => __('Branding', 'wp-sms'),
                'subtitle' => __('Control WP SMS branding visibility', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_hide_wp_sms_branding',
                        'label'       => __('Hide WP SMS Branding', 'wp-sms'),
                        'type'        => 'checkbox',
                        'description' => __('Remove WP SMS branding from OTP forms (Pro feature)', 'wp-sms'),
                        'default'     => false,
                        'readonly'    => true,
                        'tag'         => Tags::COMING_SOON,
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'page_background',
                'title'    => __('Page Background', 'wp-sms'),
                'subtitle' => __('Configure the background appearance of OTP pages', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_page_layout',
                        'label'       => __('Page Layout', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Choose the layout for OTP pages', 'wp-sms'),
                        'options'     => [
                            'left'   => __('Left Aligned', 'wp-sms'),
                            'center' => __('Center Aligned', 'wp-sms'),
                            'right'  => __('Right Aligned', 'wp-sms'),
                        ],
                        'default'     => 'center',
                    ]),
                    new Field([
                        'key'         => 'otp_page_background_color',
                        'label'       => __('Background Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for OTP pages', 'wp-sms'),
                        'default'     => '#f8f9fa',
                    ]),
                    new Field([
                        'key'         => 'otp_page_background_image',
                        'label'       => __('Background Image', 'wp-sms'),
                        'type'        => 'media',
                        'description' => __('Upload a background image for OTP pages', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_page_background_repeat',
                        'label'       => __('Background Repeat', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('How the background image should repeat', 'wp-sms'),
                        'options'     => [
                            'no-repeat' => __('No Repeat', 'wp-sms'),
                            'repeat'    => __('Repeat', 'wp-sms'),
                            'repeat-x'  => __('Repeat Horizontally', 'wp-sms'),
                            'repeat-y'  => __('Repeat Vertically', 'wp-sms'),
                        ],
                        'default'     => 'no-repeat',
                        'show_if'     => ['otp_page_background_image' => '!empty'],
                    ]),
                    new Field([
                        'key'         => 'otp_page_background_size',
                        'label'       => __('Background Size', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('How the background image should be sized', 'wp-sms'),
                        'options'     => [
                            'auto'    => __('Auto', 'wp-sms'),
                            'cover'   => __('Cover', 'wp-sms'),
                            'contain' => __('Contain', 'wp-sms'),
                        ],
                        'default'     => 'cover',
                        'show_if'     => ['otp_page_background_image' => '!empty'],
                    ]),
                ]
            ]),
        ];
    }

    /**
     * Get list of popular Google Fonts
     *
     * @return array
     */
    private function getGoogleFonts(): array
    {
        return [
            'Open Sans'           => 'Open Sans',
            'Roboto'              => 'Roboto',
            'Lato'                => 'Lato',
            'Montserrat'          => 'Montserrat',
            'Source Sans Pro'     => 'Source Sans Pro',
            'Poppins'             => 'Poppins',
            'Nunito'              => 'Nunito',
            'Inter'               => 'Inter',
            'Playfair Display'    => 'Playfair Display',
            'Merriweather'        => 'Merriweather',
            'PT Sans'             => 'PT Sans',
            'Ubuntu'              => 'Ubuntu',
            'Crimson Text'        => 'Crimson Text',
            'Fira Sans'           => 'Fira Sans',
            'Libre Baskerville'   => 'Libre Baskerville',
            'Droid Sans'          => 'Droid Sans',
            'Droid Serif'         => 'Droid Serif',
            'Oswald'              => 'Oswald',
            'Raleway'             => 'Raleway',
            'PT Serif'            => 'PT Serif',
        ];
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }

        return $allFields;
    }
}
