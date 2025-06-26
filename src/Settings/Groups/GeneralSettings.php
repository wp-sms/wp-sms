<?php /** @noinspection SqlDialectInspection */

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class GeneralSettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'general';
    }

    public function getLabel(): string {
        return 'General';
    }

    public function getFields(): array {
        return [
            new Field([
                'key'         => 'admin_title',
                'type'        => 'header',
                'label'       => 'Administrator Notifications',
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'admin_mobile_number',
                'type'        => 'text',
                'label'       => 'Admin Mobile Number',
                'description' => 'Mobile number where the administrator will receive notifications.',
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'mobile_field',
                'type'        => 'header',
                'label'       => 'Mobile Field Configuration',
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'add_mobile_field',
                'type'        => 'advancedselect',
                'label'       => 'Mobile Number Field Source',
                'description' => 'Create a new mobile number field or use an existing phone field.',
                'options'     => [
                    'WordPress' => [
                        'disable' => 'Disable',
                        'add_mobile_field_in_profile' => 'Insert a mobile number field into user profiles',
                    ],
                    'WooCommerce' => [
                        'add_mobile_field_in_wc_billing' => 'Add a mobile number field to billing and checkout pages',
                        'use_phone_field_in_wc_billing'  => 'Use the existing billing phone field',
                    ]
                ],
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'um_sync_field_name',
                'type'        => 'select',
                'label'       => 'Select the Existing Field (UM)',
                'description' => 'Select the field from Ultimate Member register form to sync.',
                'default'     => 'mobile_number',
                'show_if'     => ['add_mobile_field' => 'use_ultimate_member_mobile_field'],
                'options'     => $this->getUMRegisterFormFields(),
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'um_sync_previous_members',
                'type'        => 'checkbox',
                'label'       => 'Sync Old Members Too? (UM)',
                'description' => 'Sync mobile numbers of users registered before enabling the sync.',
                'show_if'     => ['add_mobile_field' => 'use_ultimate_member_mobile_field'],
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'bp_mobile_field_id',
                'type'        => 'advancedselect',
                'label'       => 'Select the Existing Field (BP)',
                'description' => 'Select the BuddyPress field.',
                'show_if'     => ['add_mobile_field' => 'use_buddypress_mobile_field'],
                'options'     => $this->getBuddyPressProfileFields(),
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'bp_sync_fields',
                'type'        => 'checkbox',
                'label'       => 'Sync Fields (BP)',
                'description' => 'Sync BuddyPress mobile numbers with the plugin.',
                'show_if'     => ['add_mobile_field' => 'use_buddypress_mobile_field'],
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'optional_mobile_field',
                'type'        => 'select',
                'label'       => 'Mobile Field Mandatory Status',
                'description' => 'Set the mobile number field as optional or required.',
                'default'     => '0',
                'options'     => [
                    '0'        => 'Required',
                    'optional' => 'Optional',
                ],
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'mobile_terms_field_place_holder',
                'type'        => 'text',
                'label'       => 'Mobile Field Placeholder',
                'description' => 'Example: "e.g., +1234567890".',
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'international_mobile_only_countries',
                'type'        => 'multiselect',
                'label'       => 'Only Countries',
                'description' => 'Restrict dropdown to specific countries. Leave blank for all.',
                'options'     => wp_sms_countries()->getCountryNamesByCode(),
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'international_mobile_preferred_countries',
                'type'        => 'multiselect',
                'label'       => 'Preferred Countries',
                'description' => 'Countries shown at the top of dropdown.',
                'options'     => wp_sms_countries()->getCountryNamesByCode(),
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'admin_title_privacy',
                'type'        => 'header',
                'label'       => 'Data Protection Settings',
                'description' => 'GDPR-focused user data protection settings.',
                'group_label' => 'General',
            ]),
            new Field([
                'key'         => 'gdpr_compliance',
                'type'        => 'checkbox',
                'label'       => 'GDPR Compliance Enhancements',
                'description' => 'Enable user data export/deletion by mobile, consent checkbox, etc.',
                'group_label' => 'General',
            ]),
        ];
    }
}
