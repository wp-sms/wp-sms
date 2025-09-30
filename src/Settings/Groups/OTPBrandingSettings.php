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
                    // Buttons
                    new Field([
                        'key'         => 'otp_primary_button_color',
                        'label'       => __('Primary Button', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for primary buttons', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_primary_button_label_color',
                        'label'       => __('Primary Button Label', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Text color for primary button labels', 'wp-sms'),
                        'default'     => '#ffffff',
                    ]),
                    new Field([
                        'key'         => 'otp_secondary_button_border_color',
                        'label'       => __('Sec Button Border', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Border color for secondary buttons', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
                    new Field([
                        'key'         => 'otp_secondary_button_label_color',
                        'label'       => __('Sec Button Label', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Text color for secondary button labels', 'wp-sms'),
                        'default'     => '#0073aa',
                    ]),
            
                    // Generic states
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
            
                    // Body + Widget
                    new Field([
                        'key'         => 'otp_body_color',
                        'label'       => __('Body', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Main text color for body content', 'wp-sms'),
                        'default'     => '#333333',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_background_color',
                        'label'       => __('Widget Bg', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for OTP widgets', 'wp-sms'),
                        'default'     => '#ffffff',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_border_color',
                        'label'       => __('Widget Border', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Border color for OTP widgets', 'wp-sms'),
                        'default'     => '#c9cace',
                    ]),
            
                    // All others (kept; not in the "don’t have" list)
                    new Field([
                        'key'         => 'otp_base_focus_color',
                        'label'       => __('Base Focus Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Base focus color for form elements', 'wp-sms'),
                        'default'     => '#635dff',
                    ]),
                    new Field([
                        'key'         => 'otp_base_hover_color',
                        'label'       => __('Base Hover Color', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Base hover color for interactive elements', 'wp-sms'),
                        'default'     => '#000000',
                    ]),
                    new Field([
                        'key'         => 'otp_input_labels_placeholders',
                        'label'       => __('Input Labels & Placeholders', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for input labels and placeholder text', 'wp-sms'),
                        'default'     => '#65676e',
                    ]),
                    new Field([
                        'key'         => 'otp_input_filled_text',
                        'label'       => __('Input Filled Text', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for text in filled input fields', 'wp-sms'),
                        'default'     => '#000000',
                    ]),
                    new Field([
                        'key'         => 'otp_input_border',
                        'label'       => __('Input Border', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Border color for input fields', 'wp-sms'),
                        'default'     => '#c9cace',
                    ]),
                    new Field([
                        'key'         => 'otp_input_background',
                        'label'       => __('Input Background', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Background color for input fields', 'wp-sms'),
                        'default'     => '#ffffff',
                    ]),
                    new Field([
                        'key'         => 'otp_icons_color',
                        'label'       => __('Icons', 'wp-sms'),
                        'type'        => 'color',
                        'description' => __('Color for icons and symbols', 'wp-sms'),
                        'default'     => '#65676e',
                    ]),
                    new Field([
                        'key'         => 'otp_captcha_widget_theme',
                        'label'       => __('Captcha Widget Theme', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Theme for captcha widgets', 'wp-sms'),
                        'options'     => [
                            'light' => __('Light', 'wp-sms'),
                            'dark'  => __('Dark', 'wp-sms'),
                            'auto'  => __('Auto (Based on user preference)', 'wp-sms'),
                        ],
                        'default'     => 'light',
                    ]),
                ]
            ]),
            new Section([
                'id'       => 'fonts',
                'title'    => __('Fonts', 'wp-sms'),
                'subtitle' => __('Configure typography settings for OTP forms', 'wp-sms'),
                'fields'   => [
                    new Field([
                        'key'         => 'otp_font_file',
                        'label'       => __('Font File', 'wp-sms'),
                        'type'        => 'media',
                        'description' => __('Upload your custom font file', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_font_link',
                        'label'       => __('Font Link', 'wp-sms'),
                        'type'        => 'url',
                        'description' => __('Enter the URL of your font (CSS file)', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_base_font_size',
                        'label'       => __('Base Size', 'wp-sms'),
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
                        'key'         => 'otp_widget_logo_position',
                        'label'       => __('Logo Position', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Position of the logo in the widget', 'wp-sms'),
                        'options'     => [
                            'left'   => __('Left', 'wp-sms'),
                            'center' => __('Center', 'wp-sms'),
                            'right'  => __('Right', 'wp-sms'),
                            'none'   => __('None', 'wp-sms'),
                        ],
                        'default'     => 'center',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_logo_url',
                        'label'       => __('Logo URL', 'wp-sms'),
                        'type'        => 'image',
                        'description' => __('Logo image', 'wp-sms'),
                    ]),
                    new Field([
                        'key'         => 'otp_widget_logo_height',
                        'label'       => __('Logo Height', 'wp-sms'),
                        'type'        => 'number',
                        'description' => __('Height of the logo in pixels', 'wp-sms'),
                        'default'     => 52,
                        'min'         => 1,
                        'max'         => 100,
                        'step'        => 1,
                    ]),
                    new Field([
                        'key'         => 'otp_widget_header_text_alignment',
                        'label'       => __('Header Text Alignment', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Alignment of header text in the widget', 'wp-sms'),
                        'options'     => [
                            'left'   => __('Left', 'wp-sms'),
                            'center' => __('Center', 'wp-sms'),
                            'right'  => __('Right', 'wp-sms'),
                        ],
                        'default'     => 'center',
                    ]),
                    new Field([
                        'key'         => 'otp_widget_social_buttons_layout',
                        'label'       => __('Social Buttons Layout', 'wp-sms'),
                        'type'        => 'select',
                        'description' => __('Layout position for social login buttons', 'wp-sms'),
                        'options'     => [
                            'bottom' => __('Bottom', 'wp-sms'),
                            'top'    => __('Top', 'wp-sms'),
                        ],
                        'default'     => 'bottom',
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
                    ])
                ]
            ]),
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
