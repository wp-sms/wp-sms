<?php

namespace WSms\Queue;

use WSms\Dependencies\Psr\Log\LoggerInterface;

defined('ABSPATH') || exit;

class JobProcessor
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerHandler(string $jobType, callable $handler): void
    {
        $this->handlers[$jobType] = $handler;
    }

    public function process(array $args): void
    {
        $type = $args['type'] ?? '';
        $handler = $this->handlers[$type] ?? null;

        if (!$handler) {
            $this->logger->error('No handler for job type: ' . $type);
            return;
        }

        try {
            $handler($args['payload'] ?? []);
        } catch (\Throwable $e) {
            $this->handleFailure($args, $e);
        }
    }

    private function handleFailure(array $args, \Throwable $e): void
    {
        $retries = $args['retries'] ?? 0;
        $type = $args['type'] ?? 'unknown';

        $this->logger->error("Job {$type} failed: {$e->getMessage()}", [
            'job_id'    => $args['job_id'] ?? '',
            'retries'   => $retries,
            'exception' => $e,
        ]);

        if ($retries > 0) {
            $backoff = $args['backoff'] ?? 60;
            $args['retries'] = $retries - 1;
            as_schedule_single_action(time() + $backoff, 'wsms_process_job', [$args], 'wsms');
        } else {
            do_action('wsms_job_failed', $args, $e);
        }
    }
}
