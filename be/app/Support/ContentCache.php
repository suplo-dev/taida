<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches public read responses per locale.
 *
 * Keys are namespaced by a version counter rather than by cache tags, so the
 * whole content cache can be invalidated in one write on any cache store —
 * including the database store this application ships with.
 */
class ContentCache
{
    private const TTL = 3600;

    private const VERSION_KEY = 'content:version';

    /**
     * @template TValue
     *
     * @param  array<string, mixed>  $params
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public static function remember(string $name, array $params, Closure $callback): mixed
    {
        return Cache::remember(static::key($name, $params), static::TTL, $callback);
    }

    /** @param array<string, mixed> $params */
    public static function key(string $name, array $params = []): string
    {
        ksort($params);

        return sprintf(
            'content:v%d:%s:%s:%s',
            static::version(),
            app()->getLocale(),
            $name,
            md5(json_encode($params, JSON_THROW_ON_ERROR)),
        );
    }

    public static function version(): int
    {
        return (int) Cache::rememberForever(static::VERSION_KEY, fn (): int => 1);
    }

    /** Invalidates every cached response by moving the namespace forward. */
    public static function flush(): void
    {
        Cache::forever(static::VERSION_KEY, static::version() + 1);
    }
}
