<?php
namespace WP_SMS\Settings\Groups;

use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Option;

class NewsletterSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'newsletter';
    }

    public function getLabel(): string
    {
        return 'SMS Newsletter';
    }

    public function getFields(): array
    {
        $fields = [
            new Field([
                'key'         => 'newsletter_title',
                'type'        => 'header',
                'label'       => 'SMS Newsletter Configuration',
                'description' => 'Configure how visitors subscribe to your SMS notifications.',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_groups',
                'type'        => 'checkbox',
                'label'       => 'Group Visibility in Form',
                'description' => 'Show available groups on the subscription form.',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_multiple_select',
                'type'        => 'checkbox',
                'label'       => 'Group Selection',
                'description' => 'Allow subscribers to join multiple groups from the form.',
                'show_if'     => ['newsletter_form_groups' => true],
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_specified_groups',
                'type'        => 'multiselect',
                'label'       => 'Groups to Display',
                'description' => 'Choose which groups appear on the subscription form.',
                'show_if'     => ['newsletter_form_groups' => true],
                'options'     => array_reduce(Newsletter::getGroups(), function ($acc, $item) {
                    $acc[$item->ID] = $item->name;
                    return $acc;
                }, []),
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_default_group',
                'type'        => 'select',
                'label'       => 'Default Group for New Subscribers',
                'description' => 'Set a group that all new subscribers will join by default.',
                'show_if'     => ['newsletter_form_groups' => true],
                'options'     => $this->getSubscriberGroups(),
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_verify',
                'type'        => 'checkbox',
                'label'       => 'Subscription Confirmation',
                'description' => 'Subscribers must enter a code received by SMS to complete their subscription.',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'welcome',
                'type'        => 'header',
                'label'       => 'Welcome SMS Setup',
                'description' => 'Set up automatic SMS messages for new subscribers.',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_welcome',
                'type'        => 'checkbox',
                'label'       => 'Status',
                'description' => 'Sends a welcome SMS to new subscribers when they sign up.',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'newsletter_form_welcome_text',
                'type'        => 'textarea',
                'label'       => 'Welcome Message Content',
                'description' => 'Customize the SMS message sent to new subscribers. Use placeholders for personalized details.'
                    . '<br>' . NotificationFactory::getSubscriber()->printVariables(),
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'style',
                'type'        => 'header',
                'label'       => 'Appearance Customization',
                'group_label' => 'Newsletter',
            ]),
            new Field([
                'key'         => 'disable_style_in_front',
                'type'        => 'checkbox',
                'label'       => 'Disable Default Form Styling',
                'description' => 'Remove the plugin\'s default styling from the subscription form if preferred.',
                'group_label' => 'Newsletter',
            ]),
        ];

        if (Option::getOption('gdpr_compliance')) {
            $fields[] = new Field([
                'key'         => 'newsletter_gdpr',
                'type'        => 'header',
                'label'       => 'Data Protection Settings',
                'description' => 'Set up how you comply with data protection regulations.',
                'group_label' => 'Newsletter',
            ]);
            $fields[] = new Field([
                'key'         => 'newsletter_form_gdpr_text',
                'type'        => 'textarea',
                'label'       => 'Consent Text',
                'description' => 'Provide a clear message that informs subscribers how their data will be used and that their consent is required. For example: "I agree to receive SMS notifications and understand that my data will be handled according to the privacy policy."',
                'group_label' => 'Newsletter',
            ]);
            $fields[] = new Field([
                'key'         => 'newsletter_form_gdpr_confirm_checkbox',
                'type'        => 'select',
                'label'       => 'Checkbox Default',
                'description' => 'Leave the consent checkbox unchecked by default to comply with privacy laws, which require active, explicit consent from users.',
                'options'     => [
                    'checked'   => 'Checked',
                    'unchecked' => 'Unchecked',
                ],
                'group_label' => 'Newsletter',
            ]);
        } else {
            $fields[] = new Field([
                'key'         => 'gdpr_notify',
                'type'        => 'notice',
                'label'       => 'GDPR Compliance',
                'description' => 'To get more option for GDPR, you should enable that in the general tab.',
                'group_label' => 'Newsletter',
            ]);
        }

        return $fields;
    }
}
