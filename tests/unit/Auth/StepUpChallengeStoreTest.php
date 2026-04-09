<?php

namespace WSms\Tests\Unit\Auth;

use PHPUnit\Framework\TestCase;
use WSms\Auth\StepUpChallengeStore;
use WSms\Tests\Support\FakeSessionTokenStore;
use WSms\Tests\Support\FixedClock;

class StepUpChallengeStoreTest extends TestCase
{
    private FakeSessionTokenStore $sessionStore;
    private FixedClock $clock;
    private StepUpChallengeStore $store;

    protected function setUp(): void
    {
        $GLOBALS['_test_transients'] = [];
        $this->sessionStore = new FakeSessionTokenStore();
        $this->sessionStore->setCurrentToken('session-tok-1');
        $this->clock = new FixedClock(1_700_000_000);
        $this->store = new StepUpChallengeStore($this->sessionStore, $this->clock);
    }

    protected function tearDown(): void
    {
        $GLOBALS['_test_transients'] = [];
    }

    public function testCreateReturnsNullWithoutSession(): void
    {
        $this->sessionStore->setCurrentToken('');
        $this->assertNull($this->store->create(1, 'password'));
    }

    public function testCreateReturnsChallengeIdAndCanBeFetched(): void
    {
        $created = $this->store->create(1, 'password', ['foo' => 'bar']);

        $this->assertNotNull($created);
        $this->assertNotEmpty($created['challenge_id']);

        $fetched = $this->store->get($created['challenge_id']);
        $this->assertIsArray($fetched);
        $this->assertSame(1, $fetched['user_id']);
        $this->assertSame('password', $fetched['method']);
        $this->assertSame(['foo' => 'bar'], $fetched['context']);
        $this->assertSame(0, $fetched['attempts']);
    }

    public function testConsumeIsSingleUse(): void
    {
        $created = $this->store->create(1, 'password');
        $this->store->consume($created['challenge_id']);
        $this->assertNull($this->store->get($created['challenge_id']));
    }

    public function testGetReturnsNullForMissingChallenge(): void
    {
        $this->assertNull($this->store->get('no-such-challenge'));
    }

    public function testChallengeIsScopedToSession(): void
    {
        $this->sessionStore->setCurrentToken('session-tok-1');
        $created = $this->store->create(1, 'password');

        // Switch sessions — challenge must not be visible.
        $this->sessionStore->setCurrentToken('session-tok-2');
        $this->assertNull($this->store->get($created['challenge_id']));

        // Switch back — still there.
        $this->sessionStore->setCurrentToken('session-tok-1');
        $this->assertNotNull($this->store->get($created['challenge_id']));
    }

    public function testFailureCounterInvalidatesAfterMax(): void
    {
        $created = $this->store->create(1, 'password');
        $id = $created['challenge_id'];

        for ($i = 0; $i < StepUpChallengeStore::MAX_ATTEMPTS_PER_CHALLENGE - 1; $i++) {
            $attempts = $this->store->recordFailure($id);
            $this->assertSame($i + 1, $attempts);
        }

        // Still reachable just before the limit.
        $this->assertNotNull($this->store->get($id));

        // The final failure destroys the challenge.
        $finalAttempts = $this->store->recordFailure($id);
        $this->assertSame(StepUpChallengeStore::MAX_ATTEMPTS_PER_CHALLENGE, $finalAttempts);
        $this->assertNull($this->store->get($id));
    }

    public function testSixthActiveChallengeEvictsOldest(): void
    {
        $ids = [];
        for ($i = 0; $i < StepUpChallengeStore::MAX_ACTIVE_PER_SESSION; $i++) {
            $ids[] = $this->store->create(1, 'password')['challenge_id'];
        }

        // All present.
        foreach ($ids as $id) {
            $this->assertNotNull($this->store->get($id));
        }

        // 6th challenge evicts the oldest.
        $evicting = $this->store->create(1, 'password')['challenge_id'];

        $this->assertNull($this->store->get($ids[0]), 'Oldest challenge should have been evicted');
        $this->assertNotNull($this->store->get($evicting));

        // Remaining original challenges still there.
        for ($i = 1; $i < count($ids); $i++) {
            $this->assertNotNull($this->store->get($ids[$i]));
        }
    }
}
