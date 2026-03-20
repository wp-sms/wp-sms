<?php

namespace WSms\Tests\Unit\Messaging\Catalog;

use PHPUnit\Framework\TestCase;
use WSms\Messaging\Catalog\TemplateStatus;

class TemplateStatusTest extends TestCase
{
    /**
     * @dataProvider providerStatusMappings
     */
    public function testFromProviderStatusMapsCorrectly(string $input, TemplateStatus $expected): void
    {
        $this->assertSame($expected, TemplateStatus::fromProviderStatus($input));
    }

    public static function providerStatusMappings(): array
    {
        return [
            'approved'  => ['approved', TemplateStatus::Approved],
            'active'    => ['active', TemplateStatus::Approved],
            'APPROVED'  => ['APPROVED', TemplateStatus::Approved],
            'pending'   => ['pending', TemplateStatus::Pending],
            'submitted' => ['submitted', TemplateStatus::Pending],
            'in_review' => ['in_review', TemplateStatus::Pending],
            'rejected'  => ['rejected', TemplateStatus::Rejected],
            'denied'    => ['denied', TemplateStatus::Rejected],
            'paused'    => ['paused', TemplateStatus::Paused],
            'suspended' => ['suspended', TemplateStatus::Paused],
            'unknown'   => ['something_else', TemplateStatus::Disabled],
        ];
    }

    public function testIsUsableReturnsTrueOnlyForApproved(): void
    {
        $this->assertTrue(TemplateStatus::Approved->isUsable());
        $this->assertFalse(TemplateStatus::Pending->isUsable());
        $this->assertFalse(TemplateStatus::Rejected->isUsable());
        $this->assertFalse(TemplateStatus::Paused->isUsable());
        $this->assertFalse(TemplateStatus::Disabled->isUsable());
    }
}
