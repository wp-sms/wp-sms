<?php

namespace WSms\Container;

defined('ABSPATH') || exit;

/**
 * Lazy-loading singleton service container.
 *
 * Stores factories (callables) and resolved instances.
 * Supports aliases for alternative lookups.
 *
 * @since 8.0
 */
class ServiceContainer
{
    /** @var self|null */
    private static ?self $instance = null;

    /** @var array<string, callable> Factory closures keyed by service ID. */
    private array $factories = [];

    /** @var array<string, object> Resolved singleton instances. */
    private array $instances = [];

    /** @var array<string, string> Alias -> canonical ID map. */
    private array $aliases = [];

    private function __construct()
    {
    }

    /**
     * Get the singleton container instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register a lazy factory for a service.
     *
     * The factory is only called when the service is first retrieved.
     *
     * @param string   $id      Service identifier.
     * @param callable $factory Factory that receives the container and returns the service.
     * @return self
     */
    public function register(string $id, callable $factory): self
    {
        $this->factories[$id] = $factory;
        return $this;
    }

    /**
     * Store an already-instantiated object as a singleton.
     *
     * @param string $id       Service identifier.
     * @param object $instance The service instance.
     * @return self
     */
    public function singleton(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        return $this;
    }

    /**
     * Create an alias that points to another service ID.
     *
     * @param string $alias  The alias name.
     * @param string $target The canonical service ID.
     * @return self
     */
    public function alias(string $alias, string $target): self
    {
        $this->aliases[$alias] = $target;
        return $this;
    }

    /**
     * Retrieve a service by ID (resolves aliases and lazy factories).
     *
     * @param string $id Service identifier or alias.
     * @return mixed|null The service instance, or null if not registered.
     */
    public function get(string $id)
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $this->instances[$id] = ($this->factories[$id])($this);
            return $this->instances[$id];
        }

        return null;
    }

    /**
     * Check whether a service is registered.
     *
     * @param string $id Service identifier or alias.
     * @return bool
     */
    public function has(string $id): bool
    {
        if (isset($this->aliases[$id])) {
            $id = $this->aliases[$id];
        }

        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    /**
     * Magic property access — delegates to get().
     *
     * @param string $name Service identifier.
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * Flush all resolved instances (keeps factories intact).
     *
     * @return void
     */
    public function reset(): void
    {
        $this->instances = [];
    }
}
