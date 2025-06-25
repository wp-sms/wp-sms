<?php

namespace WP_SMS\Settings\Groups;

use WP_SMS\Settings\Field;
use WP_SMS\Settings\Abstracts\AbstractSettingGroup;

class MessageButtonSettings extends AbstractSettingGroup {
    public function getName(): string {
        return 'message_button';
    }

    public function getLabel(): string {
        return 'Message Button Settings';
    }

    public function getFields(): array {
        return [
            new Field([
                'key' => 'chatbox',
                'type' => 'header',
                'label' => 'Message Button Configuration',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_message_button',
                'type' => 'checkbox',
                'label' => 'Message Button',
                'description' => 'Toggle to show or hide the Message Button on your site.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_title',
                'type' => 'text',
                'label' => 'Title',
                'description' => "Main title for your chatbox, e.g., 'Chat with Us!'",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_button',
                'type' => 'header',
                'label' => 'Button Appearance',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_button_text',
                'type' => 'text',
                'label' => 'Text',
                'description' => "The message displayed on the chat button, e.g., 'Talk to Us'",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_button_position',
                'type' => 'select',
                'label' => 'Position',
                'description' => 'Choose where the chat button appears on your site.',
                'options' => [
                    'bottom_right' => 'Bottom Right',
                    'bottom_left' => 'Bottom Left',
                ],
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_team_member',
                'type' => 'header',
                'label' => 'Support Team Profiles',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_team_members',
                'type' => 'repeater',
                'label' => 'Team Members',
                'description' => 'Add members to appear in the chat support list.',
                'repeatable' => true,
                'options' => [
                    'template' => 'admin/fields/field-team-member-repeater.php',
                ],
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_miscellaneous',
                'type' => 'header',
                'label' => 'Additional Chatbox Options',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_color',
                'type' => 'color',
                'label' => 'Chatbox Color',
                'description' => "Choose your chat button's background and header color.",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_text_color',
                'type' => 'color',
                'label' => 'Chatbox Text Color',
                'description' => 'Select the color for your button and header text.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_footer_text',
                'type' => 'text',
                'label' => 'Footer Text',
                'description' => "Text displayed in the chatbox footer, e.g., 'Chat with us on WhatsApp for instant support!'",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_footer_text_color',
                'type' => 'color',
                'label' => 'Footer Text Color',
                'description' => 'Select your footer text color.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_footer_link_title',
                'type' => 'text',
                'label' => 'Footer Link Title',
                'description' => "Add a link title in the footer, e.g., 'Related Articles'",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_footer_link_url',
                'type' => 'text',
                'label' => 'Footer Link URL',
                'description' => 'Enter the URL for the footer link.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_animation_effect',
                'type' => 'select',
                'label' => 'Animation Effect',
                'description' => 'Choose an effect for chatbox entry or hover state.',
                'options' => [
                    '' => 'None',
                    'fade' => 'Fade In',
                    'slide' => 'Slide Up',
                ],
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_disable_logo',
                'type' => 'checkbox',
                'label' => 'Disable WP SMS Logo',
                'description' => 'Check to remove the WP SMS branding from the footer.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_link',
                'type' => 'header',
                'label' => 'Informational Links',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_links_enabled',
                'type' => 'checkbox',
                'label' => 'Resource Links',
                'description' => 'Turn on to show resource links in the chatbox.',
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_links_title',
                'type' => 'text',
                'label' => 'Section Title',
                'description' => "The heading for your resource links, e.g., 'Quick Links'",
                'group_label' => 'Message Button',
            ]),
            new Field([
                'key' => 'chatbox_links',
                'type' => 'repeater',
                'label' => 'Links',
                'description' => 'Define multiple resource links shown inside the chatbox.',
                'repeatable' => true,
                'options' => [
                    'template' => 'admin/fields/field-resource-link-repeater.php',
                ],
                'group_label' => 'Message Button',
            ]),
        ];
    }
}
