<?php

namespace WSms\Auth;

defined('ABSPATH') || exit;

class RegistrationFormRepository
{
    private const OPTION_KEY = 'wsms_registration_forms';

    /** @var RegistrationForm[]|null */
    private ?array $cache = null;

    private const RESERVED_SLUGS = [
        'login', 'register', 'forgot-password', 'reset-password',
        'verify', 'profile', 'security', 'account',
    ];

    public function save(RegistrationForm $form): string
    {
        $forms = $this->getAll();
        $id = $form->getId();
        $found = false;

        // Validate slug uniqueness.
        $slug = $form->getSlug();
        foreach ($forms as $existing) {
            if ($existing->getSlug() === $slug && $existing->getId() !== $id) {
                throw new \InvalidArgumentException('A form with this slug already exists.');
            }
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new \InvalidArgumentException('This slug is reserved and cannot be used.');
        }

        foreach ($forms as $i => $existing) {
            if ($existing->getId() === $id) {
                $forms[$i] = $form;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $forms[] = $form;
        }

        $this->persist($forms);

        return $id;
    }

    public function find(string $id): ?RegistrationForm
    {
        foreach ($this->getAll() as $form) {
            if ($form->getId() === $id) {
                return $form;
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?RegistrationForm
    {
        foreach ($this->getAll() as $form) {
            if ($form->getSlug() === $slug) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return RegistrationForm[]
     */
    public function findAll(): array
    {
        return $this->getAll();
    }

    public function delete(string $id): bool
    {
        $forms = $this->getAll();
        $filtered = array_filter($forms, fn(RegistrationForm $f) => $f->getId() !== $id);

        if (count($filtered) === count($forms)) {
            return false;
        }

        $this->persist(array_values($filtered));

        return true;
    }

    public function count(): int
    {
        return count($this->getAll());
    }

    /**
     * @return RegistrationForm[]
     */
    private function getAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = get_option(self::OPTION_KEY, []);

        $this->cache = is_array($raw)
            ? array_map(fn(array $data) => RegistrationForm::fromArray($data), $raw)
            : [];

        return $this->cache;
    }

    /**
     * @param RegistrationForm[] $forms
     */
    private function persist(array $forms): void
    {
        $this->cache = $forms;

        $data = array_map(fn(RegistrationForm $f) => $f->toArray(), $forms);

        update_option(self::OPTION_KEY, $data, false);
    }
}
