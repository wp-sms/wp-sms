<?php

namespace WSms\Auth\ValueObjects;

defined('ABSPATH') || exit;

class IdentifyResult
{
    public function __construct(
        public readonly string $identifierType,
        public readonly bool $userFound,
        public readonly array $availableMethods,
        public readonly ?string $defaultMethod,
        public readonly bool $registrationAvailable,
        public readonly array $registrationFields,
        public readonly array $meta,
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'identifier_type'        => $this->identifierType,
            'user_found'             => $this->userFound,
            'available_methods'      => $this->availableMethods,
            'default_method'         => $this->defaultMethod,
            'registration_available' => $this->registrationAvailable,
        ];

        if (!empty($this->registrationFields)) {
            $data['registration_fields'] = $this->registrationFields;
        }

        if (!empty($this->meta)) {
            $data['meta'] = $this->meta;
        }

        return $data;
    }
}
