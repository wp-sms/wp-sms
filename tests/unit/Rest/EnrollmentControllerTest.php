<?php

namespace WSms\Tests\Unit\Rest;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WSms\Auth\PolicyEngine;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\Contracts\SupportsEnrollmentConfirmation;
use WSms\Mfa\MfaManager;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Rest\EnrollmentController;
use WSms\Support\UserMeta;

class EnrollmentControllerTest extends TestCase
{
    private EnrollmentController $controller;
    private MockObject&MfaManager $mfaManager;
    private MockObject&PolicyEngine $policy;

    protected function setUp(): void
    {
        $this->mfaManager = $this->createMock(MfaManager::class);
        $this->policy = $this->createMock(PolicyEngine::class);

        $this->controller = new EnrollmentController(
            $this->mfaManager,
            $this->policy,
        );

        $GLOBALS['_test_current_user_id'] = 42;
        $GLOBALS['_test_user_meta'] = [];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['_test_current_user_id']);
        unset($GLOBALS['_test_user_meta']);
    }

    // ── Two-step channel (requires_confirmation) ──────────────────────

    public function testEnrollWithRequiresConfirmationDoesNotMarkComplete(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('enroll')->willReturn(
            new EnrollmentResult(true, 'Continue to verification.', ['requires_confirmation' => true])
        );

        $this->mfaManager->method('getChannel')->with('passkey')->willReturn($channel);

        // Pre-set enrollment pending to ensure it is NOT removed.
        update_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, '1');

        $request = new \WP_REST_Request('POST', '/auth/mfa/enroll');
        $request->set_param('channel_id', 'passkey');
        $request->set_param('data', []);

        $response = $this->controller->handleEnroll($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        // Enrollment gate must still be in place.
        $this->assertSame('1', get_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, true));
        // MFA should NOT be marked enabled yet.
        $this->assertSame('', get_user_meta(42, UserMeta::MFA_ENABLED, true));
    }

    // ── Single-step channel (no confirmation needed) ──────────────────

    public function testEnrollSingleStepMarksCompleteAndRemovesGate(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('enroll')->willReturn(
            new EnrollmentResult(true, 'Enrolled successfully.')
        );

        $this->mfaManager->method('getChannel')->with('email')->willReturn($channel);
        $this->policy->method('getAvailableMfaFactors')->willReturn([]);

        update_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, '1');

        $request = new \WP_REST_Request('POST', '/auth/mfa/enroll');
        $request->set_param('channel_id', 'email');
        $request->set_param('data', []);

        $response = $this->controller->handleEnroll($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        // Enrollment should be complete.
        $this->assertSame('1', get_user_meta(42, UserMeta::MFA_ENABLED, true));
        $this->assertSame('', get_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, true));
    }

    // ── enrollVerify completes enrollment ─────────────────────────────

    public function testEnrollVerifyMarksCompleteOnSuccess(): void
    {
        $channel = $this->createTwoStepChannel();
        $channel->method('confirmEnrollment')->willReturn(
            new EnrollmentResult(true, 'Passkey registered.')
        );

        $this->mfaManager->method('getChannel')->with('passkey')->willReturn($channel);
        $this->policy->method('getAvailableMfaFactors')->willReturn([]);

        update_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, '1');

        $request = new \WP_REST_Request('POST', '/auth/mfa/enroll/verify');
        $request->set_param('channel_id', 'passkey');
        $request->set_param('code', 'attestation-data');

        $response = $this->controller->handleEnrollVerify($request);

        $this->assertSame(200, $response->get_status());
        $this->assertTrue($response->get_data()['success']);

        // NOW enrollment should be complete.
        $this->assertSame('1', get_user_meta(42, UserMeta::MFA_ENABLED, true));
        $this->assertSame('', get_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, true));
    }

    // ── Failed enrollment doesn't change meta ─────────────────────────

    public function testFailedEnrollDoesNotChangeMeta(): void
    {
        $channel = $this->createMock(ChannelInterface::class);
        $channel->method('enroll')->willReturn(
            new EnrollmentResult(false, 'Enrollment failed.')
        );

        $this->mfaManager->method('getChannel')->with('passkey')->willReturn($channel);

        update_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, '1');

        $request = new \WP_REST_Request('POST', '/auth/mfa/enroll');
        $request->set_param('channel_id', 'passkey');
        $request->set_param('data', []);

        $response = $this->controller->handleEnroll($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);

        // Nothing should have changed.
        $this->assertSame('1', get_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, true));
        $this->assertSame('', get_user_meta(42, UserMeta::MFA_ENABLED, true));
    }

    public function testFailedEnrollVerifyDoesNotChangeMeta(): void
    {
        $channel = $this->createTwoStepChannel();
        $channel->method('confirmEnrollment')->willReturn(
            new EnrollmentResult(false, 'Verification failed.')
        );

        $this->mfaManager->method('getChannel')->with('passkey')->willReturn($channel);

        update_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, '1');

        $request = new \WP_REST_Request('POST', '/auth/mfa/enroll/verify');
        $request->set_param('channel_id', 'passkey');
        $request->set_param('code', 'bad-data');

        $response = $this->controller->handleEnrollVerify($request);

        $this->assertSame(400, $response->get_status());
        $this->assertFalse($response->get_data()['success']);

        // Nothing should have changed.
        $this->assertSame('1', get_user_meta(42, UserMeta::MFA_ENROLLMENT_PENDING, true));
        $this->assertSame('', get_user_meta(42, UserMeta::MFA_ENABLED, true));
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function createTwoStepChannel(): MockObject&TwoStepChannel
    {
        return $this->createMock(TwoStepChannel::class);
    }
}

/**
 * Test double interface combining ChannelInterface + SupportsEnrollmentConfirmation.
 */
interface TwoStepChannel extends ChannelInterface, SupportsEnrollmentConfirmation
{
}
