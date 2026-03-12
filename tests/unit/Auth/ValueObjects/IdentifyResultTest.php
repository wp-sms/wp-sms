<?php

namespace WSms\Tests\Unit\Auth\ValueObjects;

use PHPUnit\Framework\TestCase;
use WSms\Auth\ValueObjects\IdentifyResult;

class IdentifyResultTest extends TestCase
{
    public function testToArrayUserFound(): void
    {
        $result = new IdentifyResult(
            identifierType: 'email',
            userFound: true,
            availableMethods: [
                ['method' => 'password', 'type' => 'password', 'channel' => 'password'],
                ['method' => 'email_otp', 'type' => 'otp', 'channel' => 'email'],
            ],
            defaultMethod: 'password',
            registrationAvailable: false,
            registrationFields: [],
            meta: ['masked_identifier' => 'j***@example.com'],
        );

        $array = $result->toArray();

        $this->assertSame('email', $array['identifier_type']);
        $this->assertTrue($array['user_found']);
        $this->assertCount(2, $array['available_methods']);
        $this->assertSame('password', $array['default_method']);
        $this->assertFalse($array['registration_available']);
        $this->assertArrayHasKey('meta', $array);
        $this->assertSame('j***@example.com', $array['meta']['masked_identifier']);
        $this->assertArrayNotHasKey('registration_fields', $array);
    }

    public function testToArrayUserNotFoundWithRegistration(): void
    {
        $result = new IdentifyResult(
            identifierType: 'email',
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: true,
            registrationFields: ['email', 'password'],
            meta: [],
        );

        $array = $result->toArray();

        $this->assertFalse($array['user_found']);
        $this->assertTrue($array['registration_available']);
        $this->assertArrayHasKey('registration_fields', $array);
        $this->assertSame(['email', 'password'], $array['registration_fields']);
        $this->assertArrayNotHasKey('meta', $array);
    }

    public function testToArrayEmptyMetaOmitsMeta(): void
    {
        $result = new IdentifyResult(
            identifierType: 'phone',
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: false,
            registrationFields: [],
            meta: [],
        );

        $array = $result->toArray();

        $this->assertArrayNotHasKey('meta', $array);
    }

    public function testToArrayEmptyFieldsOmitsKey(): void
    {
        $result = new IdentifyResult(
            identifierType: 'username',
            userFound: false,
            availableMethods: [],
            defaultMethod: null,
            registrationAvailable: false,
            registrationFields: [],
            meta: [],
        );

        $array = $result->toArray();

        $this->assertArrayNotHasKey('registration_fields', $array);
    }

    public function testReadonlyProperties(): void
    {
        $methods = [['method' => 'password', 'type' => 'password', 'channel' => 'password']];
        $meta = ['masked_identifier' => 'a***n'];

        $result = new IdentifyResult(
            identifierType: 'username',
            userFound: true,
            availableMethods: $methods,
            defaultMethod: 'password',
            registrationAvailable: false,
            registrationFields: [],
            meta: $meta,
        );

        $this->assertSame('username', $result->identifierType);
        $this->assertTrue($result->userFound);
        $this->assertSame($methods, $result->availableMethods);
        $this->assertSame('password', $result->defaultMethod);
        $this->assertFalse($result->registrationAvailable);
        $this->assertSame([], $result->registrationFields);
        $this->assertSame($meta, $result->meta);
    }
}
