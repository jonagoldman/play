# laravel-support

Reusable Eloquent concerns, validation rules, and a drop-in password reset implementation for Laravel projects. Most of the code is small standalone traits and rules — pick what you need; nothing is forced on the application.

## What this package gives you

| Domain          | Component                               | What it's for                                                                  |
| --------------- | --------------------------------------- | ------------------------------------------------------------------------------ |
| Eloquent traits | `HasExpiration`                         | `expires_at` lifecycle, fluent `addDays/Weeks/...` helpers, expired/not-expired scopes |
|                 | `HasSlugs`                              | Auto-generate URL slugs on save with method-based config                       |
|                 | `HasSearch`                             | `?search=…` LIKE filter against an allowlist                                   |
|                 | `HasSorting`                            | `?sort=-col1,col2` multi-column ordering                                       |
|                 | `CanIncludeRelationships`               | `?include=…&with_count=…` controller-driven eager loading                      |
|                 | `HasChildren` / `HasParent`             | Single-Table Inheritance with type discriminator                               |
|                 | `InMemory`                              | Sushi-style models backed by in-memory or cached SQLite                        |
| Validation      | `ExistsEloquent` / `UniqueEloquent`     | Eloquent-aware exists/unique with builder closures                             |
|                 | `StrongPassword`                        | Pre-baked password rule presets                                                |
|                 | `ValidUlid`                             | Validate ULID format before hitting the database                               |
| Auth            | `PasswordBroker` + `DatabaseTokenRepository` | Drop-in replacement for Laravel's password reset (readonly, immutable)    |
| Generic         | `Actionable` / `HasValidation` / `HasDispatcher` | Building blocks for action / service classes                          |
| Console         | `route:show`                            | Enhanced route listing with filters, JSON output, and middleware expansion     |

## Requirements

- PHP 8.4+
- Laravel 13+

## Installation

```bash
composer require deplox/laravel-support
```

`SupportServiceProvider` is auto-discovered. The provider only loads translation files (used by validation rule messages) and registers the publishable lang directory:

```bash
php artisan vendor:publish --tag=laravel-support-translations
```

---

## Eloquent concerns

All concerns are in `Deplox\Support\Database\Eloquent\Concerns`. Use them on any model that extends `Illuminate\Database\Eloquent\Model`.

### `HasExpiration`

Manages an `expires_at` column with fluent helpers and query scopes.

```php
use Deplox\Support\Database\Eloquent\Concerns\HasExpiration;

final class Invitation extends Model
{
    use HasExpiration;

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime'];
    }
}

// Set explicitly
$invitation->expires(now()->addDays(7))->save();

// Fluent additive helpers (chainable, anchored on existing expires_at or now())
$invitation->addDays(3)->addHours(6)->save();

// Scopes
Invitation::whereExpired()->get();      // expires_at IS NOT NULL AND expires_at <= now()
Invitation::whereNotExpired()->get();   // expires_at IS NULL OR expires_at > now()

// Quick toggle via the cast attribute
$invitation->expired = true;            // sets expires_at to now()
$invitation->expired = false;           // nulls expires_at
```

Schema needs a nullable `expires_at` column. The trait does not register a `'booted'` callback — query scopes and accessors only.

### `HasSlugs`

Auto-populates slug columns on `saving`. Default mapping is `['name' => 'slug']`.

```php
use Deplox\Support\Database\Eloquent\Concerns\HasSlugs;

final class Post extends Model
{
    use HasSlugs;

    /** @return array<string, string> */
    public function getSluggable(): array
    {
        return ['title' => 'slug'];           // single source → single target
        // or: ['slug' => ['first', 'last']]  // many sources → one target (concat)
        // or: ['slug' => 'title', 'meta_slug' => 'description']  // multiple slug columns
    }
}

$post = Post::create(['title' => 'Hello World']);
$post->slug;  // 'hello-world'
```

The mapping is exposed as a method (`getSluggable()`) rather than a `$sluggable` property to avoid PHP 8.4 trait property conflicts with `final` classes that may redefine the same name. You can still set `protected $sluggable = [...]` and the default `getSluggable()` will pick it up.

`HasSlugs::slugify(string)` is a static wrapper around `Str::slug()` — override it on the model if you need custom slugification (e.g., transliteration).

### `HasSearch`

Adds a `whereSearch` scope that LIKE-filters by `?search=` against an allowlist of columns.

```php
use Deplox\Support\Database\Eloquent\Concerns\HasSearch;

final class User extends Model
{
    use HasSearch;

    protected $searchable = ['name', 'email'];   // or override getSearchable()
}

User::query()->whereSearch(['name', 'email'])->get();
// Request: /users?search=jane → WHERE name LIKE '%jane%' OR email LIKE '%jane%'
```

The scope intersects the `$allowed` argument with `getSearchable()`. Empty allowlists, missing query parameters, and blank search terms are all no-ops. Pass a different query parameter via the third argument: `whereSearch($cols, 'q')`.

### `HasSorting`

Adds a `withSorting` scope that orders by `?sort=-col1,col2,+col3`.

```php
use Deplox\Support\Database\Eloquent\Concerns\HasSorting;

User::query()->withSorting(['created_at', 'name'])->get();
// Request: /users?sort=-created_at,name → ORDER BY created_at DESC, name ASC
```

Columns not in the allowlist are silently dropped — invalid input never reaches the database. `+` and no prefix mean ASC; `-` means DESC.

### `CanIncludeRelationships`

Controller-driven relationship loading via `?include=` and `?with_count=`. Two methods:

- `withIncluded(allowed: [...], allowedCounts: [...])` — query scope, eager-loads at query time
- `loadIncluded(allowed: [...], allowedCounts: [...])` — for already-loaded models, lazy-loads missing relations

```php
final class UserController
{
    public function index()
    {
        return User::query()
            ->withIncluded(allowed: ['tokens', 'posts'], allowedCounts: ['posts'])
            ->get()
            ->toResourceCollection();
    }

    public function show(User $user)
    {
        return $user
            ->loadIncluded(allowed: ['tokens', 'posts'], allowedCounts: ['posts'])
            ->toResource();
    }
}

// Request: /users?include=tokens,posts&with_count=posts
```

The allowlist lives at the **controller level**, not the model — different endpoints can expose different relationship sets for the same model. Invalid include names are filtered via `array_intersect` and silently ignored.

### `HasChildren` + `HasParent` — Single-Table Inheritance

A polymorphic STI pattern inspired by [tighten/parental](https://github.com/tighten/parental). Single physical table, multiple Eloquent classes, discriminated by a `type` column.

```php
final class Animal extends Model
{
    use HasChildren;

    /** @var array<string, class-string> */
    protected $childTypes = [
        'dog' => Dog::class,
        'cat' => Cat::class,
    ];
}

final class Dog extends Animal
{
    use HasParent;

    public function bark(): string { return 'woof'; }
}

final class Cat extends Animal
{
    use HasParent;
}

// Polymorphic retrieval
Animal::all();              // → Collection of Dog and Cat instances based on `type`
Animal::find($id)->bark();  // works if it's a Dog

// Auto-discriminated creation
Dog::create(['name' => 'Fido']);
// → INSERT INTO animals (name, type) VALUES ('Fido', 'dog')

// Children share the parent's table
(new Dog)->getTable();  // 'animals'
```

#### Public API on `HasChildren`

| Method                                | Purpose                                                           |
| ------------------------------------- | ----------------------------------------------------------------- |
| `getInheritanceColumn(): string`      | Returns `$childColumn ?? 'type'`                                  |
| `getChildTypes(): array`              | Returns `$childTypes ?? []`                                       |
| `classFromAlias(string\|UnitEnum)`    | Resolve alias (or enum value) → child FQCN                        |
| `classToAlias(string)`                | Reverse lookup: FQCN → alias                                      |
| `newInstance($attrs, $exists = false)` | Eloquent override — picks correct subclass based on `type`        |
| `newFromBuilder($attrs, $connection)` | Eloquent override — same, used during query hydration             |

#### Public API on `HasParent`

| Method                              | Purpose                                                       |
| ----------------------------------- | ------------------------------------------------------------- |
| `hasParent(): bool`                 | Marker (always `true`)                                        |
| `parentHasHasChildrenTrait(): bool` | Detects whether parent uses `HasChildren`                     |
| `getTable(): string`                | Returns parent's snake-pluralized basename if `$table` unset  |
| `getForeignKey(): string`           | Generates FK as `{parent_snake}_{primary_key}`                |
| `getMorphClass(): string`           | Delegates to parent — children share the parent's morph alias |

`HasParent::bootHasParent` registers a `creating` listener that auto-fills the `type` column with the child's alias, plus a global scope that filters queries on the child class to its own type.

#### L13 boot safety

In Laravel 13, `Model::bootIfNotBooted` throws a `LogicException` if a model is instantiated while it is still booting. Both traits coordinate to avoid this:

- `HasChildren::bootHasChildren` tracks the parent class in `$parentBootingClasses` until the framework fires `'booted'`.
- `HasChildren::registerModelEvent` short-circuits before instantiating `new static` when the call originates from a child class's boot or while the parent is still booting.
- This propagation is what keeps child classes from re-registering their parent's model events twice.

You shouldn't need to think about this unless you're modifying the trait — the safety is built in.

#### Notes

- `getChildTypes()` returns an `alias => FQCN` array. The `type` column stores the alias (e.g. `'dog'`), not the class name.
- The `$childColumn` property is optional — leave it out and the column defaults to `'type'`.
- Children inherit the parent's relationships. The `belongsTo`/`belongsToMany` overrides on `HasChildren` rewrite foreign keys for child models so relationship naming stays sensible.
- Combine with `Relation::morphMap()` (the project's `essentials` package enforces this) so children show up as named morphs — not by FQCN — in polymorphic columns.

### `InMemory`

Sushi-style trait for models backed by in-memory or file-cached SQLite. Useful for static reference data (countries, currencies, plan tiers) without paying for a real table.

```php
use Deplox\Support\Database\Eloquent\Concerns\InMemory;

final class Country extends Model
{
    use InMemory;

    public $timestamps = false;
    protected $guarded = [];

    /** @var list<array{id: int, code: string, name: string}> */
    protected $rows = [
        ['id' => 1, 'code' => 'US', 'name' => 'United States'],
        ['id' => 2, 'code' => 'GB', 'name' => 'United Kingdom'],
        ['id' => 3, 'code' => 'FR', 'name' => 'France'],
    ];
}

Country::all();                          // queries the in-memory db
Country::where('code', 'FR')->first();   // works like any Eloquent model
```

#### Two storage modes

| Mode                          | Trigger                                       | Where                                                             |
| ----------------------------- | --------------------------------------------- | ----------------------------------------------------------------- |
| File cache (default)          | `protected $rows = [...]` is defined          | `storage/framework/cache/sushi/{kebab-class}.sqlite`              |
| Pure in-memory                | No `$rows` property defined                   | `:memory:` — table rebuilt every request                          |

The cache is invalidated via file mtime: when the model class file is modified more recently than the cache file, the cache is rebuilt automatically. No manual cache flush needed during development.

#### Schema inference

The trait infers column types from the first row, with fallbacks:

| First-row value      | Column type   |
| -------------------- | ------------- |
| `int`                | `integer`     |
| `numeric` non-int    | `float`       |
| `string`             | `string`      |
| `DateTimeImmutable`  | `dateTime`    |

Override with `protected $schema = ['code' => 'string', 'population' => 'integer']`.

#### L13 boot safety

`bootInMemory` previously instantiated the model directly to call `migrate()`. In Laravel 13 that throws a `LogicException`. The current implementation defers migration: the boot path only sets up the SQLite connection and records "needs migration" in a per-class flag, then `resolveConnection()` runs the migration on first access. From the caller's perspective nothing changes.

---

## Validation rules

All rules live in `Deplox\Support\Validation\Rules`.

### `ExistsEloquent`

Eloquent-aware replacement for the generic `exists` rule — reusable, IDE-friendly, supports query closures.

```php
use Deplox\Support\Validation\Rules\ExistsEloquent;

$rules = [
    'author_id' => [
        new ExistsEloquent(Author::class)                               // primary key by default
            ->query(fn ($q) => $q->where('active', true))
            ->withMessage('That author is unavailable.'),
    ],
    'slug' => [
        new ExistsEloquent(Author::class, 'slug'),                      // alternate column
    ],
];
```

Default message: `"A resource with this :attribute does not exist."` (translated via `support::validation.exists_model`).

### `UniqueEloquent`

Same shape as `ExistsEloquent`, plus `->ignore($id)` for update flows.

```php
use Deplox\Support\Validation\Rules\UniqueEloquent;

$rules = [
    'email' => [
        new UniqueEloquent(User::class, 'email')
            ->ignore($user->id)                                // skip current row
            ->query(fn ($q) => $q->whereNull('deleted_at')),   // soft-delete-aware
    ],
];
```

### `StrongPassword`

Static factories returning ready-to-use Laravel `Password` rules:

```php
use Deplox\Support\Validation\Rules\StrongPassword;

['password' => [StrongPassword::default()]]   // strict: length + complexity + uncompromised
['password' => [StrongPassword::moderate()]]  // looser, low-risk contexts
```

Use these instead of repeating long `Password::min(...)->mixedCase()->...` chains across the codebase.

### `ValidUlid`

Format check for ULID strings before they reach Eloquent.

```php
use Deplox\Support\Validation\Rules\ValidUlid;

$validated = $request->validate([
    'id' => [new ValidUlid()],
]);
```

Backed by `Str::isUlid()`. Message: `support::validation.valid_ulid`.

---

## Password reset replacement

Drop-in replacement for Laravel's password broker. Use this when you want a `final readonly`, immutable, and easier-to-reason-about token repository — with no behavioural difference to the framework default for end users.

| Class                              | Replaces                                                        |
| ---------------------------------- | --------------------------------------------------------------- |
| `Auth\Passwords\PasswordBroker`           | `Illuminate\Auth\Passwords\PasswordBroker`            |
| `Auth\Passwords\DatabaseTokenRepository`  | `Illuminate\Auth\Passwords\DatabaseTokenRepository`   |
| `Auth\Passwords\PasswordBrokerManager`    | `Illuminate\Auth\Passwords\PasswordBrokerManager`     |
| `Auth\Passwords\PasswordResetServiceProvider` | (not auto-discovered)                             |

### Opting in

Register the provider manually in `bootstrap/providers.php`:

```php
return [
    // ...
    \Deplox\Support\Auth\Passwords\PasswordResetServiceProvider::class,
];
```

This rebinds `'auth.password'` to the replacement manager. Existing config in `config/auth.php` (`passwords.users.{provider, table, expire, throttle}`) works unchanged.

### Differences from Laravel's default

- Both classes are `final readonly` — no inheritance hooks, fewer mutation surfaces.
- `DatabaseTokenRepository` uses ULID primary keys and HMAC-SHA256 for token storage (HMAC keyed with `app.key`).
- Throttle and expiry semantics match Laravel's defaults (`expire` and `throttle` config keys, both in seconds).

API surface mirrors Laravel — `sendResetLink`, `reset`, `getUser`, `createToken`, `deleteToken`, `tokenExists`, `recentlyCreatedToken`. Existing controllers, notifications, and tests continue to work without modification.

---

## Generic concerns

### `Actionable`

Mixin for invokable action classes with container resolution. Often used together with `HasValidation`.

```php
use Deplox\Support\Concerns\Actionable;
use Deplox\Support\Concerns\HasValidation;

final class CreateInvoice
{
    use Actionable, HasValidation;

    public function execute(array $data): Invoice
    {
        $valid = $this->validate($data);
        return Invoice::create($valid);
    }

    public function rules(): array
    {
        return ['amount_cents' => 'required|integer|min:1'];
    }
}

$invoice = CreateInvoice::run(['amount_cents' => 1000]);
// equivalent to: app(CreateInvoice::class)->execute(['amount_cents' => 1000])
```

API:

| Method                              | Purpose                                                    |
| ----------------------------------- | ---------------------------------------------------------- |
| `__invoke(...$args)`                | Calls `execute(...$args)`                                  |
| `execute(...$args)`                 | Override with the action's logic                           |
| `static make(array $params = [])`   | Container-resolve with constructor parameters              |
| `static run(...$args)`              | Shortcut: `static::make()->execute(...$args)`              |

### `HasValidation`

Validate-and-return-array-or-throw behaviour for actions and form objects.

```php
final class UpdateProfile
{
    use Actionable, HasValidation;

    public function rules(): array
    {
        return [
            'name'  => 'required|string|max:120',
            'email' => 'required|email',
        ];
    }

    public function messages(): array { return []; }
    public function attributes(): array { return []; }

    public function execute(User $user, array $data): User
    {
        $user->update($this->validate($data));
        return $user;
    }
}
```

`validate()` returns the validated array on success, throws `ValidationException` on failure — same shape as Laravel form requests.

### `HasDispatcher`

Adds `dispatch($event, ...$params)` to a class so it can fire events without depending on the `Event` facade.

```php
final class CompletePurchase
{
    use Actionable, HasDispatcher;

    public function execute(Order $order): Order
    {
        $order->complete();
        $this->dispatch(new OrderCompleted($order));
        return $order;
    }
}
```

---

## Console — `route:show`

A more flexible alternative to Laravel's `route:list`.

```bash
php artisan route:show
php artisan route:show --method=GET --uri=api --sort=name
php artisan route:show --json | jq '.[] | select(.middleware | contains("auth"))'
php artisan route:show --vendor                # include vendor routes (excluded by default)
```

| Option                 | Purpose                                                  |
| ---------------------- | -------------------------------------------------------- |
| `--method=...`         | Filter by HTTP method                                    |
| `--name=...`           | Filter by route name (substring match)                   |
| `--uri=...`            | Filter by URI substring                                  |
| `--domain=...`         | Filter by domain                                         |
| `--sort=...`           | Sort column (`uri` default)                              |
| `--reverse`            | Reverse sort order                                       |
| `--vendor`             | Include vendor-package routes                            |
| `--json`               | Output JSON (good for piping into `jq`, scripts)         |

The action column is humanised: closures show as `Closure`, invokable classes as `Invokable`, view/redirect responders as `View`/`Redirect`. Middleware is expanded through the router's middleware-alias map so you see `auth:api` instead of `Illuminate\Auth\Middleware\Authenticate:api`.

---

## Translations

Validation message keys are loaded from `resources/lang/{locale}/validation.php` under the `support::` namespace:

```php
'exists_model' => 'A resource with this :attribute does not exist.',
'unique_model' => 'A resource with this :attribute already exists.',
'valid_ulid'   => 'The :attribute must be a valid ULID.',
```

Override per project by publishing the lang directory and editing your copy:

```bash
php artisan vendor:publish --tag=laravel-support-translations
```

---

## Notes worth knowing

- **PHP 8.4 trait property conflicts.** Several traits (`HasSlugs`, `HasSearch`, `HasSorting`) expose configuration via methods (`getSluggable()`, `getSearchable()`) instead of trait properties. Trait properties don't compose cleanly with `final` classes that may redefine a property of the same name — the method-with-`??`-fallback pattern dodges that.

- **Laravel 13 boot rules.** `Model::bootIfNotBooted` now throws if a model is instantiated while booting. `HasChildren` and `InMemory` both contain explicit safety logic for this; if you write your own boot-time traits, keep `new static` out of the call path.

- **Allowlists, not blocklists.** `HasSearch`, `HasSorting`, and `CanIncludeRelationships` all require an explicit `$allowed` array. Anything not on it is silently dropped. This is deliberate — query parameters are user input.

---

## File layout reference

```
src/
├── Auth/
│   └── Passwords/
│       ├── DatabaseTokenRepository.php
│       ├── PasswordBroker.php
│       ├── PasswordBrokerManager.php
│       └── PasswordResetServiceProvider.php
├── Commands/
│   └── RouteShowCommand.php
├── Concerns/
│   ├── Actionable.php
│   ├── HasDispatcher.php
│   └── HasValidation.php
├── Database/
│   └── Eloquent/
│       └── Concerns/
│           ├── CanIncludeRelationships.php
│           ├── HasChildren.php
│           ├── HasExpiration.php
│           ├── HasParent.php
│           ├── HasSearch.php
│           ├── HasSlugs.php
│           ├── HasSorting.php
│           └── InMemory.php
├── Validation/
│   └── Rules/
│       ├── ExistsEloquent.php
│       ├── StrongPassword.php
│       ├── UniqueEloquent.php
│       └── ValidUlid.php
└── SupportServiceProvider.php
resources/
└── lang/
    └── en/
        └── validation.php
docs/
└── sluggable.md
```
