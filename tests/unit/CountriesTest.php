<?php

namespace unit;

use WP_UnitTestCase;

class CountriesTest extends WP_UnitTestCase
{
    protected $countries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->countries = wp_sms_countries();
    }

    /**
     * Test getCountries returns non-empty array.
     */
    public function testGetCountriesReturnsNonEmptyArray()
    {
        $countries = $this->countries->getCountries();

        $this->assertIsArray($countries);
        $this->assertNotEmpty($countries);
    }

    /**
     * Test getCountries contains expected countries.
     */
    public function testGetCountriesContainsExpectedCountries()
    {
        $countries = $this->countries->getCountries();
        $names     = array_column($countries, 'name');

        $this->assertContains('United States (USA)', $names);
        $this->assertContains('United Kingdom (UK)', $names);
        $this->assertContains('Iran', $names);
    }

    /**
     * Test getCountries returns plucked field.
     */
    public function testGetCountriesReturnsPluckedField()
    {
        $countries = $this->countries->getCountries('name', 'dialCode');

        $this->assertIsArray($countries);
        $this->assertArrayHasKey('+1', $countries);
        $this->assertArrayHasKey('+98', $countries);
    }

    /**
     * Test getCountryByPrefix returns correct country.
     */
    public function testGetCountryByPrefixReturnsCorrectCountry()
    {
        $country = $this->countries->getCountryByPrefix('+98');

        $this->assertIsArray($country);
        $this->assertEquals('Iran', $country['name']);
    }

    /**
     * Test getCountryByPrefix returns null for invalid prefix.
     */
    public function testGetCountryByPrefixReturnsNullForInvalidPrefix()
    {
        $country = $this->countries->getCountryByPrefix('+999');

        $this->assertNull($country);
    }

    /**
     * Test getCountryByPrefix handles shared dial codes.
     */
    public function testGetCountryByPrefixHandlesSharedDialCodes()
    {
        // +44 is shared by UK and other territories (Guernsey, Jersey, Isle of Man)
        $country = $this->countries->getCountryByPrefix('+44');

        $this->assertIsArray($country);
        $this->assertContains('+44', $country['allDialCodes']);
    }

    /**
     * Test getCountryNamesByDialCode returns array.
     */
    public function testGetCountryNamesByDialCodeReturnsArray()
    {
        $names = $this->countries->getCountryNamesByDialCode();

        $this->assertIsArray($names);
        $this->assertNotEmpty($names);
    }

    /**
     * Test getCountryNamesByDialCode has correct format.
     */
    public function testGetCountryNamesByDialCodeHasCorrectFormat()
    {
        $names = $this->countries->getCountryNamesByDialCode();

        // Keys should be dial codes, values should be country names
        foreach ($names as $dialCode => $name) {
            $this->assertStringStartsWith('+', $dialCode);
            $this->assertIsString($name);
        }
    }

    /**
     * Test getCountryFullInfoByDialCode returns array.
     */
    public function testGetCountryFullInfoByDialCodeReturnsArray()
    {
        $info = $this->countries->getCountryFullInfoByDialCode();

        $this->assertIsArray($info);
        $this->assertNotEmpty($info);
    }

    /**
     * Test getCountryFullInfoByDialCode contains dial code in value.
     */
    public function testGetCountryFullInfoByDialCodeContainsDialCodeInValue()
    {
        $info = $this->countries->getCountryFullInfoByDialCode();

        foreach ($info as $dialCode => $fullInfo) {
            // Full info should contain the dial code
            $this->assertStringContainsString($dialCode, $fullInfo);
        }
    }

    /**
     * Test getCountriesMerged returns array.
     */
    public function testGetCountriesMergedReturnsArray()
    {
        $merged = $this->countries->getCountriesMerged();

        $this->assertIsArray($merged);
        $this->assertNotEmpty($merged);
    }

    /**
     * Test getCountriesMerged merges countries with same dial code.
     */
    public function testGetCountriesMergedMergesCountriesWithSameDialCode()
    {
        $merged = $this->countries->getCountriesMerged();

        // +1 is shared by USA and Canada (and others)
        if (isset($merged['+1'])) {
            // Should contain multiple country names
            $this->assertStringContainsString('United States', $merged['+1']);
        }
    }

    /**
     * Test getAllDialCodesByCode returns array.
     */
    public function testGetAllDialCodesByCodeReturnsArray()
    {
        $dialCodes = $this->countries->getAllDialCodesByCode();

        $this->assertIsArray($dialCodes);
        $this->assertNotEmpty($dialCodes);
    }

    /**
     * Test getAllDialCodesByCode has country codes as keys.
     */
    public function testGetAllDialCodesByCodeHasCountryCodesAsKeys()
    {
        $dialCodes = $this->countries->getAllDialCodesByCode();

        // Should have ISO country codes as keys
        $this->assertArrayHasKey('US', $dialCodes);
        $this->assertArrayHasKey('GB', $dialCodes);
        $this->assertArrayHasKey('IR', $dialCodes);
    }

    /**
     * Test getAllDialCodesByCode values are arrays.
     */
    public function testGetAllDialCodesByCodeValuesAreArrays()
    {
        $dialCodes = $this->countries->getAllDialCodesByCode();

        foreach ($dialCodes as $code => $codes) {
            $this->assertIsArray($codes);
        }
    }

    /**
     * Test country data structure has required fields.
     */
    public function testCountryDataStructureHasRequiredFields()
    {
        $countries     = $this->countries->getCountries();
        $firstCountry  = reset($countries);
        $requiredFields = ['name', 'dialCode', 'code'];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $firstCountry);
        }
    }

    /**
     * Test country has allDialCodes field.
     */
    public function testCountryHasAllDialCodesField()
    {
        $countries    = $this->countries->getCountries();
        $firstCountry = reset($countries);

        $this->assertArrayHasKey('allDialCodes', $firstCountry);
        $this->assertIsArray($firstCountry['allDialCodes']);
    }

    /**
     * Test getCountryByPrefix finds country with multiple dial codes.
     */
    public function testGetCountryByPrefixFindsCountryWithMultipleDialCodes()
    {
        // Some countries have multiple dial codes
        $country = $this->countries->getCountryByPrefix('+1');

        $this->assertIsArray($country);
        $this->assertNotEmpty($country['name']);
    }
}
