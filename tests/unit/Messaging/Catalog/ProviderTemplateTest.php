<?php

namespace WSms\Tests\Unit\Messaging\Catalog;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Catalog\ProviderTemplate;
use WSms\Messaging\Catalog\TemplateStatus;

class ProviderTemplateTest extends TestCase
{
    public function testToArrayAndFromArrayRoundTrip(): void
    {
        $original = new ProviderTemplate(
            id: 'HX123abc',
            name: 'OTP Template',
            language: 'en',
            category: 'authentication',
            status: TemplateStatus::Approved,
            bodyText: 'Your code is {{1}}. Expires in {{2}} minutes.',
            variableCount: 2,
            providerMeta: ['types' => ['twilio/whatsapp']],
        );

        $array = $original->toArray();
        $restored = ProviderTemplate::fromArray($array);

        $this->assertSame('HX123abc', $restored->id);
        $this->assertSame('OTP Template', $restored->name);
        $this->assertSame('en', $restored->language);
        $this->assertSame('authentication', $restored->category);
        $this->assertSame(TemplateStatus::Approved, $restored->status);
        $this->assertSame('Your code is {{1}}. Expires in {{2}} minutes.', $restored->bodyText);
        $this->assertSame(2, $restored->variableCount);
        $this->assertSame(['types' => ['twilio/whatsapp']], $restored->providerMeta);
    }

    public function testIsUsableDelegatesToStatus(): void
    {
        $approved = new ProviderTemplate('1', 'T', 'en', 'utility', TemplateStatus::Approved, '', 0);
        $pending = new ProviderTemplate('2', 'T', 'en', 'utility', TemplateStatus::Pending, '', 0);

        $this->assertTrue($approved->isUsable());
        $this->assertFalse($pending->isUsable());
    }

    public function testFromArrayHandlesMissingProviderMeta(): void
    {
        $template = ProviderTemplate::fromArray([
            'id' => 'X',
            'name' => 'T',
            'language' => 'en',
            'category' => 'utility',
            'status' => 'approved',
            'body_text' => 'Hi',
            'variable_count' => 0,
        ]);

        $this->assertSame([], $template->providerMeta);
    }
}
