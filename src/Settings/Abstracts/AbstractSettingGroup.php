<?php

namespace WP_SMS\Settings\Abstracts;

use WP_SMS\Helper;
use WP_SMS\Newsletter;
use WP_SMS\Version;
use WP_SMS\Settings\LucideIcons;
use WP_SMS\Settings\Section;

abstract class AbstractSettingGroup
{


    /**
     * @var bool
     */
    private $proIsInstalled;
    /**
     * @var bool
     */
    private $wooProIsInstalled;

    public function __construct()
    {
        $this->proIsInstalled    = Version::pro_is_active();
        $this->wooProIsInstalled = Version::pro_is_installed('wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php');
    }

    public abstract function getName(): string;

    public abstract function getLabel(): string;

    public abstract function getFields(): array;

    /**
     * Get the Lucide icon name for this group
     *
     * @return string
     */
    public function getIcon(): string
    {
        return LucideIcons::getDefault();
    }

    /**
     * Get sections for this group
     *
     * @return array
     */
    public function getSections(): array
    {
        // Default: create one section with all fields
        return [
            new Section([
                'id' => 'default',
                'title' => $this->getLabel(),
                'order' => 1,
                'fields' => $this->getFields(),
            ])
        ];
    }

    public function getUMRegisterFormFields(): array
    {
        $ultimate_member_forms = get_posts(['post_type' => 'um_form']);

        $return_value = array();
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

    public function getSubscriberGroups(): array
    {
        /*
         * Pro Pack fields
         */
        $groups              = Newsletter::getGroups();
        $subscribe_groups[0] = esc_html__('All', 'wp-sms');

        if ($groups) {
            foreach ($groups as $group) {
                $subscribe_groups[$group->ID] = $group->name;
            }
        }

        return $subscribe_groups;
    }

    public function proIsInstalled(): bool
    {
        return $this->proIsInstalled;
    }

    public function wooProIsInstalled(): bool
    {
        return $this->wooProIsInstalled;
    }

    /*
     * Get list Post Type
     */
    public function getListPostType($args = array()): array
    {
        // vars
        $postTypes = array();

        // extract special arg
        $exclude   = array();
        $exclude[] = 'attachment';
        $exclude[] = 'acf-field'; //Advance custom field
        $exclude[] = 'acf-field-group'; //Advance custom field Group
        $exclude[] = 'vc4_templates'; //Visual composer
        $exclude[] = 'vc_grid_item'; //Visual composer Grid
        $exclude[] = 'acf'; //Advance custom field Basic
        $exclude[] = 'wpcf7_contact_form'; //contact 7 Post Type
        $exclude[] = 'shop_order'; //WooCommerce Shop Order
        $exclude[] = 'shop_coupon'; //WooCommerce Shop coupon

        // get post type objects
        $objects = get_post_types($args, 'objects');
        foreach ($objects as $k => $object) {
            if (in_array($k, $exclude)) {
                continue;
            }
            if ($object->_builtin && !$object->public) {
                continue;
            }
            $postTypes[] = array($object->cap->publish_posts . '|' . $object->name => $object->label);
        }

        // return
        return $postTypes;
    }

    /**
     * Return a list of public taxonomies and terms which are not empty
     *
     * @return array
     */
    public function getTaxonomiesAndTerms(): array
    {
        $result     = [];
        $taxonomies = get_taxonomies(array(
            'public' => true,
        ));

        foreach ($taxonomies as $taxonomy) {

            $terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
                'public'     => true,
            ));

            if (isset($terms)) {
                foreach ($terms as $term) {
                    $result[$taxonomy][$term->term_id] = ucfirst($term->name);
                }
            }

        }

        return $result;
    }


    public function getRoles(): array
    {
        $wpSmsListOfRole = Helper::getListOfRoles();
        $roles              = [];

        foreach ($wpSmsListOfRole as $keyItem => $valItem) {
            $roles[] = [$keyItem => $valItem['name']];
        }

        return $roles;
    }

    /**
     * Get the option key name for this group.
     * Returns null for core groups, addon name for addon groups.
     *
     * @return string|null
     */
    public function getOptionKeyName(): ?string
    {
        return null;
    }
}