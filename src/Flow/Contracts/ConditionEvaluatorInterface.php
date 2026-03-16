<?php

namespace WSms\Flow\Contracts;

defined('ABSPATH') || exit;

interface ConditionEvaluatorInterface
{
    public function evaluate(string $expression, array $payload): bool;

    public function validate(string $expression): bool;
}
