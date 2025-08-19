<?php

namespace WP_SMS\Settings;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Groups\AdvancedSettings;
use WP_SMS\Settings\Groups\FeatureSettings;
use WP_SMS\Settings\Groups\GatewaySettings;
use WP_SMS\Settings\Groups\GeneralSettings;
use WP_SMS\Settings\Groups\MessageButtonSettings;
use WP_SMS\Settings\Groups\NewsletterSettings;
use WP_SMS\Settings\Groups\NotificationSettings;
use WP_SMS\Settings\Groups\OTPSettings;

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
     * @var array Nested paths for groups
     */
    protected static $nestedPaths = [];

    /**
     * Singleton constructor: initializes all schema groups.
     */
    private function __construct()
    {
        // Core groups
        $this->registerGroup(new GeneralSettings(), 'core');
        $this->registerGroup(new GatewaySettings(), 'core');
        $this->registerGroup(new MessageButtonSettings(), 'core');
        $this->registerGroup(new NotificationSettings(), 'core');
        $this->registerGroup(new AdvancedSettings(), 'core');
        $this->registerGroup(new NewsletterSettings(), 'core');
        $this->registerGroup(new OTPSettings(), 'core');

        // Addons
        $this->registerGroup(new ProWordPressSettings(), 'addons');

        // Integrations with nested paths
        $this->registerGroup(new ContactForm7Settings(), 'integrations', 'integrations.contact_forms.contact_form_7');
        $this->registerGroup(new GravityFormsSettings(), 'integrations', 'integrations.contact_forms.gravityforms');
        $this->registerGroup(new QuformSettings(), 'integrations', 'integrations.contact_forms.quform');
        $this->registerGroup(new BuddyPressSettings(), 'integrations', 'integrations.community_membership.buddypress');
        $this->registerGroup(new UltimateMemberSettings(), 'integrations', 'integrations.community_membership.ultimate_member');
        $this->registerGroup(new WooCommerceSettings(), 'integrations', 'integrations.ecommerce.woocommerce');
        $this->registerGroup(new EasyDigitalDownloadsSettings(), 'integrations', 'integrations.ecommerce.edd');
        $this->registerGroup(new AwesomeSupportSettings(), 'integrations', 'integrations.support.awesome_support');
        $this->registerGroup(new JobManagerSettings(), 'integrations', 'integrations.jobs.job_manager');
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
     * Register a settings group under a specific category with optional nested path.
     *
     * @param AbstractSettingGroup $group
     * @param string $category
     * @param string|null $nestedPath Optional dot-separated path for nested structure
     * @return void
     */
    protected function registerGroup(AbstractSettingGroup $group, string $category, ?string $nestedPath = null)
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

        if ($nestedPath) {
            self::$nestedPaths[$name] = $nestedPath;
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
            $group = $this->getGroup($name);
            if ($group && $group->isApiVisible()) {
                $groups[$name] = $group->getFields();
            }
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
        $visibleGroups = [];
        foreach (self::$groups as $name => $group) {
            if ($group->isApiVisible()) {
                $visibleGroups[$name] = $group;
            }
        }
        return $visibleGroups;
    }

    /**
     * Get all registered groups including hidden ones (raw objects).
     *
     * @return AbstractSettingGroup[]
     */
    public function allGroupsIncludingHidden()
    {
        return self::$groups;
    }

    /**
     * Get a group by its name, including hidden groups.
     *
     * @param string $name
     * @return AbstractSettingGroup|null
     */
    public function getGroupIncludingHidden(string $name): ?AbstractSettingGroup
    {
        return self::$groups[$name] ?? null;
    }

    /**
     * Get all groups in a given category, including hidden ones.
     *
     * @param string $category
     * @return array
     */
    public function getCategoryIncludingHidden(string $category): array
    {
        $groups = [];

        if (!isset(self::$categories[$category])) {
            return $groups;
        }

        foreach (self::$categories[$category] as $name) {
            $group = $this->getGroupIncludingHidden($name);
            if ($group) {
                $groups[$name] = $group->getFields();
            }
        }

        return $groups;
    }

    /**
     * Export the full schema with labels and fields for all groups.
     *
     * @return array
     */
    public function export(): array
    {
        return $this->buildNestedStructure();
    }

    /**
     * Export the full schema including hidden groups.
     *
     * @return array
     */
    public function exportIncludingHidden(): array
    {
        return $this->buildNestedStructure(false, true);
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
        $group = $this->getGroup($name);
        if (!$group || !$group->isApiVisible()) {
            return null;
        }
        return $this->formatGroup($name);
    }

    /**
     * Export a single group schema by name, including hidden groups.
     *
     * @param string $name
     * @return array|null
     */
    public function exportGroupIncludingHidden(string $name): ?array
    {
        $group = $this->getGroupIncludingHidden($name);
        if (!$group) {
            return null;
        }
        return $this->formatGroup($name);
    }

    /**
     * Export group names and labels, grouped by category with nested structure.
     *
     * @return array
     */
    public function exportGroupList(): array
    {
        return $this->buildNestedStructure(true);
    }

    /**
     * Export group names and labels, grouped by category with nested structure, including hidden groups.
     *
     * @return array
     */
    public function exportGroupListIncludingHidden(): array
    {
        return $this->buildNestedStructure(true, true);
    }

    /**
     * Build nested structure from registered groups.
     *
     * @param bool $labelsOnly Whether to return only labels or full field data
     * @param bool $includeHidden Whether to include hidden groups
     * @return array
     */
    protected function buildNestedStructure(bool $labelsOnly = false, bool $includeHidden = false): array
    {
        $structure = [
            'core' => [],
            'addons' => [],
            'integrations' => [
                'label' => 'Integrations',
                'children' => [
                    'contact_forms' => [
                        'label' => 'Contact Forms',
                        'children' => [],
                    ],
                    'community_membership' => [
                        'label' => 'Community & Membership',
                        'children' => [],
                    ],
                    'ecommerce' => [
                        'label' => 'E-commerce',
                        'children' => [],
                    ],
                    'support' => [
                        'label' => 'Support',
                        'children' => [],
                    ],
                    'jobs' => [
                        'label' => 'Jobs',
                        'children' => [],
                    ],
                ],
            ],
        ];

        foreach (self::$groups as $name => $group) {
            // Skip groups that are not API visible if not including hidden
            if (!$group->isApiVisible() && !$includeHidden) {
                continue;
            }

            if ($labelsOnly) {
                $groupData = [
                    'name' => $name,
                    'label' => $group->getLabel(),
                    'icon' => $group->getIcon(),
                ];
            } else {
                $groupData = $this->formatGroup($name);
                if (!$groupData) {
                    continue;
                }
            }

            // Check if this group has a nested path
            if (isset(self::$nestedPaths[$name])) {
                $pathParts = explode('.', self::$nestedPaths[$name]);
                $this->insertIntoNestedStructure($structure, $pathParts, $name, $groupData);
            } else {
                // Handle flat structure for core and addons
                $category = $this->getGroupCategory($name);
                if ($category && $category !== 'integrations') {
                    $structure[$category][$name] = $groupData;
                }
            }
        }

        return $structure;
    }

    /**
     * Insert group data into nested structure.
     *
     * @param array &$structure
     * @param array $pathParts
     * @param string $name
     * @param array $groupData
     * @return void
     */
    protected function insertIntoNestedStructure(array &$structure, array $pathParts, string $name, array $groupData): void
    {
        $current = &$structure;
        
        foreach ($pathParts as $index => $part) {
            if ($index === count($pathParts) - 1) {
                // Last part - insert the group data using the path part as key
                $current[$part] = $groupData;
            } else {
                // Create intermediate structure if it doesn't exist
                if (!isset($current[$part])) {
                    $current[$part] = [
                        'label' => $this->formatLabel($part),
                        'children' => [],
                    ];
                }
                $current = &$current[$part]['children'];
            }
        }
    }

    /**
     * Get the category for a group name.
     *
     * @param string $name
     * @return string|null
     */
    protected function getGroupCategory(string $name): ?string
    {
        foreach (self::$categories as $category => $groupNames) {
            if (in_array($name, $groupNames, true)) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Format a path part into a readable label.
     *
     * @param string $part
     * @return string
     */
    protected function formatLabel(string $part): string
    {
        return ucwords(str_replace('_', ' ', $part));
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

        $sections = $group->getSections();
        usort($sections, function($a, $b) {
            return $a->order <=> $b->order;
        });

        return [
            'label' => $group->getLabel(),
            'icon' => $group->getIcon(),
            'sections' => array_map(function($section) {
                return $section->toArray();
            }, $sections),
        ];
    }
}
