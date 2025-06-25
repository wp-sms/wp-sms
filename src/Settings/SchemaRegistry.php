<?php

namespace WP_SMS\Settings;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Groups\FeatureSettings;
use WP_SMS\Settings\Groups\GatewaySettings;
use WP_SMS\Settings\Groups\GeneralSettings;
use WP_SMS\Settings\Groups\MessageButtonSettings;
use WP_SMS\Settings\Groups\NewsletterSettings;
use WP_SMS\Settings\Groups\NotificationSettings;

// Addons
use WP_SMS\Settings\Groups\Addons\ProWordPressSettings;

// Integrations
use WP_SMS\Settings\Groups\Integrations\AwesomeSupportSettings;
use WP_SMS\Settings\Groups\Integrations\BuddyPressSettings;
use WP_SMS\Settings\Groups\Integrations\ContactForm7Settings;
use WP_SMS\Settings\Groups\Integrations\EasyDigitalDownloadsSettings;
use WP_SMS\Settings\Groups\Integrations\GravityFormsSettings;
use WP_SMS\Settings\Groups\Integrations\JobManagerSettings;
use WP_SMS\Settings\Groups\Integrations\QuformSettings;
use WP_SMS\Settings\Groups\Integrations\UltimateMemberSettings;
use WP_SMS\Settings\Groups\Integrations\WooCommerceSettings;

/**
 * Manages the settings schema for all groups and categories.
 * Provides export and retrieval methods for use in REST APIs and admin UI.
 */
class SchemaRegistry
{
    /**
     * @var SchemaRegistry|null Singleton instance
     */
    private static $instance = null;

    /**
     * @var AbstractSettingGroup[] All registered groups by name
     */
    protected static $groups = [];

    /**
     * @var array Categorized group names
     */
    protected static $categories = [
        'core' => [],
        'addons' => [],
        'integrations' => [],
    ];

    /**
     * Singleton constructor: initializes all schema groups.
     */
    private function __construct()
    {
        // Core groups
        $this->registerGroup(new GeneralSettings(), 'core');
        $this->registerGroup(new GatewaySettings(), 'core');
        $this->registerGroup(new FeatureSettings(), 'core');
        $this->registerGroup(new MessageButtonSettings(), 'core');
        $this->registerGroup(new NotificationSettings(), 'core');
        $this->registerGroup(new NewsletterSettings(), 'core');

        // Addons
        $this->registerGroup(new ProWordPressSettings(), 'addons');

        // Integrations
        $this->registerGroup(new AwesomeSupportSettings(), 'integrations');
        $this->registerGroup(new BuddyPressSettings(), 'integrations');
        $this->registerGroup(new ContactForm7Settings(), 'integrations');
        $this->registerGroup(new EasyDigitalDownloadsSettings(), 'integrations');
        $this->registerGroup(new GravityFormsSettings(), 'integrations');
        $this->registerGroup(new JobManagerSettings(), 'integrations');
        $this->registerGroup(new QuformSettings(), 'integrations');
        $this->registerGroup(new UltimateMemberSettings(), 'integrations');
        $this->registerGroup(new WooCommerceSettings(), 'integrations');
    }

    /**
     * Get the singleton instance.
     *
     * @return SchemaRegistry
     */
    public static function instance(): ?SchemaRegistry
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a settings group under a specific category.
     *
     * @param AbstractSettingGroup $group
     * @param string $category
     * @return void
     */
    protected function registerGroup(AbstractSettingGroup $group, string $category)
    {
        $name = $group->getName();

        if (isset(self::$groups[$name])) {
            trigger_error("Duplicate settings group name detected: '{$name}'", E_USER_WARNING);
            return;
        }

        self::$groups[$name] = $group;

        if (!in_array($name, self::$categories[$category], true)) {
            self::$categories[$category][] = $name;
        }
    }

    /**
     * Get a group by its name.
     *
     * @param string $name
     * @return AbstractSettingGroup|null
     */
    public function getGroup(string $name): ?AbstractSettingGroup
    {
        return self::$groups[$name] ?? null;
    }

    /**
     * Get all groups in a given category.
     *
     * @param string $category
     * @return array
     */
    public function getCategory(string $category): array
    {
        $groups = [];

        if (!isset(self::$categories[$category])) {
            return $groups;
        }

        foreach (self::$categories[$category] as $name) {
            $groups[$name] = $this->getGroup($name)->getFields();
        }

        return $groups;
    }

    /**
     * Get all registered groups (raw objects).
     *
     * @return AbstractSettingGroup[]
     */
    public function all()
    {
        return self::$groups;
    }

    /**
     * Export the full schema with labels and fields for all groups.
     *
     * @return array
     */
    public function export(): array
    {
        $exported = [];

        foreach (self::$groups as $name => $group) {
            $exported[$name] = $this->formatGroup($name);
        }

        return $exported;
    }

    /**
     * Export only one category's group schemas.
     *
     * @param string $category
     * @return array
     */
    public function exportCategory(string $category): array
    {
        return $this->getCategory($category);
    }

    /**
     * Export a single group schema by name.
     *
     * @param string $name
     * @return array|null
     */
    public function exportGroup(string $name): ?array
    {
        return $this->formatGroup($name);
    }

    /**
     * Export group names and labels, grouped by category.
     *
     * @return array
     */
    public function exportGroupList(): array
    {
        $categorized = [];

        foreach (self::$categories as $category => $groupNames) {
            $categorized[$category] = [];

            foreach ($groupNames as $name) {
                $group = $this->getGroup($name);

                if ($group) {
                    $categorized[$category][] = [
                        'name'  => $name,
                        'label' => $group->getLabel(),
                    ];
                }
            }
        }

        return $categorized;
    }

    /**
     * Internal utility to format a group into array format.
     *
     * @param string $name
     * @return array|null
     */
    protected function formatGroup(string $name): ?array
    {
        $group = $this->getGroup($name);

        if (!$group) {
            return null;
        }

        return [
            'label'  => $group->getLabel(),
            'fields' => array_map(function ($field) {
                return $field->toArray();
            }, $group->getFields()),
        ];
    }
}
