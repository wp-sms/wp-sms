<?php

namespace WSms\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when input validation fails.
 *
 * Carries a field-to-message map for structured error responses.
 */
class ValidationException extends DomainException
{
    /** @var array<string, string> */
    private array $errors;

    /**
     * @param array<string, string> $errors Field => message map.
     */
    public function __construct(array $errors, string $message = 'Validation failed.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @param array<string, string> $errors
     */
    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }

    public static function field(string $field, string $message): self
    {
        return new self([$field => $message]);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
