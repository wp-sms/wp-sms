<?php
namespace WP_SMS\Settings\Groups;

use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Option;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class NewsletterSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'newsletter';
    }

    public function getLabel(): string
    {
        return __('SMS Newsletter', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::NEWSPAPER;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'sms_newsletter_configuration',
                'title' => __('Subscription Form', 'wp-sms'),
                'subtitle' => __('Choose how visitors subscribe.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/add-sms-subscriber-form/',
                'fields' => [
                    new Field([
                        'key' => 'newsletter_form_groups',
                        'label' => __('Show groups on form', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Display your subscriber groups on the signup form.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'newsletter_form_multiple_select',
                        'label' => __('Allow joining multiple groups', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Let visitors pick more than one group.', 'wp-sms'),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_specified_groups',
                        'label' => __('Groups shown on form', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Select which groups appear in the form.', 'wp-sms'),
                        'options' => $this->getNewsletterGroups(),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_default_group',
                        'label' => __('Default group', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Add every new subscriber to this group by default.', 'wp-sms'),
                        'options' => $this->getSubscribeGroups(),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_verify',
                        'label' => __('Require SMS verification', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Send a one-time code that subscribers must enter to confirm their number.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'welcome_sms_setup',
                'title' => __('Welcome SMS', 'wp-sms'),
                'subtitle' => __('Send an automatic message after someone subscribes.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/send-welcome-sms-to-new-subscribers/',
                'fields' => [
                    new Field([
                        'key' => 'newsletter_form_welcome',
                        'label' => __('Send a welcome SMS', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Automatically send a welcome message after a subscription is confirmed.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'newsletter_form_welcome_text',
                        'label' => __('Welcome message', 'wp-sms'),
                        'type' => 'textarea',
                        'show_if' => ['newsletter_form_welcome' => true],
                        'description' => __('Write the text for your welcome SMS. You can use these placeholders: ', 'wp-sms') . '<br>' . NotificationFactory::getSubscriber()->printVariables() . '<br>' . __('Keep it short. If your gateway supports opt-out keywords, add a line like "Reply STOP to unsubscribe."', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'appearance_customization',
                'title' => __('Form Design', 'wp-sms'),
                'subtitle' => __('Use your theme styles.', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'disable_style_in_front',
                        'label' => __('Use theme styling', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Turn off the plugin\'s default form styles and rely on your theme or custom CSS.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'data_protection_settings',
                'title' => __('Consent and Data Protection', 'wp-sms'),
                'subtitle' => __('Collect clear consent on your form.', 'wp-sms'),
                'fields' => $this->getGdprFields()
            ]),
        ];
    }

    private function getNewsletterGroups(): array
    {
        $groups = Newsletter::getGroups();
        $options = [];
        
        foreach ($groups as $group) {
            $options[] = [$group->ID => $group->name];
        }
        
        return $options;
    }

    private function getSubscribeGroups(): array
    {
        $groups = Newsletter::getGroups();
        $options = [0 => __('No default group', 'wp-sms')];
        
        foreach ($groups as $group) {
            $options[$group->ID] = $group->name;
        }
        
        return $options;
    }

    private function getGdprFields(): array
    {
        if (Option::getOption('gdpr_compliance')) {
            return [
                new Field([
                    'key' => 'newsletter_form_gdpr_text',
                    'label' => __('Consent message', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Tell people what you send and how you use their data. Example: "I agree to receive SMS updates and accept the Privacy Policy."', 'wp-sms')
                ]),
                new Field([
                    'key' => 'newsletter_form_gdpr_confirm_checkbox',
                    'label' => __('Consent checkbox default', 'wp-sms'),
                    'type' => 'select',
                    'options' => [
                        'unchecked' => __('Unchecked (recommended)', 'wp-sms'),
                        'checked' => __('Checked', 'wp-sms')
                    ],
                    'description' => __('Leave this unchecked to require active consent.', 'wp-sms')
                ])
            ];
        } else {
            return [
                new Field([
                    'key' => 'gdpr_notify',
                    'label' => __('Data Protection', 'wp-sms'),
                    'type' => 'notice',
                    'description' => __('To manage consent on this form, enable Data Protection in Settings → General.', 'wp-sms')
                ])
            ];
        }
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
