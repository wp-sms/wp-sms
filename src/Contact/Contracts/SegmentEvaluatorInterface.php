<?php

namespace WSms\Contact\Contracts;

defined('ABSPATH') || exit;

interface SegmentEvaluatorInterface
{
    /** @return array Contact rows matching the conditions */
    public function evaluate(array $conditions, int $limit = 1000, int $offset = 0): array;

    public function count(array $conditions): int;
}
