<?php

namespace WSms\Queue\Contracts;

defined('ABSPATH') || exit;

interface QueueInterface
{
    /** Dispatch a job for immediate async processing. Returns job ID. */
    public function dispatch(JobInterface $job): string;

    /** Schedule a job for future execution. Returns job ID. */
    public function schedule(JobInterface $job, \DateTimeInterface $runAt): string;

    /** Cancel a scheduled job. */
    public function cancel(string $jobId): bool;
}
