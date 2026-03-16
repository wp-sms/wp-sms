<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface MessageInterface
{
    public function getChannel(): string;

    public function getRecipient(): string;

    public function getBody(): string;

    public function getMeta(): array;

    public function getFlowExecutionId(): ?string;
}
