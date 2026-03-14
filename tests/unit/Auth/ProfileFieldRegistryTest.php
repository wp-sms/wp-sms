<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\ProfileFieldRegistry;
use WSms\Auth\SettingsRepository;
use WSms\Auth\ValueObjects\ProfileFieldDefinition;

class ProfileFieldRegistryTest extends TestCase
{
    private ProfileFieldRegistry $registry;

    protected function setUp(): void
    {
        unset($GLOBALS['_test_options']['wsms_auth_settings']);
        $GLOBALS['_test_user_meta'] = [];
        $GLOBALS['_test_registered_meta'] = [];

        $this->registry = new ProfileFieldRegistry(new SettingsRepository());
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['_test_options'],
            $GLOBALS['_test_user_meta'],
            $GLOBALS['_test_registered_meta'],
        );
    }

    // --- System fields ---

    public function testGetSystemDefaultsReturnsSixFields(): void
    {
        $fields = $this->registry->getSystemDefaults();

        $this->assertCount(6, $fields);
        $ids = array_map(fn($f) => $f->id, $fields);
        $this->assertContains('email', $ids);
        $this->assertContains('password', $ids);
        $this->assertContains('phone', $ids);
        $this->assertContains('first_name', $ids);
        $this->assertContains('last_name', $ids);
        $this->assertContains('display_name', $ids);
    }

    public function testSystemFieldsAreSortedBySortOrder(): void
    {
        $fields = $this->registry->getSystemDefaults();

        for ($i = 1; $i < count($fields); $i++) {
            $this->assertLessThanOrEqual($fields[$i]->sortOrder, $fields[$i - 1]->sortOrder);
        }
    }

    // --- getAllFields ---

    public function testGetAllFieldsReturnsSystemFieldsWithNoCustom(): void
    {
        $fields = $this->registry->getAllFields();

        $this->assertCount(6, $fields);
    }

    public function testGetAllFieldsMergesCustomFields(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'sort_order' => 7],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $fields = $registry->getAllFields();

        $this->assertCount(7, $fields);

        $companyField = array_values(array_filter($fields, fn($f) => $f->id === 'company'));
        $this->assertNotEmpty($companyField);
        $this->assertSame('Company', $companyField[0]->label);
        $this->assertSame('custom', $companyField[0]->source);
    }

    public function testGetAllFieldsSortsBySortOrder(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'sort_order' => 3],
                ['id' => 'department', 'type' => 'select', 'label' => 'Department', 'source' => 'custom', 'meta_key' => 'department', 'sort_order' => 99],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $fields = $registry->getAllFields();

        $sortOrders = array_map(fn($f) => $f->sortOrder, $fields);
        $sorted = $sortOrders;
        sort($sorted);
        $this->assertSame($sorted, $sortOrders);
    }

    public function testSystemFieldOverridesFromProfileFields(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'phone', 'sort_order' => 1, 'required' => true, 'visibility' => 'registration'],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $fields = $registry->getAllFields();
        $phone = array_values(array_filter($fields, fn($f) => $f->id === 'phone'))[0];

        $this->assertSame('system', $phone->source); // Source stays system.
        $this->assertSame(1, $phone->sortOrder);
        $this->assertTrue($phone->required);
        $this->assertSame('registration', $phone->visibility);
    }

    // --- getFieldsForContext ---

    public function testGetFieldsForRegistrationExcludesProfileOnlyFields(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'bio', 'type' => 'textarea', 'label' => 'Bio', 'source' => 'custom', 'meta_key' => 'bio', 'visibility' => 'profile', 'sort_order' => 7],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $regFields = $registry->getFieldsForContext('registration');
        $bioFields = array_filter($regFields, fn($f) => $f->id === 'bio');

        $this->assertEmpty($bioFields);
    }

    public function testGetFieldsForProfileExcludesPasswordField(): void
    {
        $fields = $this->registry->getFieldsForContext('profile');
        $passwordFields = array_filter($fields, fn($f) => $f->id === 'password');

        $this->assertEmpty($passwordFields);
    }

    public function testGetFieldsForRegistrationIncludesBothVisibility(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'visibility' => 'both', 'sort_order' => 7],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $regFields = $registry->getFieldsForContext('registration');
        $companyFields = array_filter($regFields, fn($f) => $f->id === 'company');

        $this->assertNotEmpty($companyFields);
    }

    // --- getCustomFields ---

    public function testGetCustomFieldsExcludesSystemFields(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'sort_order' => 7],
                ['id' => 'phone', 'sort_order' => 1], // System override.
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $custom = $registry->getCustomFields();

        $this->assertCount(1, $custom);
        $this->assertSame('company', $custom[0]->id);
    }

    // --- sanitizeValue ---

    public function testSanitizeTextCallsSanitizeTextField(): void
    {
        $field = new ProfileFieldDefinition(
            id: 'name', type: 'text', label: 'Name',
            source: 'custom', metaKey: 'name',
        );

        $result = $this->registry->sanitizeValue($field, '<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testSanitizeCheckboxReturnsBool(): void
    {
        $field = new ProfileFieldDefinition(
            id: 'agree', type: 'checkbox', label: 'Agree',
            source: 'custom', metaKey: 'agree',
        );

        $this->assertTrue($this->registry->sanitizeValue($field, '1'));
        $this->assertTrue($this->registry->sanitizeValue($field, 'yes'));
        $this->assertFalse($this->registry->sanitizeValue($field, ''));
        $this->assertFalse($this->registry->sanitizeValue($field, '0'));
    }

    public function testSanitizeSelectRejectsInvalidValue(): void
    {
        $field = new ProfileFieldDefinition(
            id: 'dept', type: 'select', label: 'Department',
            source: 'custom', metaKey: 'dept',
            options: [['value' => 'eng', 'label' => 'Engineering'], ['value' => 'sales', 'label' => 'Sales']],
        );

        $this->assertSame('eng', $this->registry->sanitizeValue($field, 'eng'));
        $this->assertSame('', $this->registry->sanitizeValue($field, 'hacking'));
        $this->assertSame('', $this->registry->sanitizeValue($field, ''));
    }

    // --- validateFieldsConfig ---

    public function testValidateFieldsConfigReturnsErrorsForInvalidFields(): void
    {
        $errors = $this->registry->validateFieldsConfig([
            ['id' => '', 'type' => 'text', 'label' => 'No ID'],
            ['id' => 'ok', 'type' => 'invalid', 'label' => 'Bad Type'],
            ['id' => 'sel', 'type' => 'select', 'label' => 'No Options'],
            ['id' => 'dup', 'type' => 'text', 'label' => 'First'],
            ['id' => 'dup', 'type' => 'text', 'label' => 'Duplicate'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertGreaterThanOrEqual(4, count($errors));
    }

    public function testValidateFieldsConfigReturnsEmptyForValidFields(): void
    {
        $errors = $this->registry->validateFieldsConfig([
            ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom'],
            ['id' => 'dept', 'type' => 'select', 'label' => 'Department', 'source' => 'custom', 'options' => [['value' => 'eng', 'label' => 'Engineering']]],
        ]);

        $this->assertEmpty($errors);
    }

    // --- ProfileFieldDefinition value object ---

    public function testProfileFieldDefinitionFromArray(): void
    {
        $def = ProfileFieldDefinition::fromArray([
            'id' => 'company',
            'type' => 'text',
            'label' => 'Company',
            'source' => 'custom',
            'meta_key' => 'company',
            'visibility' => 'both',
            'required' => true,
            'sort_order' => 7,
            'placeholder' => 'Enter company',
        ]);

        $this->assertSame('company', $def->id);
        $this->assertSame('text', $def->type);
        $this->assertTrue($def->required);
        $this->assertSame(7, $def->sortOrder);
        $this->assertSame('Enter company', $def->placeholder);
        $this->assertFalse($def->isSystem());
    }

    public function testProfileFieldDefinitionToArray(): void
    {
        $def = new ProfileFieldDefinition(
            id: 'test', type: 'text', label: 'Test',
            source: 'custom', metaKey: 'test',
            placeholder: 'Hello',
        );

        $arr = $def->toArray();

        $this->assertSame('test', $arr['id']);
        $this->assertSame('Hello', $arr['placeholder']);
    }

    public function testProfileFieldDefinitionIsVisibleIn(): void
    {
        $both = new ProfileFieldDefinition(id: 'a', type: 'text', label: 'A', source: 'custom', metaKey: 'a', visibility: 'both');
        $reg = new ProfileFieldDefinition(id: 'b', type: 'text', label: 'B', source: 'custom', metaKey: 'b', visibility: 'registration');
        $prof = new ProfileFieldDefinition(id: 'c', type: 'text', label: 'C', source: 'custom', metaKey: 'c', visibility: 'profile');

        $this->assertTrue($both->isVisibleIn('registration'));
        $this->assertTrue($both->isVisibleIn('profile'));
        $this->assertTrue($reg->isVisibleIn('registration'));
        $this->assertFalse($reg->isVisibleIn('profile'));
        $this->assertFalse($prof->isVisibleIn('registration'));
        $this->assertTrue($prof->isVisibleIn('profile'));
    }

    // --- Hidden visibility ---

    public function testHiddenFieldExcludedFromAllContexts(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'sort_order' => 7, 'visibility' => 'hidden'],
                ['id' => 'dept', 'type' => 'text', 'label' => 'Department', 'source' => 'custom', 'meta_key' => 'dept', 'sort_order' => 8],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $regIds = array_map(fn($f) => $f->id, $registry->getFieldsForContext('registration'));
        $profIds = array_map(fn($f) => $f->id, $registry->getFieldsForContext('profile'));

        $this->assertNotContains('company', $regIds);
        $this->assertNotContains('company', $profIds);
        $this->assertContains('dept', $regIds);
    }

    public function testGetAllFieldsReturnsHiddenFields(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'source' => 'custom', 'meta_key' => 'company', 'sort_order' => 7, 'visibility' => 'hidden'],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $ids = array_map(fn($f) => $f->id, $registry->getAllFields());

        $this->assertContains('company', $ids);
    }

    public function testHiddenSystemFieldExcludedFromContext(): void
    {
        $GLOBALS['_test_options']['wsms_auth_settings'] = [
            'profile_fields' => [
                ['id' => 'display_name', 'visibility' => 'hidden'],
            ],
        ];
        $registry = new ProfileFieldRegistry(new SettingsRepository());

        $regIds = array_map(fn($f) => $f->id, $registry->getFieldsForContext('registration'));
        $profIds = array_map(fn($f) => $f->id, $registry->getFieldsForContext('profile'));

        $this->assertNotContains('display_name', $regIds);
        $this->assertNotContains('display_name', $profIds);
    }

    public function testHiddenIsValidVisibility(): void
    {
        $this->assertContains('hidden', ProfileFieldDefinition::VALID_VISIBILITY);
    }

    // --- Description and default value ---

    public function testProfileFieldDefinitionDescriptionAndDefaultValue(): void
    {
        $def = ProfileFieldDefinition::fromArray([
            'id' => 'country',
            'type' => 'text',
            'label' => 'Country',
            'source' => 'custom',
            'meta_key' => 'country',
            'description' => 'Your country of residence',
            'default_value' => 'United States',
        ]);

        $this->assertSame('Your country of residence', $def->description);
        $this->assertSame('United States', $def->defaultValue);

        $arr = $def->toArray();
        $this->assertSame('Your country of residence', $arr['description']);
        $this->assertSame('United States', $arr['default_value']);
    }

    public function testProfileFieldDefinitionEmptyDescriptionAndDefaultOmittedFromToArray(): void
    {
        $def = new ProfileFieldDefinition(
            id: 'test', type: 'text', label: 'Test',
            source: 'custom', metaKey: 'test',
        );

        $arr = $def->toArray();
        $this->assertArrayNotHasKey('description', $arr);
        $this->assertArrayNotHasKey('default_value', $arr);
    }

    // --- Helper ---

    private function makeWpdb(): void
    {
        $wpdb = new \stdClass();
        $wpdb->prefix = 'wp_';
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->get_results = function () {
            return [];
        };
        $GLOBALS['wpdb'] = $wpdb;
    }
}
