<?php

namespace WSms\SubscriptionForm;

defined('ABSPATH') || exit;

class SubscriptionFormRepository
{
    private const OPTION_KEY = 'wsms_subscription_forms';

    /** @var SubscriptionForm[]|null */
    private ?array $cache = null;

    private const RESERVED_SLUGS = [
        'subscribe', 'unsubscribe', 'confirm', 'verify',
        'login', 'register', 'profile', 'account',
    ];

    public function save(SubscriptionForm $form): string
    {
        $forms = $this->getAll();
        $id = $form->getId();
        $found = false;

        // Validate slug uniqueness.
        $slug = $form->getSlug();
        foreach ($forms as $existing) {
            if ($existing->getSlug() === $slug && $existing->getId() !== $id) {
                throw new \InvalidArgumentException('A subscription form with this slug already exists.');
            }
        }

        if (in_array($slug, self::RESERVED_SLUGS, true)) {
            throw new \InvalidArgumentException('This slug is reserved and cannot be used.');
        }

        // Validate fields contain at least email or phone.
        $fieldKeys = $form->getFieldKeys();
        if (!in_array('email', $fieldKeys, true) && !in_array('phone', $fieldKeys, true)) {
            throw new \InvalidArgumentException('Form must include at least an email or phone field.');
        }

        // Validate optin_channel matches an enabled field.
        if ($form->requiresDoubleOptIn()) {
            $channel = $form->getOptInChannel();
            if (!in_array($channel, $fieldKeys, true)) {
                throw new \InvalidArgumentException('Double opt-in channel must match an enabled field in the form.');
            }
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

    public function find(string $id): ?SubscriptionForm
    {
        foreach ($this->getAll() as $form) {
            if ($form->getId() === $id) {
                return $form;
            }
        }

        return null;
    }

    public function findBySlug(string $slug): ?SubscriptionForm
    {
        foreach ($this->getAll() as $form) {
            if ($form->getSlug() === $slug) {
                return $form;
            }
        }

        return null;
    }

    /**
     * @return SubscriptionForm[]
     */
    public function findAll(): array
    {
        return $this->getAll();
    }

    public function delete(string $id): bool
    {
        $forms = $this->getAll();
        $filtered = array_filter($forms, fn(SubscriptionForm $f) => $f->getId() !== $id);

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
     * @return SubscriptionForm[]
     */
    private function getAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $raw = get_option(self::OPTION_KEY, []);

        $this->cache = is_array($raw)
            ? array_map(fn(array $data) => SubscriptionForm::fromArray($data), $raw)
            : [];

        return $this->cache;
    }

    /**
     * @param SubscriptionForm[] $forms
     */
    private function persist(array $forms): void
    {
        $this->cache = $forms;

        $data = array_map(fn(SubscriptionForm $f) => $f->toArray(), $forms);

        update_option(self::OPTION_KEY, $data, false);
    }
}
