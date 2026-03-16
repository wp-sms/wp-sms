<?php

namespace WSms\Queue;

use WSms\Dependencies\Symfony\Component\Uid\Ulid;
use WSms\Queue\Contracts\JobInterface;
use WSms\Queue\Contracts\QueueInterface;

defined('ABSPATH') || exit;

class ActionSchedulerQueue implements QueueInterface
{
    private const HOOK = 'wsms_process_job';
    private const GROUP = 'wsms';

    public function dispatch(JobInterface $job): string
    {
        $jobId = (string) new Ulid();

        // as_enqueue_async_action() is the recommended way to dispatch
        // jobs for immediate async processing per Action Scheduler docs.
        as_enqueue_async_action(self::HOOK, [$this->buildArgs($jobId, $job)], self::GROUP);

        return $jobId;
    }

    public function schedule(JobInterface $job, \DateTimeInterface $runAt): string
    {
        $jobId = (string) new Ulid();

        as_schedule_single_action($runAt->getTimestamp(), self::HOOK, [$this->buildArgs($jobId, $job)], self::GROUP);

        return $jobId;
    }

    public function cancel(string $jobId): bool
    {
        // Search for pending action with this job_id and unschedule it.
        $actionId = as_get_scheduled_actions([
            'hook'   => self::HOOK,
            'status' => \ActionScheduler_Store::STATUS_PENDING,
            'group'  => self::GROUP,
        ], 'ids');

        // Action Scheduler doesn't support querying by arg content directly,
        // so cancellation by job ID requires iterating pending actions.
        // For now, return false — callers should use flow execution status instead.
        return false;
    }

    private function buildArgs(string $jobId, JobInterface $job): array
    {
        return [
            'job_id'  => $jobId,
            'type'    => $job->getType(),
            'payload' => $job->getPayload(),
            'retries' => $job->getMaxRetries(),
            'backoff' => $job->getRetryBackoff(),
        ];
    }
}
