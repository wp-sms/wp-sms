<?php /** @noinspection SqlDialectInspection */

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class GeneralSettings extends AbstractSettingGroup {

    public function getName(): string
    {
        return 'general';
    }

    public function getLabel(): string
    {
        return __('General', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::SETTINGS;
    }

    public function getSections(): array {
        return [
            new Section([
                'id' => 'administrator_notifications',
                'title' => __('Administrator Notifications', 'wp-sms'),
                'subtitle' => __('Choose where admin SMS alerts are sent.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'admin_mobile_number',
                        'label' => __('Admin mobile number', 'wp-sms'),
                        'type' => 'tel',
                        'description' => __('WP SMS sends admin alerts to this number.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'mobile_field_configuration',
                'title' => __('Mobile Number Field', 'wp-sms'),
                'subtitle' => __('Add or reuse a mobile field in profiles and checkout. Set validation and country options.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'add_mobile_field',
                        'label' => __('Mobile Number Field Source', 'wp-sms'),
                        'type' => 'advancedselect',
                        'description' => __('Create a new mobile number field or reuse an existing one.', 'wp-sms'),
                        'options' => [
                            'disable' => __('Disable', 'wp-sms'),
                            'WordPress' => [
                                'add_mobile_field_in_profile' => __('Add to user profiles', 'wp-sms')
                            ],
                            'WooCommerce' => [
                                'add_mobile_field_in_wc_billing' => __('Add to billing and checkout', 'wp-sms'),
                                'use_phone_field_in_wc_billing' => __('Use Billing phone field', 'wp-sms')
                            ],
                            'Ultimate Member' => [
                                'use_ultimate_member_mobile_field' => __('Use existing field', 'wp-sms')
                            ],
                            'BuddyPress' => [
                                'use_buddypress_mobile_field' => __('Use existing field', 'wp-sms')
                            ]
                        ]
                    ]),
                    new Field([
                        'key' => 'um_sync_field_name',
                        'label' => __('Ultimate Member field to sync', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Choose the Ultimate Member registration field to sync as the mobile number. Default is "Mobile Number".', 'wp-sms'),
                        'options' => $this->getUmRegisterFormFields(),
                        'default' => 'mobile_number',
                        'show_if' => ['add_mobile_field' => 'use_ultimate_member_mobile_field']
                    ]),
                    new Field([
                        'key' => 'um_sync_previous_members',
                        'label' => __('Sync existing members', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Also sync numbers for members who registered before this option was enabled.', 'wp-sms'),
                        'show_if' => ['add_mobile_field' => 'use_ultimate_member_mobile_field']
                    ]),
                    new Field([
                        'key' => 'bp_mobile_field_id',
                        'label' => __('BuddyPress field', 'wp-sms'),
                        'type' => 'advancedselect',
                        'description' => __('Choose the BuddyPress profile field that stores the mobile number.', 'wp-sms'),
                        'options' => $this->getBuddyPressProfileFields(),
                        'show_if' => ['add_mobile_field' => 'use_buddypress_mobile_field']
                    ]),
                    new Field([
                        'key' => 'bp_sync_fields',
                        'label' => __('Sync BuddyPress numbers', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Keep BuddyPress mobile numbers in sync with WP SMS.', 'wp-sms'),
                        'show_if' => ['add_mobile_field' => 'use_buddypress_mobile_field']
                    ]),
                    new Field([
                        'key' => 'optional_mobile_field',
                        'label' => __('Required or optional', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Choose whether users must provide a mobile number.', 'wp-sms'),
                        'options' => [
                            '0' => __('Required', 'wp-sms'),
                            'optional' => __('Optional', 'wp-sms')
                        ]
                    ]),
                    new Field([
                        'key' => 'mobile_terms_field_place_holder',
                        'label' => __('Placeholder text', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Example shown inside the field. For example: +1234567890.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'international_mobile',
                        'label' => __('International input', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Show a country dropdown with flags and format numbers in international format.', 'wp-sms'),
                        'helper' => __('When enabled, the default country code and min or max digits settings are ignored.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'international_mobile_only_countries',
                        'label' => __('Allowed countries', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Only show these countries in the dropdown.', 'wp-sms'),
                        'show_if' => ['international_mobile' => true],
                        'options' => wp_sms_countries()->getCountryNamesByCode()
                    ]),
                    new Field([
                        'key' => 'international_mobile_preferred_countries',
                        'label' => __('Pinned countries (top of list)', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('These countries appear at the top of the dropdown.', 'wp-sms'),
                        'show_if' => ['international_mobile' => true],
                        'options_depends_on' => 'international_mobile_only_countries',
                        'sortable' => true,
                        'options' => wp_sms_countries()->getCountryNamesByCode()
                    ]),
                    new Field([
                        'key' => 'mobile_county_code',
                        'label' => __('Default country code', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Used when International input is off. Choose a code to prepend. Select "No country code (Global or local)" for local numbers.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true],
                        'options' => array_merge(['0' => __('No country code (Global or local)', 'wp-sms')], wp_sms_countries()->getCountriesMerged())
                    ]),
                    new Field([
                        'key' => 'mobile_terms_minimum',
                        'label' => __('Minimum digits', 'wp-sms'),
                        'type' => 'number',
                        'description' => __('Smallest allowed number length. Symbols are ignored.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true]
                    ]),
                    new Field([
                        'key' => 'mobile_terms_maximum',
                        'label' => __('Maximum digits', 'wp-sms'),
                        'type' => 'number',
                        'description' => __('Largest allowed number length. Symbols are ignored.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'data_protection_settings',
                'title' => __('Privacy and GDPR', 'wp-sms'),
                'subtitle' => __('Tools that help you respect user privacy and handle GDPR requests.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'gdpr_compliance',
                        'label' => __('Enable GDPR tools', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Adds a consent checkbox for SMS marketing. Lets users request export or deletion of their data by mobile number.', 'wp-sms'),
                    ]),
                ]
            ]),
        ];
    }

    public function getUmRegisterFormFields(): array
    {
        $ultimate_member_forms = get_posts(['post_type' => 'um_form']);
        $return_value = [];

        foreach ($ultimate_member_forms as $form) {
            $form_role = get_post_meta($form->ID, '_um_mode');

            if (in_array('register', $form_role)) {
                $form_fields = get_post_meta($form->ID, '_um_custom_fields');

                foreach ($form_fields[0] as $field) {
                    if (isset($field['title']) && isset($field['metakey'])) {
                        $return_value[$field['metakey']] = $field['title'];
                    }
                }
            }
        }
        return $return_value;
    }

    public function getBuddyPressProfileFields(): array
    {
        $buddyPressProfileFields = [];
        
        if (function_exists('bp_xprofile_get_groups')) {
            $buddyPressProfileGroups = bp_xprofile_get_groups(['fetch_fields' => true]);

            foreach ($buddyPressProfileGroups as $buddyPressProfileGroup) {
                if (isset($buddyPressProfileGroup->fields)) {
                    foreach ($buddyPressProfileGroup->fields as $field) {
                        $buddyPressProfileFields[$buddyPressProfileGroup->name][$field->id] = $field->name;
                    }
                }
            }
        }

        return $buddyPressProfileFields;
    }

    public function getFields(): array {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }
}
