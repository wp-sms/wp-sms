<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Notification\NotificationFactory;

class FluentSupportSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'fluent_support';
    }

    public function getLabel(): string
    {
        return __('Fluent Support', 'wp-sms-fluent-integrations');
    }

    public function getIcon(): string
    {
        return LucideIcons::HELP_CIRCLE;
    }

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $inactiveNotice = $isPluginActive ? '' : ' <em>(' . __('Plugin not active', 'wp-sms-fluent-integrations') . ')</em>';
        
        return [
            new Section([
                'id' => 'ticket_created',
                'title' => __('Ticket Created', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for ticket creation', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getTicketCreatedFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
                'order' => 1,
            ]),
            new Section([
                'id' => 'customer_response',
                'title' => __('Replied By Customer', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for customer responses', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getCustomerResponseFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
                'order' => 2,
            ]),
            new Section([
                'id' => 'agent_assigned',
                'title' => __('Ticket Assigned', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for ticket assignment', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getAgentAssignedFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
                'order' => 3,
            ]),
            new Section([
                'id' => 'ticket_closed',
                'title' => __('Ticket Closed', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Configure SMS notifications for ticket closure', 'wp-sms-fluent-integrations') . $inactiveNotice,
                'fields' => $this->getTicketClosedFields(),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
                'order' => 4,
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
     * Get ticket created fields
     *
     * @return array
     */
    private function getTicketCreatedFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%ticket_id%' => '',
            '%ticket_title%' => '',
            '%customer_name%' => '',
            '%customer_email%' => '',
            '%ticket_status%' => '',
            '%ticket_priority%' => '',
            '%ticket_category%' => '',
        ];

        return [
            new Field([
                'key' => 'fluent_support_notif_ticket_created',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for ticket created', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_ticket_created_receiver',
                'label' => __('Phone number(s)', 'wp-sms-fluent-integrations'),
                'type' => 'text',
                'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-fluent-integrations'),
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_ticket_created_message',
                'label' => __('Message', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
        ];
    }

    /**
     * Get customer response fields
     *
     * @return array
     */
    private function getCustomerResponseFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%ticket_id%' => '',
            '%ticket_title%' => '',
            '%customer_name%' => '',
            '%customer_email%' => '',
            '%response_content%' => '',
            '%response_date%' => '',
        ];

        return [
            new Field([
                'key' => 'fluent_support_notif_customer_response',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for customer response', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_customer_response_receiver',
                'label' => __('Phone number(s)', 'wp-sms-fluent-integrations'),
                'type' => 'text',
                'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-fluent-integrations'),
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_customer_response_message',
                'label' => __('Message', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
        ];
    }

    /**
     * Get agent assigned fields
     *
     * @return array
     */
    private function getAgentAssignedFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%ticket_id%' => '',
            '%ticket_title%' => '',
            '%agent_name%' => '',
            '%agent_email%' => '',
            '%customer_name%' => '',
            '%assignment_date%' => '',
        ];

        return [
            new Field([
                'key' => 'fluent_support_notif_agent_assigned',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for ticket assigned', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_agent_assigned_receiver',
                'label' => __('Phone number(s)', 'wp-sms-fluent-integrations'),
                'type' => 'text',
                'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-fluent-integrations'),
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_agent_assigned_message',
                'label' => __('Message', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
        ];
    }

    /**
     * Get ticket closed fields
     *
     * @return array
     */
    private function getTicketClosedFields(): array
    {
        $isPluginActive = $this->isPluginActive();
        $variables = [
            '%ticket_id%' => '',
            '%ticket_title%' => '',
            '%customer_name%' => '',
            '%customer_email%' => '',
            '%closed_by%' => '',
            '%close_date%' => '',
            '%close_reason%' => '',
        ];

        return [
            new Field([
                'key' => 'fluent_support_notif_ticket_closed',
                'label' => __('Status', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification for ticket closed', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_ticket_closed_receiver',
                'label' => __('Phone number(s)', 'wp-sms-fluent-integrations'),
                'type' => 'text',
                'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-fluent-integrations'),
                'tag' => 'fluentsupport',
            ]),
            new Field([
                'key' => 'fluent_support_notif_ticket_closed_message',
                'label' => __('Message', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentsupport',
            ]),
        ];
    }

    /**
     * Check if FluentSupport plugin is active
     *
     * @return bool
     */
    private function isPluginActive(): bool
    {
        return class_exists('WPSmsFluentCrmPlugin\WPSmsFluentCrmPlugin') && class_exists('FluentSupport\App\Hooks\Handlers\ActivationHandler');
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

    public function getOptionKeyName(): ?string
    {
        return 'fluent_integrations';
    }
} 