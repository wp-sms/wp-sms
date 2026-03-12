<?php

namespace WSms\Auth\ValueObjects;

defined('ABSPATH') || exit;

readonly class IdentifyResult
{
    public function __construct(
        public string $identifierType,
        public bool $userFound,
        public array $availableMethods,
        public ?string $defaultMethod,
        public bool $registrationAvailable,
        public array $registrationFields,
        public array $meta,
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
