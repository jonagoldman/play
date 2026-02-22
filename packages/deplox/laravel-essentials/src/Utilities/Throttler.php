<?php

declare(strict_types=1);

namespace Deplox\Essentials\Utilities;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Container\Container;

/**
 * Throttler (Rate limiting wrapper)
 *
 * @see https://laravel.com/docs/rate-limiting
 */
final class Throttler
{
    private RateLimiter $limiter;

    /**
     * @param  string  $key  The rate limiter key represents the action being rate limited.
     * @param  int  $limit  The maximum number of allowed attempts for the given key.
     * @param  DateTimeInterface|DateInterval|int  $wait  The number of seconds until the available attempts are reset.
     */
    public function __construct(
        private string $key,
        private int $limit = 5,
        private DateTimeInterface|DateInterval|int $wait = 60
    ) {
        $this->limiter = Container::getInstance()->make(RateLimiter::class);
    }

    /**
     * Attempts to execute a callback if it's not limited.
     * Returns false when the callback has no remaining attempts available;
     * otherwise, the attempt method will return the callback's result or true.
     */
    public function attempt(Closure $callback): mixed
    {
        return $this->limiter->attempt($this->key, $this->limit, $callback, $this->wait);
    }

    /**
     * Determine if the limiter has been "accessed" too many times.
     */
    public function tooManyAttempts(): bool
    {
        return $this->limiter->tooManyAttempts($this->key, $this->limit);
    }

    /**
     * Increment (by 1) the counter.
     */
    public function hit(): int
    {
        return $this->limiter->hit($this->key, $this->wait);
    }

    /**
     * Increment the counter by a given amount.
     */
    public function increment(int $amount = 1): int
    {
        return $this->limiter->increment($this->key, $this->wait, $amount);
    }

    /**
     * Decrement the counter by a given amount.
     */
    public function decrement(int $amount = 1): int
    {
        return $this->limiter->decrement($this->key, $this->wait, $amount);
    }

    /**
     * Get the number of attempts.
     */
    public function attempts(): mixed
    {
        return $this->limiter->attempts($this->key);
    }

    /**
     * Reset the number of attempts.
     */
    public function resetAttempts(): bool
    {
        return $this->limiter->resetAttempts($this->key);
    }

    /**
     * Get the number of retries left.
     */
    public function remaining(): int
    {
        return $this->limiter->remaining($this->key, $this->limit);
    }

    /**
     * Clear the hits and lockout timer.
     */
    public function clear(): void
    {
        $this->limiter->clear($this->key);
    }

    /**
     * Get the number of seconds until the limiter is accessible again.
     */
    public function availableIn(): int
    {
        return $this->limiter->availableIn($this->key);
    }
}
