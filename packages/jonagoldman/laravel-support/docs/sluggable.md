# Sluggable Eloquent Trait

```php
public function sluggable(): array
{
    return [];
}
```

One to one: Sets the `slug` attribute from the `name` attribute
```php
public function sluggable(): array
{
    return ['slug' => 'name'];
}
```

Many to one: Sets the `slug` attribute from the `name` and `nickname` attributes combined
```php
public function sluggable(): array
{
    return ['slug' => ['name', 'nickname']];
}
```

Many to many: Sets multiple `slug` attribute from the `name` attribute
```php
public function sluggable(): array
{
    return [
        'slug' => 'name',
        'other' => ['first', 'second']
    ];
}
```