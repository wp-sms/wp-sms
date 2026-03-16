<?php

namespace WSms\Flow\Condition;

use WSms\Dependencies\Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use WSms\Dependencies\Symfony\Component\ExpressionLanguage\SyntaxError;
use WSms\Flow\Contracts\ConditionEvaluatorInterface;

defined('ABSPATH') || exit;

class ExpressionLanguageEvaluator implements ConditionEvaluatorInterface
{
    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new ExpressionLanguage();
    }

    public function evaluate(string $expression, array $payload): bool
    {
        try {
            return (bool) $this->language->evaluate($expression, $payload);
        } catch (\Throwable) {
            return false;
        }
    }

    public function validate(string $expression): bool
    {
        try {
            // lint() is the recommended way to validate expressions per Symfony docs.
            // It throws SyntaxError if the expression is invalid.
            $this->language->lint($expression, array_keys($this->getDefaultVariables()));
            return true;
        } catch (SyntaxError) {
            return false;
        }
    }

    private function getDefaultVariables(): array
    {
        return ['payload' => true];
    }
}
