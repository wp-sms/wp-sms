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
                'title' => __('SMS Newsletter Configuration', 'wp-sms'),
                'subtitle' => __('Configure how visitors subscribe to your SMS notifications.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/add-sms-subscriber-form/',
                'fields' => [
                    new Field([
                        'key' => 'newsletter_form_groups',
                        'label' => __('Group Visibility in Form', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Show available groups on the subscription form.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'newsletter_form_multiple_select',
                        'label' => __('Group Selection', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Allow subscribers to join multiple groups from the form.', 'wp-sms'),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_specified_groups',
                        'label' => __('Groups to Display', 'wp-sms'),
                        'type' => 'multiselect',
                        'description' => __('Choose which groups appear on the subscription form.', 'wp-sms'),
                        'options' => $this->getNewsletterGroups(),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_default_group',
                        'label' => __('Default Group for New Subscribers', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Set a group that all new subscribers will join by default.', 'wp-sms'),
                        'options' => $this->getSubscribeGroups(),
                        'show_if' => ['newsletter_form_groups' => true]
                    ]),
                    new Field([
                        'key' => 'newsletter_form_verify',
                        'label' => __('Subscription Confirmation', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Subscribers must enter a code received by SMS to complete their subscription.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'welcome_sms_setup',
                'title' => __('Welcome SMS Setup', 'wp-sms'),
                'subtitle' => __('Set up automatic SMS messages for new subscribers.', 'wp-sms'),
                'help_url' => WP_SMS_SITE . '/resources/send-welcome-sms-to-new-subscribers/',
                'fields' => [
                    new Field([
                        'key' => 'newsletter_form_welcome',
                        'label' => __('Status', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Sends a welcome SMS to new subscribers when they sign up.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'newsletter_form_welcome_text',
                        'label' => __('Welcome Message Content', 'wp-sms'),
                        'type' => 'textarea',
                        'description' => __('Customize the SMS message sent to new subscribers. Use placeholders for personalized details: ', 'wp-sms') . '<br>' . NotificationFactory::getSubscriber()->printVariables()
                    ]),
                ]
            ]),
            new Section([
                'id' => 'appearance_customization',
                'title' => __('Appearance Customization', 'wp-sms'),
                'subtitle' => __('Customize the visual appearance of the newsletter form', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'disable_style_in_front',
                        'label' => __('Disable Default Form Styling', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Remove the plugin\'s default styling from the subscription form if preferred.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'data_protection_settings',
                'title' => __('Data Protection Settings', 'wp-sms'),
                'subtitle' => __('Set up how you comply with data protection regulations', 'wp-sms'),
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
        $options = [0 => __('All', 'wp-sms')];
        
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
                    'label' => __('Consent Text', 'wp-sms'),
                    'type' => 'textarea',
                    'description' => __('Provide a clear message that informs subscribers how their data will be used and that their consent is required. For example: "I agree to receive SMS notifications and understand that my data will be handled according to the privacy policy."', 'wp-sms')
                ]),
                new Field([
                    'key' => 'newsletter_form_gdpr_confirm_checkbox',
                    'label' => __('Checkbox Default', 'wp-sms'),
                    'type' => 'select',
                    'options' => [
                        'checked' => __('Checked', 'wp-sms'),
                        'unchecked' => __('Unchecked', 'wp-sms')
                    ],
                    'description' => __('Leave the consent checkbox unchecked by default to comply with privacy laws, which require active, explicit consent from users.', 'wp-sms')
                ])
            ];
        } else {
            return [
                new Field([
                    'key' => 'gdpr_notify',
                    'label' => __('GDPR Compliance', 'wp-sms'),
                    'type' => 'notice',
                    'description' => __('To get more option for GDPR, you should enable that in the general tab.', 'wp-sms')
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
