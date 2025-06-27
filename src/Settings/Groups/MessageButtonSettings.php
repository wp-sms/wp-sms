<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Tags;

class MessageButtonSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'message_button';
    }

    public function getLabel(): string
    {
        return __('Message Button', 'wp-sms');
    }

    public function getIcon(): string
    {
        return LucideIcons::MESSAGE_CIRCLE;
    }

    public function getSections(): array
    {
        return [
            new Section([
                'id' => 'message_button_configuration',
                'title' => __('Message Button Configuration', 'wp-sms'),
                'subtitle' => __('Configure the main message button settings', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'chatbox_message_button',
                        'label' => __('Message Button', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Switch on to display the Message Button on your site or off to hide it. <a href="#" class="js-wpsms-chatbox-preview">Preview</a>', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_title',
                        'label' => __('Title', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Main title for your chatbox, e.g., \'Chat with Us!\'', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'button_appearance',
                'title' => __('Button Appearance', 'wp-sms'),
                'subtitle' => __('Customize the appearance and position of the message button', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'chatbox_button_text',
                        'label' => __('Text', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('The message displayed on the chat button, e.g., \'Talk to Us\'', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_button_position',
                        'label' => __('Position', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Choose where the chat button appears on your site.', 'wp-sms'),
                        'options' => [
                            'bottom_right' => __('Bottom Right', 'wp-sms'),
                            'bottom_left' => __('Bottom Left', 'wp-sms'),
                        ]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'support_team_profiles',
                'title' => __('Support Team Profiles', 'wp-sms'),
                'subtitle' => __('Configure team member profiles for the chatbox', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'chatbox_team_members',
                        'label' => __('Team Members', 'wp-sms'),
                        'type' => 'repeater',
                        'description' => __('Add team members to display in the chatbox', 'wp-sms'),
                        'options' => [
                            'template' => 'admin/fields/field-team-member-repeater.php',
                        ]
                    ]),
                ]
            ]),
            new Section([
                'id' => 'additional_chatbox_options',
                'title' => __('Additional Chatbox Options', 'wp-sms'),
                'subtitle' => __('Customize colors, animations, and other visual elements', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'chatbox_color',
                        'label' => __('Chatbox Color', 'wp-sms'),
                        'type' => 'color',
                        'description' => __('Choose your chat button\'s background color and header color.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_text_color',
                        'label' => __('Chatbox Text Color', 'wp-sms'),
                        'type' => 'color',
                        'description' => __('Select the color for your button and header text.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_footer_text',
                        'label' => __('Footer Text', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Text displayed in the chatbox footer, such as \'Chat with us on WhatsApp for instant support!\'', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_footer_text_color',
                        'label' => __('Footer Text Color', 'wp-sms'),
                        'type' => 'color',
                        'description' => __('Select your footer text color.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_footer_link_title',
                        'label' => __('Footer Link Title', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Include a link for more information in the chatbox footer, e.g., \'Related Articles\'', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_footer_link_url',
                        'label' => __('Footer Link URL', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('Enter the URL of the chatbox footer link.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_animation_effect',
                        'label' => __('Animation Effect', 'wp-sms'),
                        'type' => 'select',
                        'description' => __('Choose an effect for the chatbox\'s entry or hover state.', 'wp-sms'),
                        'options' => [
                            '' => __('None', 'wp-sms'),
                            'fade' => __('Fade In', 'wp-sms'),
                            'slide' => __('Slide Up', 'wp-sms'),
                        ]
                    ]),
                    new Field([
                        'key' => 'chatbox_disable_logo',
                        'label' => __('Disable WP SMS Logo', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Check this box to disable the WP SMS logo in the footer of the chatbox.', 'wp-sms')
                    ]),
                ]
            ]),
            new Section([
                'id' => 'informational_links',
                'title' => __('Informational Links', 'wp-sms'),
                'subtitle' => __('Configure resource links displayed in the chatbox', 'wp-sms'),
                'fields' => [
                    new Field([
                        'key' => 'chatbox_links_enabled',
                        'label' => __('Resource Links', 'wp-sms'),
                        'type' => 'checkbox',
                        'description' => __('Turn on to show resource links in the chatbox.', 'wp-sms')
                    ]),
                    new Field([
                        'key' => 'chatbox_links_title',
                        'label' => __('Section Title', 'wp-sms'),
                        'type' => 'text',
                        'description' => __('The heading for your resource links, e.g., \'Quick Links\'', 'wp-sms'),
                        'show_if' => ['chatbox_links_enabled' => true]
                    ]),
                    new Field([
                        'key' => 'chatbox_links',
                        'label' => __('Links', 'wp-sms'),
                        'type' => 'repeater',
                        'description' => __('Add resource links to display in the chatbox', 'wp-sms'),
                        'options' => [
                            'template' => 'admin/fields/field-resource-link-repeater.php',
                        ],
                        'show_if' => ['chatbox_links_enabled' => true]
                    ]),
                ]
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
}
