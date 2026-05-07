<?php

declare(strict_types=1);

namespace Psr\SimpleCache;

use Throwable;

/**
 * Describes a simple caching interface.
 *
 * @link https://www.php-fig.org/psr/psr-16/
 */
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function getMultiple(iterable $keys, mixed $default = null): iterable;

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool;

    public function deleteMultiple(iterable $keys): bool;

    public function has(string $key): bool;
}