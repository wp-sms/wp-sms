<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Notification\NotificationFactory;

class FluentFormsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'fluent_forms';
    }

    public function getLabel(): string
    {
        return __('Fluent Forms', 'wp-sms-fluent-integrations');
    }

    public function getIcon(): string
    {
        return LucideIcons::FILE_TEXT;
    }

    public function getMetaData(){
        return [
            'addon' => 'fluent_integrations',
        ];
    }

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'fluent_forms_integration',
                'title' => __('Fluent Forms Integration', 'wp-sms-fluent-integrations'),
                'subtitle' => __('Connect Fluent Forms to enable SMS options.', 'wp-sms-fluent-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'fluent_forms_not_active_notice',
                        'label' => __('Not active', 'wp-sms-fluent-integrations'),
                        'type' => 'notice',
                        'description' => __('Fluent Forms is not installed or active. Install and activate Fluent Forms to configure SMS notifications.', 'wp-sms-fluent-integrations')
                    ])
                ]
            ]);
        }

        $forms = $this->getFluentForms();

        if (!empty($forms)) {
            foreach ($forms as $formId => $formTitle) {
                $sections[] = new Section([
                    'id' => 'form_' . $formId,
                    'title' => sprintf(__('Form: %s', 'wp-sms-fluent-integrations'), $formTitle),
                    'subtitle' => __('Configure SMS notifications for this form', 'wp-sms-fluent-integrations'),
                    'fields' => $this->getFormFields($formId, $formTitle),
                    'readonly' => !$isPluginActive,
                    'tag' => 'fluentforms',
                    'order' => $formId,
                ]);
            }
        } else {
            $sections[] = new Section([
                'id' => 'no_forms',
                'title' => __('No Forms Found', 'wp-sms-fluent-integrations'),
                'subtitle' => __('No Fluent Forms found', 'wp-sms-fluent-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'fluent_forms_not_found',
                        'label' => __('Notice', 'wp-sms-fluent-integrations'),
                        'type' => 'html',
                        'description' => __('We could not find any Fluent Forms. Please create forms in Fluent Forms to configure SMS notifications.', 'wp-sms-fluent-integrations'),
                        'readonly' => !$isPluginActive,
                        'tag' => 'fluentforms',
                    ]),
                ],
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
                'order' => 1,
            ]);
        }

        return $sections;
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
     * Get form fields for a specific form
     *
     * @param int $formId
     * @param string $formTitle
     * @return array
     */
    private function getFormFields(int $formId, string $formTitle): array
    {
        $isPluginActive = $this->isPluginActive();
        $formFields = $this->getFormFieldOptions($formId);
        $variables = $this->getFormVariables($formId);

        return [
            new Field([
                'key' => 'fluent_forms_notif_after_submission_' . $formId,
                'label' => __('Send SMS to a number', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification to a number after form submission', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
            new Field([
                'key' => 'fluent_forms_notif_after_submission_' . $formId . '_receiver',
                'label' => __('Phone number(s)', 'wp-sms-fluent-integrations'),
                'type' => 'text',
                'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
            new Field([
                'key' => 'fluent_forms_notif_after_submission_' . $formId . '_message',
                'label' => __('Message body', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
            new Field([
                'key' => 'fluent_forms_notif_field_after_submission_' . $formId,
                'label' => __('Send SMS to field', 'wp-sms-fluent-integrations'),
                'type' => 'checkbox',
                'description' => __('By this option you can add SMS notification to a field after form submission', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
            new Field([
                'key' => 'fluent_forms_notif_field_after_submission_' . $formId . '_field',
                'label' => __('A field of the form', 'wp-sms-fluent-integrations'),
                'type' => 'select',
                'options' => $formFields,
                'description' => __('Select the field', 'wp-sms-fluent-integrations'),
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
            new Field([
                'key' => 'fluent_forms_notif_field_after_submission_' . $formId . '_message',
                'label' => __('Message body', 'wp-sms-fluent-integrations'),
                'type' => 'textarea',
                'description' => __('Enter the message body', 'wp-sms-fluent-integrations') . '<br>' . $this->getVariablesHtml($variables),
                'rows' => 5,
                'readonly' => !$isPluginActive,
                'tag' => 'fluentforms',
            ]),
        ];
    }

    /**
     * Check if FluentForms plugin is active
     *
     * @return bool
     */
    private function isPluginActive(): bool
    {
        return class_exists('WPSmsFluentCrmPlugin\WPSmsFluentCrmPlugin') && function_exists('wpFluent');
    }

    /**
     * Get Fluent Forms
     *
     * @return array
     */
    private function getFluentForms(): array
    {
        if (!function_exists('wpFluent')) {
            return [];
        }

        $forms = [];
        $fluentForms = wpFluent()->table('fluentform_forms')->select(array('id', 'title'))->orderBy('id', 'DESC')->get();
        
        if (!empty($fluentForms)) {
            foreach ($fluentForms as $form) {
                $forms[$form->id] = $form->title;
            }
        }

        return $forms;
    }

    /**
     * Get form field options
     *
     * @param int $formId
     * @return array
     */
    private function getFormFieldOptions(int $formId): array
    {
        if (!function_exists('fluentFormApi')) {
            return [];
        }

        $form = fluentFormApi('forms')->form($formId);
        $fields = $form->fields();
        $data = [];

        if (!empty($fields['fields'])) {
            foreach ($fields['fields'] as $field) {
                if ('container' === $field['element']) {
                    foreach ($field['columns'] as $columnField) {
                        $data = array_merge($this->getFieldData($columnField['fields']), $data);
                    }
                } else {
                    $data = array_merge($this->getFieldData([$field]), $data);
                }
            }
        }

        return $data;
    }

    /**
     * Get field data
     *
     * @param array $fieldInput
     * @return array
     */
    private function getFieldData(array $fieldInput): array
    {
        $variables = array();
        foreach ($fieldInput as $field) {
            if (array_key_exists('fields', $field)) {
                foreach ($field['fields'] as $key => $farray) {
                    $variables[$key] = ucwords(str_replace('_', ' ', $key));
                }
            } else {
                if (array_key_exists('name', $field['attributes'])) {
                    $variables[$field['attributes']['name']] = ucwords(str_replace('_', ' ', $field['attributes']['name']));
                }
            }
        }
        return $variables;
    }

    /**
     * Get form variables
     *
     * @param int $formId
     * @return array
     */
    private function getFormVariables(int $formId): array
    {
        $variables = [
            '%form_title%' => '',
            '%form_id%' => '',
            '%submission_id%' => '',
            '%submission_date%' => '',
        ];

        // Add form-specific field variables
        $formFields = $this->getFormFieldOptions($formId);
        foreach ($formFields as $fieldKey => $fieldLabel) {
            $variables['%' . $fieldKey . '%'] = $fieldLabel;
        }

        return $variables;
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