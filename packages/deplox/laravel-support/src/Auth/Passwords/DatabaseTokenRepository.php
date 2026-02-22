<?php

declare(strict_types=1);

namespace Deplox\Support\Auth\Passwords;

use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final readonly class DatabaseTokenRepository implements TokenRepositoryInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private HasherContract $hasher,
        private string $key,
        private string $table,
        private int $expires = 60 * 60,
        private int $throttle = 60
    ) {}

    public function create(CanResetPasswordContract $user)
    {
        $this->deleteExisting($user);

        // We will create a new, random token for the user so that we can e-mail them
        // a safe link to the password reset form. Then we will insert a record in
        // the database so that we can verify the token within the actual reset.
        $token = $this->createNewToken();

        $this->getTable()->insert($this->getPayload($user, $token));

        return $token;
    }

    public function exists(CanResetPasswordContract $user, $token)
    {
        $record = (array) $this->getTable()->where('email', $user->getEmailForPasswordReset())->first();

        return $record && ! $this->tokenExpired($record['created_at']) && $this->hasher->check($token, $record['token']);
    }

    public function recentlyCreatedToken(CanResetPasswordContract $user)
    {
        $record = (array) $this->getTable()->where('email', $user->getEmailForPasswordReset())->first();

        return $record && $this->tokenRecentlyCreated($record['created_at']);
    }

    public function delete(CanResetPasswordContract $user): void
    {
        $this->deleteExisting($user);
    }

    public function deleteExpired(): void
    {
        $expiredAt = \Illuminate\Support\Facades\Date::now()->subSeconds($this->expires);

        $this->getTable()->where('created_at', '<', $expiredAt)->delete();
    }

    /**
     * Create a new token for the user.
     */
    public function createNewToken(): string
    {
        return hash_hmac('sha256', Str::random(40), $this->getKey());
    }

    /**
     * Return the application key.
     */
    public function getKey(): string
    {
        if (Str::startsWith($this->key, 'base64:')) {
            return base64_decode(mb_substr($this->key, 7));
        }

        return $this->key;
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the hasher instance.
     */
    public function getHasher(): HasherContract
    {
        return $this->hasher;
    }

    /**
     * Determine if the token was recently created.
     */
    private function tokenRecentlyCreated(string $createdAt): bool
    {
        if ($this->throttle <= 0) {
            return false;
        }

        return \Illuminate\Support\Facades\Date::parse($createdAt)->addSeconds($this->throttle)->isFuture();
    }

    /**
     * Determine if the token has expired.
     */
    private function tokenExpired(string $createdAt): bool
    {
        return \Illuminate\Support\Facades\Date::parse($createdAt)->addSeconds($this->expires)->isPast();
    }

    /**
     * Delete all existing reset tokens from the database.
     */
    private function deleteExisting(CanResetPasswordContract $user): int
    {
        return $this->getTable()->where('email', $user->getEmailForPasswordReset())->delete();
    }

    /**
     * Build the record payload for the table.
     */
    private function getPayload(CanResetPasswordContract $user, string $token): array
    {
        return [
            'id' => (string) Str::ulid(),
            'email' => $user->getEmailForPasswordReset(),
            'token' => $this->hasher->make($token),
            'created_at' => new Carbon(),
        ];
    }

    /**
     * Begin a new database query against the table.
     */
    private function getTable(): Builder
    {
        return $this->connection->table($this->table);
    }
}
