<?php

declare(strict_types=1);

namespace Lubie;

use Closure;
use Lubie\Db\Database;
use Lubie\Support\Clock;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Small dependency-injection container: explicit factories for infrastructure,
 * constructor autowiring for application classes (one shared instance per request).
 */
final class Container
{
    /** @var array<string, object> */
    private array $instances = [];
    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    public function __construct(Database $db, Clock $clock)
    {
        $this->instances[Database::class] = $db;
        $this->instances[Clock::class] = $clock;
        $this->instances[self::class] = $this;
    }

    /** @param Closure(self): object $factory */
    public function factory(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function set(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->factories[$id])) {
            return $this->instances[$id] = ($this->factories[$id])($this);
        }
        if (!class_exists($id)) {
            throw new RuntimeException("Cannot resolve {$id}");
        }
        $ref = new ReflectionClass($id);
        $ctor = $ref->getConstructor();
        $args = [];
        foreach ($ctor?->getParameters() ?? [] as $p) {
            $t = $p->getType();
            if ($t instanceof ReflectionNamedType && !$t->isBuiltin()) {
                $args[] = $this->get($t->getName());
            } elseif ($p->isDefaultValueAvailable()) {
                $args[] = $p->getDefaultValue();
            } else {
                throw new RuntimeException("Cannot autowire {$id}::\${$p->getName()}");
            }
        }
        return $this->instances[$id] = $ref->newInstanceArgs($args);
    }
}
