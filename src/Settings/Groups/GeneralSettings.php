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
                'subtitle' => __('Configure administrator notification settings', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'admin_mobile_number',
                        'label' => __('Admin Mobile Number', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Mobile number where the administrator will receive notifications.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'mobile_field_configuration',
                'title' => __('Mobile Field Configuration', 'wp-sms'),
                'subtitle' => __('Configure mobile number field settings for user profiles and forms', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'add_mobile_field',
                        'label' => __('Mobile Number Field Source', 'wp-sms'),
                        'type' => 'advancedselect',
                        'description' => __('Create a new mobile number field or use an existing phone field.', 'wp-sms'),
                        'options' => [
                            'WordPress' => [
                                'disable' => __('Disable', 'wp-sms'),
                                'add_mobile_field_in_profile' => __('Insert a mobile number field into user profiles', 'wp-sms')
                            ],
                            'WooCommerce' => [
                                'add_mobile_field_in_wc_billing' => __('Add a mobile number field to billing and checkout pages', 'wp-sms'),
                                'use_phone_field_in_wc_billing' => __('Use the existing billing phone field', 'wp-sms')
                            ]
                        ]
                    ]),
                    new Field([
                        'key' => 'um_sync_field_name',
                        'label' => __('Select the Existing Field', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Select the field from ultimate member register form that you want to be synced(Default is "Mobile Number").', 'wp-sms'),
                        'options' => $this->getUmRegisterFormFields(),
                        'default' => 'mobile_number',
                        'show_if' => ['add_mobile_field' => 'use_ultimate_member_mobile_field']
                    ]),
                    new Field([
                        'key' => 'um_sync_previous_members',
                        'label' => __('Sync Old Members Too?', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sync the old mobile numbers which registered before enabling the previous option in Ultimate Member.', 'wp-sms'),
                        'show_if' => ['add_mobile_field' => 'use_ultimate_member_mobile_field']
                    ]),
                    new Field([
                        'key' => 'bp_mobile_field_id',
                        'label' => __('Select the Existing Field', 'wp-sms'),
                        'type' => 'advancedselect',
                        'description' => __('Select the BuddyPress field', 'wp-sms'),
                        'options' => $this->getBuddyPressProfileFields(),
                        'show_if' => ['add_mobile_field' => 'use_buddypress_mobile_field']
                    ]),
                    new Field([
                        'key' => 'bp_sync_fields',
                        'label' => __('Sync Fields', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sync and compatibility the BuddyPress mobile numbers with plugin.', 'wp-sms'),
                        'show_if' => ['add_mobile_field' => 'use_buddypress_mobile_field']
                    ]),
                    new Field([
                        'key' => 'optional_mobile_field',
                        'label' => __('Mobile Field Mandatory Status', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Set the mobile number field as optional or required.', 'wp-sms'),
                        'options' => [
                            '0' => __('Required', 'wp-sms'),
                            'optional' => __('Optional', 'wp-sms')
                        ]
                    ]),
                    new Field([
                        'key' => 'mobile_terms_field_place_holder',
                        'label' => __('Mobile Field Placeholder', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter a sample format for the mobile number that users will see. Example: "e.g., +1234567890".', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'international_mobile',
                        'label' => __('International Number Input', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Add a flag dropdown for international format support in the mobile number input field.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'international_mobile_only_countries',
                        'label' => __('Only Countries', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('In the dropdown, display only the countries you specify.', 'wp-sms'),
                        'show_if' => ['international_mobile' => true],
                        'options' => wp_sms_countries()->getCountryNamesByDialCode()
                    ]),
                    new Field([
                        'key' => 'international_mobile_preferred_countries',
                        'label' => __('Preferred Countries', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Specify the countries to appear at the top of the list.', 'wp-sms'),
                        'show_if' => ['international_mobile' => true],
                        'options_depends_on' => 'international_mobile_only_countries',
                        'sortable' => true,
                        'options' => wp_sms_countries()->getCountryNamesByDialCode()
                    ]),
                    new Field([
                        'key' => 'mobile_county_code',
                        'label' => __('Country Code Prefix', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('If the user\'s mobile number requires a country code, select it from the list. If the number is not specific to any country, select \'No country code (Global / Local)\'.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true],
                        'options' => array_merge(['0' => __('No country code (Global / Local)', 'wp-sms')], wp_sms_countries()->getCountriesMerged())
                    ]),
                    new Field([
                        'key' => 'mobile_terms_minimum',
                        'label' => __('Minimum Length Number', 'wp-sms'),
                        'type' => 'number',
                        'description' => __('Specify the shortest allowed mobile number.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true]
                    ]),
                    new Field([
                        'key' => 'mobile_terms_maximum',
                        'label' => __('Maximum Length Number', 'wp-sms'),
                        'type' => 'number',
                        'description' => __('Specify the longest allowed mobile number.', 'wp-sms'),
                        'hide_if' => ['international_mobile' => true]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'data_protection_settings',
                'title' => __('Data Protection Settings', 'wp-sms'),
                'subtitle' => __('Enhance user privacy with GDPR-focused settings. Activate to ensure compliance with data protection regulations and provide users with transparency and control over their personal information.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'gdpr_compliance',
                        'label' => __('GDPR Compliance Enhancements', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Implements GDPR adherence by enabling user data export and deletion via mobile number and adding a consent checkbox for SMS newsletter subscriptions.', 'wp-sms'),
                        'tag' => Tags::NEW
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
