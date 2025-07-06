<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Notification\NotificationFactory;

class FluentCRMSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'fluent_crm';
    }

    public function getLabel(): string
    {
        return __('Fluent CRM', 'wp-sms-fluent-integrations');
    }

    public function getIcon(): string
    {
        return LucideIcons::USERS;
    }

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $inactiveNotice = $isPluginActive ? '' : ' <em>(' . __('Plugin not active', 'wp-sms-fluent-integrations') . ')</em>';
        
        return [
            new Section([
                'id' => 'contact_subscribed',
                'title' => __('Contact Subscribed', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for contact subscription', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getContactSubscribedFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
                'order' => 1,
            ]),
            new Section([
                'id' => 'contact_unsubscribed',
                'title' => __('Contact Unsubscribed', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for contact unsubscription', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getContactUnsubscribedFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
                'order' => 2,
            ]),
            new Section([
                'id' => 'contact_pending',
                'title' => __('Contact Pending Subscription', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for contact pending subscription', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getContactPendingFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
                'order' => 3,
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

    /**
     * Get contact subscribed fields
     *
     * @return array
     */
    private function getContactSubscribedFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%contact_first_name%' => '',
            '%contact_last_name%'  => '',
            '%contact_email%'      => '',
            '%contact_phone%'      => '',
            '%contact_status%'     => '',
        ];

        return [
            new Field([
                'key' => 'fluent_crm_notif_contact_subscribed',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for contact subscription', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
            new Field([
                'key' => 'fluent_crm_notif_contact_subscribed_message',
                'label' => __('Message Body', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the contents of the SMS message', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
        ];
    }

    /**
     * Get contact unsubscribed fields
     *
     * @return array
     */
    private function getContactUnsubscribedFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%contact_first_name%' => '',
            '%contact_last_name%'  => '',
            '%contact_email%'      => '',
            '%contact_phone%'      => '',
            '%contact_status%'     => '',
        ];

        return [
            new Field([
                'key' => 'fluent_crm_notif_contact_unsubscribed',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for contact unsubscription', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
            new Field([
                'key' => 'fluent_crm_notif_contact_unsubscribed_message',
                'label' => __('Message Body', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the contents of the SMS message', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
        ];
    }

    /**
     * Get contact pending fields
     *
     * @return array
     */
    private function getContactPendingFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%contact_first_name%' => '',
            '%contact_last_name%'  => '',
            '%contact_email%'      => '',
            '%contact_phone%'      => '',
            '%contact_status%'     => '',
        ];

        return [
            new Field([
                'key' => 'fluent_crm_notif_contact_pending',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for contact pending subscription', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
            new Field([
                'key' => 'fluent_crm_notif_contact_pending_message',
                'label' => __('Message Body', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the contents of the SMS message', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentcrm',
            ]),
        ];
    }

    /**
     * Check if FluentCRM plugin is active
     *
     * @return bool
     */
    private function isPluginActive(): bool
    {
        return class_exists('WPSmsFluentCrmPlugin\WPSmsFluentCrmPlugin') && class_exists('FluentCrm\App\Hooks\Handlers\ActivationHandler');
    }

    /**
     * Get variables HTML
     *
     * @param array $variables
     * @return string
     */
    private function getVariablesHtml(array $variables): string
    {
        $html = '<div class="wpsms-variables">';
        $html .= '<strong>' . __('Available Variables:', 'wp-sms') . '</strong><br>';
        foreach ($variables as $variable => $description) {
            $html .= '<code>' . esc_html($variable) . '</code> ';
        }
        $html .= '</div>';
        return $html;
    }
} 