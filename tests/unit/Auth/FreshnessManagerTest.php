<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\FreshnessManager;
use WSms\Tests\Support\FakeSessionTokenStore;
use WSms\Tests\Support\FixedClock;

class FreshnessManagerTest extends TestCase
{
    private FakeSessionTokenStore $store;
    private FixedClock $clock;
    private FreshnessManager $manager;

    protected function setUp(): void
    {
        $this->store = new FakeSessionTokenStore();
        $this->clock = new FixedClock(1_700_000_000);
        $this->manager = new FreshnessManager($this->store, $this->clock);
    }

    public function testIsFreshReturnsFalseWhenNoCurrentSessionToken(): void
    {
        $this->assertFalse($this->manager->isFresh(1, 3600));
    }

    public function testMarkFreshIsNoOpWithoutSessionToken(): void
    {
        // Should not throw even when no session is present.
        $this->manager->markFresh(1);
        $this->assertFalse($this->manager->isFresh(1, 3600));
    }

    public function testMarkFreshStampsCurrentSession(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', ['login' => 1_600_000_000]);

        $this->manager->markFresh(1);

        $this->assertTrue($this->manager->isFresh(1, 3600));
        $this->assertSame(0, $this->manager->getFreshAge(1));
    }

    public function testIsFreshHonorsWindowBoundary(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);
        $this->manager->markFresh(1);

        // Exactly at the window edge — still fresh.
        $this->clock->advance(3600);
        $this->assertTrue($this->manager->isFresh(1, 3600));

        // One second past — stale.
        $this->clock->advance(1);
        $this->assertFalse($this->manager->isFresh(1, 3600));
    }

    public function testIsFreshFallsBackToWpLoginField(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', ['login' => $this->clock->now() - 120]);

        // No wsms_fresh_auth_at — falls back to `login`.
        $this->assertTrue($this->manager->isFresh(1, 3600));
    }

    public function testWpLoginFallbackOlderThanWindowFails(): void
    {
        // Fresh manager per case because isFresh memoizes per-request.
        $store = new FakeSessionTokenStore();
        $store->setCurrentToken('tok1');
        $store->seed(1, 'tok1', ['login' => $this->clock->now() - 7200]);
        $manager = new FreshnessManager($store, $this->clock);

        $this->assertFalse($manager->isFresh(1, 3600));
    }

    public function testIsFreshReturnsFalseWhenBothFieldsMissing(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);

        $this->assertFalse($this->manager->isFresh(1, 3600));
    }

    public function testZeroOrNegativeWindowAlwaysRequiresStepUp(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);
        $this->manager->markFresh(1);

        $this->assertFalse($this->manager->isFresh(1, 0));
        $this->assertFalse($this->manager->isFresh(1, -1));
    }

    public function testGetFreshAgeReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->manager->getFreshAge(1));

        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);
        $this->assertNull($this->manager->getFreshAge(1));
    }

    public function testGetFreshAgeMeasuresFromMarkFresh(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);
        $this->manager->markFresh(1);

        $this->clock->advance(120);
        $this->assertSame(120, $this->manager->getFreshAge(1));
    }

    /**
     * Regression guard — mirrors Better Auth 1.6's
     * session-api.test.ts:68-120.
     *
     * Activity (further interaction without a new markFresh) must NOT
     * extend the freshness window. This test exists so anyone who
     * accidentally calls markFresh() on every request gets caught.
     */
    public function testActivityDoesNotExtendFreshWindow(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', []);
        $this->manager->markFresh(1);

        // Five minutes pass.
        $this->clock->advance(300);

        // Many "activity" reads — each of which only calls isFresh, never markFresh.
        for ($i = 0; $i < 10; $i++) {
            $this->manager->isFresh(1, 60); // window is 60s
        }

        // Still past the 60s window — must be stale.
        $this->assertFalse($this->manager->isFresh(1, 60));
    }

    public function testSessionIsolationBetweenTokens(): void
    {
        $this->store->setCurrentToken('tokA');
        $this->store->seed(1, 'tokA', []);
        $this->store->seed(1, 'tokB', []);

        // Mark A fresh only.
        $this->manager->markFresh(1);

        $this->assertTrue($this->manager->isFresh(1, 3600));

        // Switch to session B — it was never marked fresh.
        $this->store->setCurrentToken('tokB');
        $this->assertFalse($this->manager->isFresh(1, 3600));
    }

    public function testMarkFreshInvalidatesMemoizedSession(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', ['login' => $this->clock->now() - 7200]);

        // First read: stale by `login` fallback.
        $this->assertFalse($this->manager->isFresh(1, 3600));

        // markFresh writes a new timestamp AND must invalidate the cache,
        // so the follow-up isFresh reflects the freshly stamped value.
        $this->manager->markFresh(1);
        $this->assertTrue($this->manager->isFresh(1, 3600));
    }

    public function testInvalidUserIdReturnsFalse(): void
    {
        $this->store->setCurrentToken('tok1');
        $this->store->seed(1, 'tok1', ['login' => $this->clock->now()]);

        $this->assertFalse($this->manager->isFresh(0, 3600));
        $this->assertFalse($this->manager->isFresh(-5, 3600));
    }
}
