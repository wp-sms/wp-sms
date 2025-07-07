<?php

use PHPUnit\Framework\TestCase;
use WP_SMS\Settings\SchemaRegistry;
use WP_SMS\Settings\Groups\GeneralSettings;
use WP_SMS\Settings\Groups\Addons\ProWordPressSettings;
use WP_SMS\Settings\Groups\Addons\TwoWaySettings;
use WP_SMS\Settings\Groups\Addons\BookingCalendarSettings;
use WP_SMS\Settings\Groups\Addons\FluentCRMSettings;

class SchemaRegistryAddonTest extends TestCase
{
    public function testExportGroupIncludesAddonFieldForAddonGroups()
    {
        $registry = SchemaRegistry::instance();
        
        // Test addon groups - using the actual group names from the registry
        $addonGroups = [
            'pro_wordpress' => 'pro',
            'two_way' => 'two_way',
            'addon_booking_integrations_booking_calendar' => 'booking_integrations',
            'fluent_crm' => 'fluent_integrations',
        ];

        foreach ($addonGroups as $groupName => $expectedAddon) {
            $groupData = $registry->exportGroup($groupName);
            
            if ($groupData !== null) {
                $this->assertIsArray($groupData);
                $this->assertArrayHasKey('addon', $groupData);
                $this->assertEquals($expectedAddon, $groupData['addon']);
            }
        }
    }

    public function testExportGroupReturnsNullAddonForCoreGroups()
    {
        $registry = SchemaRegistry::instance();
        
        // Test core groups
        $coreGroups = [
            'general',
            'gateway',
            'message_button',
            'notification',
            'advanced',
            'newsletter',
        ];

        foreach ($coreGroups as $groupName) {
            $groupData = $registry->exportGroup($groupName);
            
            if ($groupData !== null) {
                $this->assertIsArray($groupData);
                $this->assertArrayHasKey('addon', $groupData);
                $this->assertNull($groupData['addon']);
            }
        }
    }

    public function testExportGroupMaintainsExistingStructure()
    {
        $registry = SchemaRegistry::instance();
        $groupData = $registry->exportGroup('pro_wordpress');
        
        if ($groupData !== null) {
            // Check that existing fields are still present
            $this->assertArrayHasKey('label', $groupData);
            $this->assertArrayHasKey('icon', $groupData);
            $this->assertArrayHasKey('sections', $groupData);
            $this->assertArrayHasKey('addon', $groupData);
            
            // Check that the addon field is a string for addon groups
            $this->assertIsString($groupData['addon']);
            $this->assertEquals('pro', $groupData['addon']);
        }
    }

    public function testExportGroupAddonFieldIsConsistent()
    {
        $registry = SchemaRegistry::instance();
        
        // Test that the same group always returns the same addon value
        $groupData1 = $registry->exportGroup('pro_wordpress');
        $groupData2 = $registry->exportGroup('pro_wordpress');
        
        if ($groupData1 !== null && $groupData2 !== null) {
            $this->assertEquals($groupData1['addon'], $groupData2['addon']);
            $this->assertEquals('pro', $groupData1['addon']);
        }
    }

    public function testExportGroupAddonFieldMatchesGroupOptionKeyName()
    {
        $registry = SchemaRegistry::instance();
        
        $addonGroups = [
            'pro_wordpress' => new ProWordPressSettings(),
            'two_way' => new TwoWaySettings(),
            'addon_booking_integrations_booking_calendar' => new BookingCalendarSettings(),
            'fluent_crm' => new FluentCRMSettings(),
        ];

        foreach ($addonGroups as $groupName => $groupInstance) {
            $groupData = $registry->exportGroup($groupName);
            if ($groupData !== null) {
                $expectedAddon = $groupInstance->getOptionKeyName();
                $this->assertEquals($expectedAddon, $groupData['addon']);
            }
        }
    }

    public function testExportGroupAddonFieldIsNullForCoreGroups()
    {
        $registry = SchemaRegistry::instance();
        $coreGroup = new GeneralSettings();
        
        $groupData = $registry->exportGroup('general');
        if ($groupData !== null) {
            $expectedAddon = $coreGroup->getOptionKeyName();
            $this->assertEquals($expectedAddon, $groupData['addon']);
            $this->assertNull($groupData['addon']);
        }
    }

    public function testExportGroupAddonFieldIsValidForAllGroups()
    {
        $registry = SchemaRegistry::instance();
        
        // Get all groups
        $allGroups = $registry->all();
        
        foreach ($allGroups as $groupName => $group) {
            $groupData = $registry->exportGroup($groupName);
            
            if ($groupData !== null) {
                $this->assertArrayHasKey('addon', $groupData);
                
                if ($groupData['addon'] !== null) {
                    // If addon is not null, it should be a valid string
                    $this->assertIsString($groupData['addon']);
                    $this->assertNotEmpty($groupData['addon']);
                    
                    // Should match the group's getOptionKeyName method
                    $this->assertEquals($group->getOptionKeyName(), $groupData['addon']);
                } else {
                    // If addon is null, the group should also return null
                    $this->assertNull($group->getOptionKeyName());
                }
            }
        }
    }

    public function testExportGroupAddonFieldDoesNotAffectOtherFields()
    {
        $registry = SchemaRegistry::instance();
        
        // Test that adding the addon field doesn't break existing functionality
        $groupData = $registry->exportGroup('pro_wordpress');
        
        if ($groupData !== null) {
            // Check that all required fields are present and have correct types
            $this->assertIsString($groupData['label']);
            $this->assertIsString($groupData['icon']);
            $this->assertIsArray($groupData['sections']);
            $this->assertIsString($groupData['addon']);
            
            // Check that sections still contain the expected structure
            foreach ($groupData['sections'] as $section) {
                $this->assertArrayHasKey('id', $section);
                $this->assertArrayHasKey('title', $section);
                $this->assertArrayHasKey('fields', $section);
            }
        }
    }
} 