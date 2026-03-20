<?php

namespace WSms\Tests\Unit\Messaging\Catalog;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Catalog\TemplateMapping;

class TemplateMappingTest extends TestCase
{
    private function createMapping(array $variableMap = []): TemplateMapping
    {
        return new TemplateMapping(
            templateType: 'otp',
            providerTemplateId: 'HX123',
            gatewayId: 'twilio',
            language: 'en',
            variableMap: $variableMap ?: ['otp_code' => '1', 'expiry_minutes' => '2'],
            providerTemplateName: 'OTP Template',
            providerTemplateBody: 'Your code is {{1}}. Expires in {{2}} min.',
            lastVerifiedAt: 1700000000,
        );
    }

    public function testResolveVariablesTransformsInternalToPositional(): void
    {
        $mapping = $this->createMapping();

        $result = $mapping->resolveVariables([
            'otp_code'       => '482916',
            'expiry_minutes' => '5',
        ]);

        $this->assertSame(['1' => '482916', '2' => '5'], $result);
    }

    public function testResolveVariablesIgnoresUnmappedVars(): void
    {
        $mapping = $this->createMapping(['otp_code' => '1']);

        $result = $mapping->resolveVariables([
            'otp_code'       => '123456',
            'unknown_var'    => 'something',
        ]);

        $this->assertSame(['1' => '123456'], $result);
    }

    public function testResolveVariablesReturnsEmptyForNoMatch(): void
    {
        $mapping = $this->createMapping(['otp_code' => '1']);

        $result = $mapping->resolveVariables(['other_var' => 'value']);

        $this->assertSame([], $result);
    }

    public function testGetMissingVariablesReturnsUnmappedNames(): void
    {
        $mapping = $this->createMapping(['otp_code' => '1']);

        $missing = $mapping->getMissingVariables([
            'otp_code'       => '123',
            'expiry_minutes' => '5',
            'site_name'      => 'Test',
        ]);

        $this->assertSame(['expiry_minutes', 'site_name'], $missing);
    }

    public function testGetMissingVariablesReturnsEmptyWhenAllMapped(): void
    {
        $mapping = $this->createMapping(['otp_code' => '1', 'expiry_minutes' => '2']);

        $missing = $mapping->getMissingVariables([
            'otp_code'       => '123',
            'expiry_minutes' => '5',
        ]);

        $this->assertSame([], $missing);
    }

    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $original = $this->createMapping();
        $array = $original->toArray();
        $restored = TemplateMapping::fromArray($array);

        $this->assertSame('otp', $restored->templateType);
        $this->assertSame('HX123', $restored->providerTemplateId);
        $this->assertSame('twilio', $restored->gatewayId);
        $this->assertSame('en', $restored->language);
        $this->assertSame(['otp_code' => '1', 'expiry_minutes' => '2'], $restored->variableMap);
        $this->assertSame('OTP Template', $restored->providerTemplateName);
        $this->assertSame('Your code is {{1}}. Expires in {{2}} min.', $restored->providerTemplateBody);
        $this->assertSame(1700000000, $restored->lastVerifiedAt);
    }

    public function testFromArrayHandlesOptionalFields(): void
    {
        $mapping = TemplateMapping::fromArray([
            'template_type'        => 'welcome',
            'provider_template_id' => 'HX999',
            'gateway_id'           => 'meta_cloud',
            'language'             => 'fr',
        ]);

        $this->assertSame([], $mapping->variableMap);
        $this->assertSame('', $mapping->providerTemplateName);
        $this->assertSame('', $mapping->providerTemplateBody);
        $this->assertNull($mapping->lastVerifiedAt);
    }
}
