<?php

namespace WSms\Integration\Line\Actions;

use WSms\Flow\Contracts\AbstractAction;
use WSms\Flow\Contracts\ActionResult;
use WSms\Line\LineBotClient;

defined('ABSPATH') || exit;

class MulticastAction extends AbstractAction
{
    private const BATCH_SIZE = 500;

    public function __construct(
        private readonly LineBotClient $botClient,
    ) {
    }

    public function getId(): string
    {
        return 'line.multicast';
    }

    public function getName(): string
    {
        return __('Multicast LINE Message', 'wp-sms');
    }

    public function getDescription(): string
    {
        return __('Send a message to multiple LINE users at once (auto-batched in groups of 500)', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'LINE';
    }

    public function getOutputSchema(): array
    {
        return [
            'batches_sent' => ['type' => 'integer', 'title' => 'Batches Sent'],
            'total_recipients' => ['type' => 'integer', 'title' => 'Total Recipients'],
        ];
    }

    public function getConfigSchema(): array
    {
        return [
            'recipients' => [
                'type'        => 'text',
                'label'       => __('Recipient User IDs', 'wp-sms'),
                'description' => __('Comma-separated LINE user IDs (auto-batched in groups of 500)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'U1234...,U5678...',
            ],
            'text' => [
                'type'        => 'text',
                'label'       => __('Message Text', 'wp-sms'),
                'description' => __('The message content (max 5,000 characters)', 'wp-sms'),
                'template'    => true,
                'required'    => true,
                'example'     => 'Hello everyone!',
            ],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $recipientsRaw = $config['recipients'] ?? '';
        $text = $config['text'] ?? '';

        if ($recipientsRaw === '' || $text === '') {
            return ActionResult::failure('Recipients and text are required.');
        }

        $recipients = array_filter(array_map('trim', explode(',', $recipientsRaw)));

        if (empty($recipients)) {
            return ActionResult::failure('No valid recipients provided.');
        }

        $text = mb_substr($text, 0, 5000);
        $messages = [['type' => 'text', 'text' => $text]];

        // Auto-batch into groups of 500.
        $batches = array_chunk($recipients, self::BATCH_SIZE);
        $batchesSent = 0;

        foreach ($batches as $batch) {
            $retryKey = wp_generate_uuid4();
            $result = $this->botClient->multicast($batch, $messages, $retryKey);

            if ($result === null) {
                return ActionResult::failure(sprintf(
                    'Multicast failed at batch %d of %d.',
                    $batchesSent + 1,
                    count($batches),
                ));
            }

            $batchesSent++;
        }

        return ActionResult::success([
            'batches_sent'     => $batchesSent,
            'total_recipients' => count($recipients),
        ]);
    }
}
