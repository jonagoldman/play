# Testing Helpers

## ActingAsToken Trait

The `ActingAsToken` trait provides a convenient way to authenticate HTTP requests with a real bearer token in your tests.

### Setup

Add the trait to your test case (or Pest's `extend()`):

```php
use Deplox\Shield\Concerns\ActingAsToken;

pest()->extend(TestCase::class)->use(ActingAsToken::class)->in('Feature');
```

Or in a PHPUnit test class:

```php
use Deplox\Shield\Concerns\ActingAsToken;

final class TokenTest extends TestCase
{
    use ActingAsToken;
}
```

### Usage

```php
it('accesses a protected endpoint with bearer auth', function () {
    $user = User::factory()->create();

    $this->actingAsToken($user)
        ->getJson('/api/user')
        ->assertSuccessful();
});
```

The method creates a real token in the database, decorates it, and sets the `Authorization: Bearer {token}` header on the request.

### Parameters

```php
public function actingAsToken(
    Authenticatable&OwnsTokens $user,  // User who owns the token
    TokenType $type = TokenType::Bearer, // Token type
    ?DateTimeInterface $expiresAt = null, // Custom expiration
): static;
```

### Examples

```php
use Deplox\Shield\Enums\TokenType;

// Default bearer token
$this->actingAsToken($user)->getJson('/api/resource');

// Remember token
$this->actingAsToken($user, TokenType::Remember)->getJson('/api/resource');

// Token with custom expiration
$this->actingAsToken($user, expiresAt: now()->addHour())->getJson('/api/resource');
```

## Session Auth in Tests

For session-based authentication, use Laravel's built-in `actingAs()` with the `dynamic` guard:

```php
$this->actingAs($user, 'dynamic')
    ->getJson('/api/user')
    ->assertSuccessful();
```

The user will have `$user->token === null`, indicating session authentication.

## Factory Patterns

### Token Factory

Create tokens using the factory with the `owner` relationship name:

```php
use App\Models\Token;
use App\Models\User;

$user = User::factory()->create();

// Create a token for a user
$token = Token::factory()->for($user, 'owner')->create();

// Create a token with specific type
$token = Token::factory()->for($user, 'owner')->create([
    'type' => TokenType::Remember,
]);

// Create a token with a name
$token = Token::factory()->for($user, 'owner')->create([
    'name' => 'mobile-app',
]);
```

### Using createToken()

When you need a token with a populated `plain` property (e.g., to test API responses that include the token):

```php
$token = $user->createToken();
$plainToken = $token->plain; // The decorated token string

// Use it in requests
$this->withToken($plainToken, 'Bearer')
    ->getJson('/api/user')
    ->assertSuccessful();
```

### Testing Expired Tokens

```php
$token = Token::factory()->for($user, 'owner')->create([
    'expires_at' => now()->subDay(),
]);

// Token should be rejected
$this->withToken($token->plain ?? 'any', 'Bearer')
    ->getJson('/api/user')
    ->assertUnauthorized();
```

### Testing Unverified Users

If your `validateUser` callback checks verification:

```php
$unverified = User::factory()->unverified()->create();

$this->actingAsToken($unverified)
    ->getJson('/api/user')
    ->assertUnauthorized();
```

## Manual Token Auth in Tests

When you need the raw token for manual header setting:

```php
$token = $user->createToken();

$this->withToken($token->plain, 'Bearer')
    ->getJson('/api/user')
    ->assertSuccessful();
```

This is equivalent to what `actingAsToken()` does internally, but gives you access to the token model for assertions.
