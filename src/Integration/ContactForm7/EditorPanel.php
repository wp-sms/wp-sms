<?php

namespace WSms\Integration\ContactForm7;

use WSms\Messaging\Gateway\GatewayRegistry;

defined('ABSPATH') || exit;

class EditorPanel
{
    private const NOTIFICATION_DEFAULTS = [
        'active'        => false,
        'channel'       => 'sms',
        'to'            => '',
        'subject'       => '',
        'body'          => '',
        'exclude_blank' => false,
    ];

    private const ALLOWED_CHANNELS = ['sms', 'email', 'whatsapp', 'telegram'];

    private const TO_PLACEHOLDERS = [
        'sms'      => '[your-phone]',
        'email'    => '[your-email]',
        'whatsapp' => '[your-phone]',
        'telegram' => 'chat_id',
    ];

    public function __construct(
        private readonly GatewayRegistry $gatewayRegistry,
    ) {
    }

    public function registerHooks(): void
    {
        add_filter('wpcf7_pre_construct_contact_form_properties', [$this, 'registerProperties'], 10, 1);
        add_filter('wpcf7_editor_panels', [$this, 'addEditorPanel'], 10, 1);
        add_action('wpcf7_save_contact_form', [$this, 'saveContactForm'], 10, 1);
        add_action('admin_print_footer_scripts', [$this, 'printEditorScript']);
    }

    /**
     * Register wsms_notification and wsms_notification_2 with defaults.
     */
    public function registerProperties(array $properties): array
    {
        if (!isset($properties['wsms_notification'])) {
            $properties['wsms_notification'] = self::NOTIFICATION_DEFAULTS;
        }

        if (!isset($properties['wsms_notification_2'])) {
            $properties['wsms_notification_2'] = self::NOTIFICATION_DEFAULTS;
        }

        return $properties;
    }

    /**
     * Add "WSMS" panel to the CF7 editor.
     */
    public function addEditorPanel(array $panels): array
    {
        $panels['wsms-notification'] = [
            'title'    => __('WSMS', 'wp-sms'),
            'callback' => [$this, 'renderPanel'],
        ];

        return $panels;
    }

    /**
     * Render the WSMS notification panel inside the CF7 editor.
     */
    public function renderPanel(\WPCF7_ContactForm $contactForm): void
    {
        $configuredChannels = $this->gatewayRegistry->getConfiguredChannels();

        echo '<h2>' . esc_html__('WSMS Notifications', 'wp-sms') . '</h2>';

        if (empty($configuredChannels)) {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__('No messaging gateway configured. Configure a gateway in WSMS settings.', 'wp-sms');
            echo '</p></div>';
        }

        $this->renderNotificationBox($contactForm, $configuredChannels, [
            'id'    => 'wsms-notification',
            'name'  => 'wsms_notification',
            'title' => __('Notification', 'wp-sms'),
            'use'   => null,
        ]);

        echo '<br class="clear" />';

        $this->renderNotificationBox($contactForm, $configuredChannels, [
            'id'    => 'wsms-notification-2',
            'name'  => 'wsms_notification_2',
            'title' => __('Notification (2)', 'wp-sms'),
            'use'   => __('Use Notification (2)', 'wp-sms'),
        ]);
    }

    /**
     * Render a single notification box (primary or secondary).
     */
    private function renderNotificationBox(
        \WPCF7_ContactForm $contactForm,
        array $configuredChannels,
        array $args,
    ): void {
        $config = wp_parse_args(
            (array) $contactForm->prop($args['name']),
            self::NOTIFICATION_DEFAULTS,
        );

        $id   = $args['id'];
        $name = $args['name'];

        echo '<div class="contact-form-editor-box-mail" id="' . esc_attr($id) . '">';

        // Toggle checkbox for secondary notification
        if ($args['use'] !== null) {
            $checked = $config['active'] ? ' checked="checked"' : '';
            echo '<h2>' . esc_html($args['title']) . '</h2>';
            echo '<label>';
            echo '<input type="checkbox" name="' . esc_attr($name) . '[active]" value="1"' . $checked;
            echo ' data-toggle="' . esc_attr($id) . '-fieldset" />';
            echo ' ' . esc_html($args['use']);
            echo '</label>';
            echo '<p class="description">';
            echo esc_html__('Notification (2) is an additional notification often used as an autoresponder.', 'wp-sms');
            echo '</p>';
            echo '<fieldset id="' . esc_attr($id) . '-fieldset">';
        } else {
            echo '<h2>' . esc_html($args['title']) . '</h2>';
            // Primary is always "active" — just controlled by having config
            echo '<input type="hidden" name="' . esc_attr($name) . '[active]" value="1" />';
        }

        // Available mail-tags
        echo '<div class="wsms-mail-tags">';
        echo '<p class="description">' . esc_html__('Available mail-tags:', 'wp-sms') . '</p>';
        echo '<p>' . $contactForm->suggest_mail_tags($name) . '</p>';
        echo '</div>';

        // Channel select
        echo '<table class="form-table"><tbody>';

        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($id) . '-channel">';
        echo esc_html__('Channel', 'wp-sms') . '</label></th>';
        echo '<td>';
        echo '<select id="' . esc_attr($id) . '-channel" name="' . esc_attr($name) . '[channel]"';
        echo ' class="wsms-channel-select" data-box="' . esc_attr($id) . '"';
        if (empty($configuredChannels)) {
            echo ' disabled="disabled"';
        }
        echo '>';
        $channelLabels = [
            'sms'      => __('SMS', 'wp-sms'),
            'email'    => __('Email', 'wp-sms'),
            'whatsapp' => __('WhatsApp', 'wp-sms'),
            'telegram' => __('Telegram', 'wp-sms'),
        ];
        foreach (self::ALLOWED_CHANNELS as $channel) {
            if (!in_array($channel, $configuredChannels, true)) {
                continue;
            }
            $selected = $config['channel'] === $channel ? ' selected="selected"' : '';
            $label = $channelLabels[$channel] ?? ucfirst($channel);
            echo '<option value="' . esc_attr($channel) . '"' . $selected . '>';
            echo esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '</td></tr>';

        // To field
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($id) . '-to">';
        echo esc_html__('To', 'wp-sms') . '</label></th>';
        echo '<td>';
        $toPlaceholder = self::TO_PLACEHOLDERS[$config['channel']] ?? '[your-phone]';
        echo '<input type="text" id="' . esc_attr($id) . '-to" name="' . esc_attr($name) . '[to]"';
        echo ' class="large-text" value="' . esc_attr($config['to']) . '"';
        echo ' placeholder="' . esc_attr($toPlaceholder) . '" />';
        echo '</td></tr>';

        // Subject field (email only)
        $subjectDisplay = $config['channel'] === 'email' ? '' : ' style="display:none"';
        echo '<tr class="wsms-email-only" data-box="' . esc_attr($id) . '"' . $subjectDisplay . '>';
        echo '<th scope="row"><label for="' . esc_attr($id) . '-subject">';
        echo esc_html__('Subject', 'wp-sms') . '</label></th>';
        echo '<td>';
        echo '<input type="text" id="' . esc_attr($id) . '-subject" name="' . esc_attr($name) . '[subject]"';
        echo ' class="large-text" value="' . esc_attr($config['subject']) . '" />';
        echo '</td></tr>';

        // Message body
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($id) . '-body">';
        echo esc_html__('Message Body', 'wp-sms') . '</label></th>';
        echo '<td>';
        echo '<textarea id="' . esc_attr($id) . '-body" name="' . esc_attr($name) . '[body]"';
        echo ' class="large-text" rows="10">' . esc_textarea($config['body']) . '</textarea>';
        echo '</td></tr>';

        // Exclude blank
        $excludeChecked = $config['exclude_blank'] ? ' checked="checked"' : '';
        echo '<tr>';
        echo '<th scope="row"></th>';
        echo '<td><label>';
        echo '<input type="checkbox" name="' . esc_attr($name) . '[exclude_blank]" value="1"' . $excludeChecked . ' />';
        echo ' ' . esc_html__('Exclude lines with blank mail-tags from output', 'wp-sms');
        echo '</label></td></tr>';

        echo '</tbody></table>';

        if ($args['use'] !== null) {
            echo '</fieldset>';
        }

        echo '</div>';
    }

    /**
     * Save notification config from POST data.
     */
    public function saveContactForm(\WPCF7_ContactForm $contactForm): void
    {
        $contactForm->set_properties([
            'wsms_notification'   => $this->sanitizeNotificationConfig('wsms_notification'),
            'wsms_notification_2' => $this->sanitizeNotificationConfig('wsms_notification_2'),
        ]);
    }

    /**
     * Sanitize a single notification config from $_POST.
     */
    public function sanitizeNotificationConfig(string $key): array
    {
        $raw = $_POST[$key] ?? [];

        if (!is_array($raw)) {
            return self::NOTIFICATION_DEFAULTS;
        }

        $channel = $raw['channel'] ?? 'sms';
        if (!in_array($channel, self::ALLOWED_CHANNELS, true)) {
            $channel = 'sms';
        }

        return [
            'active'        => !empty($raw['active']),
            'channel'       => $channel,
            'to'            => sanitize_text_field($raw['to'] ?? ''),
            'subject'       => sanitize_text_field($raw['subject'] ?? ''),
            'body'          => sanitize_textarea_field($raw['body'] ?? ''),
            'exclude_blank' => !empty($raw['exclude_blank']),
        ];
    }

    /**
     * Print JS in admin footer for channel-dependent field visibility toggling.
     *
     * Hooked to admin_print_footer_scripts because CF7's panel output
     * goes through wp_kses() which strips <script> tags.
     */
    public function printEditorScript(): void
    {
        $screen = get_current_screen();

        if (!$screen || $screen->id !== 'toplevel_page_wpcf7') {
            return;
        }

        ?>
        <script>
        (function() {
            var placeholders = <?php echo wp_json_encode(self::TO_PLACEHOLDERS); ?>;

            // Toggle Subject row visibility and To placeholder based on channel select
            document.querySelectorAll('.wsms-channel-select').forEach(function(select) {
                select.addEventListener('change', function() {
                    var box = this.getAttribute('data-box');
                    var rows = document.querySelectorAll('.wsms-email-only[data-box="' + box + '"]');
                    rows.forEach(function(row) {
                        row.style.display = select.value === 'email' ? '' : 'none';
                    });
                    var toInput = document.getElementById(box + '-to');
                    if (toInput) {
                        toInput.placeholder = placeholders[select.value] || '';
                    }
                });
            });
        })();
        </script>
        <?php
    }
}
