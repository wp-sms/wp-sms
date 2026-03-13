<?php

namespace WSms\Tests\Unit\Social;

use PHPUnit\Framework\TestCase;
use WSms\Social\OAuthStateManager;

class OAuthStateManagerTest extends TestCase
{
    private OAuthStateManager $manager;

    protected function setUp(): void
    {
        $GLOBALS['_test_transients'] = [];
        $this->manager = new OAuthStateManager();
    }

    public function testCreateReturnsStateAndVerifier(): void
    {
        $result = $this->manager->create();

        $this->assertArrayHasKey('state', $result);
        $this->assertArrayHasKey('code_verifier', $result);
        $this->assertNotEmpty($result['state']);
        $this->assertNotEmpty($result['code_verifier']);
    }

    public function testConsumeReturnsDataForValidState(): void
    {
        $created = $this->manager->create();

        $consumed = $this->manager->consume($created['state']);

        $this->assertNotNull($consumed);
        $this->assertSame($created['code_verifier'], $consumed['code_verifier']);
    }

    public function testConsumeIsOneTimeUse(): void
    {
        $created = $this->manager->create();

        $first = $this->manager->consume($created['state']);
        $second = $this->manager->consume($created['state']);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function testConsumeReturnsNullForInvalidState(): void
    {
        $this->assertNull($this->manager->consume('nonexistent-state'));
    }

    public function testCreateWithLinkUserIdIncludesIt(): void
    {
        $result = $this->manager->create(42);

        $consumed = $this->manager->consume($result['state']);

        $this->assertSame(42, $consumed['link_user_id']);
    }

    public function testCreateWithoutLinkUserIdOmitsIt(): void
    {
        $result = $this->manager->create();

        $consumed = $this->manager->consume($result['state']);

        $this->assertArrayNotHasKey('link_user_id', $consumed);
    }

    public function testCodeChallengeIsDeterministic(): void
    {
        $verifier = 'test-code-verifier-123';

        $challenge1 = OAuthStateManager::codeChallenge($verifier);
        $challenge2 = OAuthStateManager::codeChallenge($verifier);

        $this->assertSame($challenge1, $challenge2);
        $this->assertNotSame($verifier, $challenge1);
    }
}
