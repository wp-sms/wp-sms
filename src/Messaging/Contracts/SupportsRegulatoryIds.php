<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

/**
 * For gateways that send full message text alongside regulatory metadata (Pattern B).
 *
 * Used in markets like India (DLT) and China (MIIT) where providers require
 * entity/template IDs as metadata alongside the rendered message body.
 */
interface SupportsRegulatoryIds
{
    /**
     * Schema for regulatory config fields (DLT entity ID, etc.).
     *
     * @return array Field definitions following the gateway config schema format
     */
    public function getRegulatoryConfigSchema(): array;

    /**
     * Build regulatory metadata to merge into the API request.
     *
     * @param array $regulatoryMeta Key-value pairs from TemplateMapping::$regulatoryMeta
     * @return array Provider-specific parameters to merge into the send request
     */
    public function buildRegulatoryPayload(array $regulatoryMeta): array;
}
