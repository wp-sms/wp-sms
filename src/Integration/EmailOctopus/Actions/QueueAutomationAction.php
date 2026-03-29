<?php

namespace WSms\Integration\EmailOctopus\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Integration\EmailOctopus\EmailOctopusApiClient;

defined('ABSPATH') || exit;

class QueueAutomationAction extends AbstractAction
{
    public function __construct(
        private readonly EmailOctopusApiClient $client,
    ) {
    }

    public function getId(): string
    {
        return 'emailoctopus_queue_automation';
    }

    public function getName(): string
    {
        return __('Queue into EmailOctopus Automation', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Queue a contact into an EmailOctopus automation sequence.', 'wp-sms');
    }

    public function getGroup(): string
    {
        return __('EmailOctopus', 'wp-sms');
    }

    public function getConfigSchema(): array
    {
        return [
            'automation_id' => [
                'type'        => 'string',
                'label'       => __('Automation ID', 'wp-sms'),
                'description' => __('The EmailOctopus automation ID. Find this in your automation settings.', 'wp-sms'),
                'required'    => true,
            ],
            'email' => [
                'type'        => 'string',
                'label'       => __('Email', 'wp-sms'),
                'description' => __('Contact email address.', 'wp-sms'),
                'required'    => true,
                'template'    => true,
                'example'     => '{{contact.email}}',
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'automation_id' => ['type' => 'string', 'title' => 'Automation ID'],
            'email'         => ['type' => 'string', 'title' => 'Email'],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $automationId = $config['automation_id'] ?? '';
        $email = $config['email'] ?? '';

        if (empty($automationId) || empty($email)) {
            return ActionResult::failure('Automation ID and email are required');
        }

        try {
            $this->client->queueIntoAutomation($automationId, $email);

            return ActionResult::success([
                'automation_id' => $automationId,
                'email'         => $email,
            ]);
        } catch (\RuntimeException $e) {
            return ActionResult::failure($e->getMessage());
        }
    }
}
