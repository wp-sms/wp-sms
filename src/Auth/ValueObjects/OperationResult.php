<?php

namespace WSms\Auth\ValueObjects;

class OperationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $error = null,
        public readonly ?int $userId = null,
        public readonly array $meta = [],
    ) {
    }

    public static function ok(string $message, array $meta = []): self
    {
        return new self(
            success: true,
            message: $message,
            meta: $meta,
        );
    }

    public static function fail(string $error, string $message): self
    {
        return new self(
            success: false,
            message: $message,
            error: $error,
        );
    }

    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'message' => $this->message,
        ];

        if ($this->error !== null) {
            $data['error'] = $this->error;
        }

        if ($this->userId !== null) {
            $data['user_id'] = $this->userId;
        }

        if (!empty($this->meta)) {
            foreach ($this->meta as $key => $value) {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
