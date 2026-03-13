<?php

namespace WSms\Mfa\Contracts;

use WSms\Mfa\ValueObjects\EnrollmentResult;

defined('ABSPATH') || exit;

/**
 * Channels that require a two-step enrollment: enroll → confirm.
 *
 * After enroll() creates a pending factor, the user must submit a
 * verification code to confirmEnrollment() to activate it.
 */
interface SupportsEnrollmentConfirmation
{
    /**
     * Confirm a pending enrollment by verifying the code sent during enroll().
     */
    public function confirmEnrollment(int $userId, string $code): EnrollmentResult;
}
